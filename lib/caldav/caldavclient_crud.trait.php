<?php
/* Copyright (C) 2025-2026  MATER Stéphane
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       htdocs/custom/caldavclient/lib/caldav/caldavclient_crud.trait.php
 * \ingroup    caldavclient
 * \brief      Création, mise à jour, suppression d événements sur le serveur.
 */

trait CalDAVClientCrudTrait
{
	/**
	 * Créer un événement sur le serveur CalDAV
	 *
	 * @param  array    $event   Données de l'événement
	 * @return bool             true si succès, false sinon
	 */
	public function createEvent($event)
	{
		// Générer un UID si absent
		if (empty($event['uid'])) {
			$event['uid'] = uniqid('dolibarr-', true).'@dolibarr.local';
		}
		
		// Générer le fichier iCalendar
		$ical = $this->generateICalendar($event);
		
		// URL de la ressource
		$resource_url = $this->calendar_path.$event['uid'].'.ics';
		
		$headers = array(
			'Content-Type: text/calendar; charset=utf-8'
		);
		
		$response = $this->request('PUT', $resource_url, $ical, $headers);
		
		return $response['code'] >= 200 && $response['code'] < 300;
	}

	/**
	 * Créer un événement sur un calendrier donné (URL complète)
	 *
	 * @param  string $calendar_url URL du calendrier (ex: .../calendars/user/nomcal/)
	 * @param  array  $event        Données de l'événement (start/end DateTime, summary, etc.)
	 * @return array               ['success'=>bool,'code'=>int,'etag'=>string|null,'error'=>string|null,'url'=>string]
	 */
	public function createEventOnCalendar($calendar_url, $event)
	{
		// Générer un UID si absent
		if (empty($event['uid'])) {
			$event['uid'] = uniqid('dolibarr-', true).'@dolibarr.local';
		}

		$ical = $this->generateICalendar($event);
		$resource_url = rtrim($calendar_url, '/').'/'.rawurlencode($event['uid']).'.ics';

		$headers = array(
			'Content-Type: text/calendar; charset=utf-8',
			'If-None-Match: *'
		);

		$response = $this->request('PUT', $resource_url, $ical, $headers);
		$etag = $this->extractEtagFromHeaders(isset($response['headers']) ? $response['headers'] : '');

		return array(
			'success' => ($response['code'] >= 200 && $response['code'] < 300),
			'code' => (int) $response['code'],
			'etag' => $etag,
			'error' => !empty($response['error']) ? $response['error'] : null,
			'url' => $resource_url
		);
	}

	/**
	 * Mettre à jour un événement sur le serveur CalDAV
	 *
	 * @param  string   $uid     UID de l'événement
	 * @param  string   $etag    ETag actuel (pour la vérification)
	 * @param  array    $event   Nouvelles données de l'événement
	 * @return bool             true si succès, false sinon
	 */
	public function updateEvent($uid, $etag, $event)
	{
		$event['uid'] = $uid;
		$ical = $this->generateICalendar($event);
		
		$resource_url = $this->calendar_path.$uid.'.ics';
		
		$headers = array('Content-Type: text/calendar; charset=utf-8');
		// If-Match uniquement si on a un vrai ETag
		if (!empty($etag) && $etag !== '*') {
			$headers[] = 'If-Match: '.$etag;
		}
		
		$response = $this->request('PUT', $resource_url, $ical, $headers);
		
		return $response['code'] >= 200 && $response['code'] < 300;
	}

