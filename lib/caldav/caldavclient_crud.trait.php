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
			'Content-Type: text/calendar; charset=utf-8'
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
	public function updateEventOnCalendar($calendar_url, $uid, $etag, $event)
	{
		$event['uid'] = $uid;
		$ical = $this->generateICalendar($event);
		$resource_url = rtrim($calendar_url, '/').'/'.rawurlencode($uid).'.ics';

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
	public function deleteEventOnCalendar($calendar_url, $uid, $etag = null)
	{
		$resource_url = rtrim($calendar_url, '/').'/'.rawurlencode($uid).'.ics';
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

	/**
	 * Extraire un ETag depuis des headers HTTP bruts (cURL)
	 *
	 * @param  string $headers_raw
	 * @return string|null
	 */
	private function extractEtagFromHeaders($headers_raw)
	{
		if (empty($headers_raw)) return null;
		if (preg_match('/\r?\nETag:\s*([^\r\n]+)/i', $headers_raw, $m)) {
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
		if (empty($tzid) || $tzid === 'UTC') {
			$tzid = 'Europe/Paris';
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
		
		// DTEND: certains serveurs ignorent une fin vide ou <= début.
		// On force une fin à +1h si nécessaire.
		$dtendObj = !empty($event['end']) ? $event['end'] : null;
		if (!$is_all_day && $event['start']) {
			$startTs = $event['start'] instanceof DateTime ? $event['start']->getTimestamp() : 0;
			$endTs = ($dtendObj instanceof DateTime) ? $dtendObj->getTimestamp() : 0;
			if (empty($endTs) || $endTs <= $startTs) {
				$dtendObj = new DateTime('@'.($startTs + 3600));
			}
		}

		if ($dtendObj) {
			if ($is_all_day) {
				// Pour les événements sur toute la journée, la date de fin est exclusive
				// Il faut donc ajouter 1 jour
				$end_date = clone $dtendObj;
				$end_date->modify('+1 day');
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