	/**
	 * Mettre à jour un événement sur un calendrier donné (URL complète)
	 *
	 * @param  string $calendar_url URL du calendrier
	 * @param  string $uid          UID de l'événement
	 * @param  string $etag         ETag actuel (optionnel)
	 * @param  array  $event        Nouvelles données
	 * @return array               ['success'=>bool,'code'=>int,'etag'=>string|null,'error'=>string|null,'url'=>string]
	 */
	public function updateEventOnCalendar($calendar_url, $uid, $etag, $event, $remote_event = null)
	{
		if (empty($etag) || $etag === '*' || empty($remote_event['href']) || empty($remote_event['raw_calendar_data'])) {
			throw new RuntimeException('Refusing update without remote resource and ETag');
		}
		if (!empty($remote_event['unsupported_recurrence']) || preg_match('/^ATTENDEE(?:[:;])/mi', $remote_event['raw_calendar_data'])) {
			throw new RuntimeException('Conflict: recurring events and attendees require manual handling');
		}
		$event['uid'] = $uid;
		$generated = $this->generateICalendar($event);
		// Retain unknown properties and calendar components instead of reconstructing the resource.
		$properties = 'SUMMARY|DESCRIPTION|LOCATION|DTSTART|DTEND|DTSTAMP|LAST-MODIFIED';
		$ical = preg_replace('/\r?\n[ \t]/', '', $remote_event['raw_calendar_data']);
		preg_match_all('/^(?:'.$properties.')(?:;[^:\r\n]*)?:[^\r\n]*/mi', $generated, $replacement);
		$ical = preg_replace_callback('/BEGIN:VEVENT\r?\n(.*?)END:VEVENT/is', function ($match) use ($properties, $replacement) {
			$body = '';
			$depth = 0;
			foreach (preg_split('/\r?\n/', $match[1]) as $line) {
				if ($line === '') continue;
				if ($depth === 0 && preg_match('/^(?:'.$properties.')(?:;|:)/i', $line)) continue;
				if (stripos($line, 'BEGIN:') === 0) $depth++;
				$body .= $line."\r\n";
				if (stripos($line, 'END:') === 0) $depth--;
			}

			return "BEGIN:VEVENT\r\n".$body.implode("\r\n", $replacement[0])."\r\nEND:VEVENT";
		}, $ical, 1);

		$resource_url = $this->calendarResourceUrl($calendar_url, $remote_event['href']);

		$headers = array('Content-Type: text/calendar; charset=utf-8');
		if (!empty($etag) && $etag !== '*') {
			$headers[] = 'If-Match: '.$etag;
		}

		$response = $this->request('PUT', $resource_url, $ical, $headers);
		$newEtag = $this->extractEtagFromHeaders(isset($response['headers']) ? $response['headers'] : '');

		return array(
			'success' => ($response['code'] >= 200 && $response['code'] < 300),
			'code' => (int) $response['code'],
			'etag' => $newEtag,
			'error' => !empty($response['error']) ? $response['error'] : null,
			'url' => $resource_url
		);
	}

	/**
	 * Supprimer un événement du serveur CalDAV
	 *
	 * @param  string   $uid     UID de l'événement
	 * @param  string   $etag    ETag actuel (pour la vérification)
	 * @return bool             true si succès, false sinon
	 */
	public function deleteEvent($uid, $etag)
	{
		$resource_url = $this->calendar_path.$uid.'.ics';
		
		$headers = array();
		if (!empty($etag) && $etag !== '*') {
			$headers[] = 'If-Match: '.$etag;
		}
		
		$response = $this->request('DELETE', $resource_url, null, $headers);
		
		return $response['code'] >= 200 && $response['code'] < 300;
	}

	/**
	 * Supprimer un événement sur un calendrier donné (URL complète)
	 *
	 * @param  string $calendar_url URL du calendrier
	 * @param  string $uid          UID de l'événement
	 * @param  string $etag         ETag actuel (optionnel)
	 * @return array               ['success'=>bool,'code'=>int,'error'=>string|null,'url'=>string]
	 */
	public function deleteEventOnCalendar($calendar_url, $uid, $etag = null, $href = null)
	{
		if (empty($etag) || $etag === '*' || empty($href)) {
			throw new RuntimeException('Refusing deletion without remote resource and ETag');
		}
		$resource_url = $this->calendarResourceUrl($calendar_url, $href);

		$headers = array();
		if (!empty($etag) && $etag !== '*') {
			$headers[] = 'If-Match: '.$etag;
		}

		$response = $this->request('DELETE', $resource_url, null, $headers);

		return array(
			'success' => ($response['code'] >= 200 && $response['code'] < 300) || (int) $response['code'] === 404,
			'code' => (int) $response['code'],
			'error' => !empty($response['error']) ? $response['error'] : null,
			'url' => $resource_url
		);
	}

	/** Resolve only a direct resource in the exact authenticated calendar. */
	private function calendarResourceUrl($calendar_url, $href)
	{
		$calendar = parse_url($calendar_url);
		if (strpos($href, '/') === 0 && strpos($href, '//') !== 0) {
			$href = $calendar['scheme'].'://'.$calendar['host'].(isset($calendar['port']) ? ':'.$calendar['port'] : '').$href;
		} elseif (!preg_match('~^https?://~i', $href)) {
			$href = rtrim($calendar_url, '/').'/'.$href;
		}
		$resource = parse_url($href);
		$prefix = rtrim($calendar['path'], '/').'/';
		$path = isset($resource['path']) ? $resource['path'] : '';
		$name = substr($path, strlen($prefix));
		if (!$resource || isset($resource['user']) || isset($resource['pass']) || isset($resource['query']) || isset($resource['fragment'])
			|| strtolower($resource['scheme'] ?? '') !== strtolower($calendar['scheme'])
			|| strtolower($resource['host'] ?? '') !== strtolower($calendar['host'])
			|| ($resource['port'] ?? 443) !== ($calendar['port'] ?? 443)
			|| strpos($path, $prefix) !== 0 || empty($name)
			|| strpos(rawurldecode($name), '/') !== false || strpos(rawurldecode($name), '\\') !== false
			|| in_array(rawurldecode($name), array('.', '..'), true)) {
			throw new RuntimeException('Remote resource is outside the configured calendar');
		}
		return $href;
	}

	/**
	 * Extraire un ETag depuis des headers HTTP bruts (cURL)
	 *
	 * @param  string $headers_raw
	 * @return string|null
	 */
	private function extractEtagFromHeaders($headers_raw)
	{
		if (empty($headers_raw)) return null;
		if (preg_match('/(?:^|\r?\n)ETag:\s*([^\r\n]+)/i', $headers_raw, $m)) {
			return trim($m[1]);
		}
		return null;
	}

	/**
	 * Générer un fichier iCalendar à partir d'un événement (méthode statique publique)
	 *
	 * @param  array    $event   Données de l'événement
	 * @return string           Contenu iCalendar
	 */
	public static function generateICalendarStatic($event)
	{
		$obj = new self((object)['url' => '', 'username' => '', 'password' => '', 'calendar_path' => '']);
		return $obj->generateICalendar($event);
	}

	/**
	 * Générer un fichier iCalendar à partir d'un événement
	 *
	 * @param  array    $event   Données de l'événement
	 * @return string           Contenu iCalendar
	 */
	private function generateICalendar($event)
	{
		// Utiliser le fuseau horaire utilisateur Dolibarr si disponible.
		// Si non défini (cas fréquent), on force un défaut cohérent pour une société en France.
		$tzid = getDolGlobalString('MAIN_DOLIBARR_USER_TIMEZONE', '');
		if (empty($tzid)) {
			$tzid = date_default_timezone_get();
		}
		// Sécuriser: si le TZID n'est pas valide, fallback UTC
		try {
			new DateTimeZone($tzid);
		} catch (Exception $e) {
			$tzid = 'UTC';
		}

		$ical = "BEGIN:VCALENDAR\r\n";
		$ical .= "VERSION:2.0\r\n";
		$ical .= "PRODID:-//Dolibarr//CalDAV Client//FR\r\n";
		$ical .= "CALSCALE:GREGORIAN\r\n";
		$ical .= "X-WR-TIMEZONE:".$tzid."\r\n";
		$ical .= "BEGIN:VEVENT\r\n";
		$ical .= "UID:".$event['uid']."\r\n";
		$ical .= "DTSTAMP:".gmdate('Ymd\THis\Z')."\r\n";
		
		// CREATED et LAST-MODIFIED
		$now = gmdate('Ymd\THis\Z');
		$ical .= "CREATED:".$now."\r\n";
		$ical .= "LAST-MODIFIED:".$now."\r\n";
		
		if (!empty($event['summary'])) {
			$ical .= "SUMMARY:".$this->escapeICalendarText($event['summary'])."\r\n";
		}
		if (!empty($event['description'])) {
			$ical .= "DESCRIPTION:".$this->escapeICalendarText($event['description'])."\r\n";
		}
		if (!empty($event['location'])) {
			$ical .= "LOCATION:".$this->escapeICalendarText($event['location'])."\r\n";
		}

		// Organisateur (permet d'afficher le bon utilisateur côté CalDAV)
		if (!empty($event['organizer_email'])) {
			$cn = !empty($event['organizer_cn']) ? $this->escapeICalendarText($event['organizer_cn']) : '';
			$mailto = 'mailto:'.$event['organizer_email'];
			if (!empty($cn)) {
				$ical .= "ORGANIZER;CN=".$cn.":".$mailto."\r\n";
			} else {
				$ical .= "ORGANIZER:".$mailto."\r\n";
			}
		}
		
		// Gestion des événements sur toute la journée
		$is_all_day = !empty($event['all_day']);
		
		if ($event['start']) {
			if ($is_all_day) {
				// Format DATE pour les événements sur toute la journée (sans heure)
				$ical .= "DTSTART;VALUE=DATE:".$event['start']->format('Ymd')."\r\n";
			} else {
				// Format DATE-TIME en TZID (heure locale)
				$dtstart = clone $event['start'];
				$dtstart->setTimezone(new DateTimeZone($tzid));
				$ical .= "DTSTART;TZID=".$tzid.":".$dtstart->format('Ymd\THis')."\r\n";
			}
		}
		
		$dtendObj = !empty($event['end']) ? $event['end'] : null;
		if ($dtendObj && $event['start'] && $dtendObj->getTimestamp() < $event['start']->getTimestamp()) {
			throw new UnexpectedValueException('Event end precedes start');
		}

		if ($dtendObj) {
			if ($is_all_day) {
				// Pour les événements sur toute la journée, la date de fin est exclusive
				$end_date = clone $dtendObj;
				$ical .= "DTEND;VALUE=DATE:".$end_date->format('Ymd')."\r\n";
			} else {
				$dtend = clone $dtendObj;
				$dtend->setTimezone(new DateTimeZone($tzid));
				$ical .= "DTEND;TZID=".$tzid.":".$dtend->format('Ymd\THis')."\r\n";
			}
		}
		
		// Statut de l'événement
		if (!empty($event['status'])) {
			$status = strtoupper($event['status']);
			if (in_array($status, array('TENTATIVE', 'CONFIRMED', 'CANCELLED'))) {
				$ical .= "STATUS:".$status."\r\n";
			}
		}
		
		// Transparence (occupe le temps ou non)
		if (!empty($event['transparent'])) {
			$ical .= "TRANSP:TRANSPARENT\r\n";
		} else {
			$ical .= "TRANSP:OPAQUE\r\n";
		}
		
		$ical .= "END:VEVENT\r\n";
		$ical .= "END:VCALENDAR\r\n";
		
		return $ical;
	}

	/**
	 * Échapper le texte pour iCalendar (méthode statique publique)
	 *
	 * @param  string $text   Texte à échapper
	 * @return string        Texte échappé
	 */
	public static function escapeICalendarTextStatic($text)
	{
		$text = str_replace('\\', '\\\\', $text);
		$text = str_replace(',', '\\,', $text);
		$text = str_replace(';', '\\;', $text);
		$text = str_replace("\n", '\\n', $text);
		return $text;
	}

	/**
	 * Échapper le texte pour iCalendar
	 *
	 * @param  string $text   Texte à échapper
	 * @return string        Texte échappé
	 */
	private function escapeICalendarText($text)
	{
		$text = str_replace('\\', '\\\\', $text);
		$text = str_replace(',', '\\,', $text);
		$text = str_replace(';', '\\;', $text);
		$text = str_replace("\n", '\\n', $text);
		return $text;
	}
}
