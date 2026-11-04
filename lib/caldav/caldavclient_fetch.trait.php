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
 * \file       htdocs/custom/caldavclient/lib/caldav/caldavclient_fetch.trait.php
 * \ingroup    caldavclient
 * \brief      Récupération des événements (REPORT, cache, parse réponse).
 */

trait CalDAVClientFetchTrait
{
	/**
	 * Récupérer les événements d'un calendrier pour une période donnée
	 *
	 * @param  string   $calendar_url   URL du calendrier
	 * @param  DateTime $start          Date de début
	 * @param  DateTime $end           Date de fin
	 * @param  bool     $complete       Retrieve the complete calendar inventory for deletion detection
	 * @return array                    Tableau d'événements au format iCalendar
	 */
	public function getEvents($calendar_url = null, $start = null, $end = null, $use_cache = true, $complete = false)
	{
		global $conf;
		
		// Utiliser l'URL du calendrier fournie ou celle par défaut
		$url = $calendar_url ? $calendar_url : $this->calendar_path;
		
		// Si pas de dates fournies, utiliser une période par défaut
		if (!$start) {
			$start = new DateTime();
			$start->modify('-1 month');
		}
		if (!$end) {
			$end = new DateTime();
			$end->modify('+1 year');
		}
		
		// S'assurer que les dates sont en UTC
		if ($start->getTimezone()->getName() != 'UTC') {
			$start->setTimezone(new DateTimeZone('UTC'));
		}
		if ($end->getTimezone()->getName() != 'UTC') {
			$end->setTimezone(new DateTimeZone('UTC'));
		}
		
	// Générer une clé de cache basée sur l'URL et la période
	$cache_key = md5($url . ($complete ? 'complete' : $start->format('Ymd') . $end->format('Ymd')));
	$cache_dir = DOL_DATA_ROOT . '/caldavclient/cache';
	if (!is_dir($cache_dir)) {
		@mkdir($cache_dir, 0755, true);
	}
	$cache_file = $cache_dir . '/events_' . $cache_key . '.cache';
	$cache_duration = 600; // 10 minutes (optimisé pour meilleures performances)
		
		// Vérifier le cache si activé
		if ($use_cache && file_exists($cache_file)) {
			$cache_age = time() - filemtime($cache_file);
			if ($cache_age < $cache_duration) {
				$cached_data = @file_get_contents($cache_file);
				if ($cached_data !== false) {
					$events = @unserialize($cached_data);
					if (is_array($events)) {
						dol_syslog("CalDAV getEvents: ".count($events)." événement(s) chargé(s) depuis le cache (âge: ".$cache_age."s)", LOG_DEBUG);
						return $events;
					}
				}
			}
		}
		
		dol_syslog("CalDAV getEvents: URL du calendrier = ".$url, LOG_DEBUG);
		
		// Construire la requête REPORT pour récupérer les événements
		$start_str = $start->format('Ymd\THis\Z');
		$end_str = $end->format('Ymd\THis\Z');
		
		dol_syslog("CalDAV getEvents: Période demandée de ".$start_str." à ".$end_str, LOG_DEBUG);
		
		$xml = '<?xml version="1.0" encoding="utf-8" ?>';
		$xml .= '<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">';
		$xml .= '<D:prop>';
		$xml .= '<D:getetag/>';
		$xml .= '<C:calendar-data/>';
		$xml .= '</D:prop>';
		$xml .= '<C:filter>';
		$xml .= '<C:comp-filter name="VCALENDAR">';
		$xml .= '<C:comp-filter name="VEVENT">';
		if (!$complete) {
			$xml .= '<C:time-range start="'.$start_str.'" end="'.$end_str.'"/>';
		}
		$xml .= '</C:comp-filter>';
		$xml .= '</C:comp-filter>';
		$xml .= '</C:filter>';
		$xml .= '</C:calendar-query>';
		
		dol_syslog("CalDAV getEvents: Requête XML = ".$xml, LOG_DEBUG);
		
		$headers = array(
			'Content-Type: application/xml; charset=utf-8',
			'Depth: 1'
		);
		
		$response = $this->request('REPORT', $url, $xml, $headers);
		
		dol_syslog("CalDAV getEvents: Code HTTP = ".$response['code'], LOG_DEBUG);
		dol_syslog("CalDAV getEvents: Taille de la réponse = ".strlen($response['body'])." octets", LOG_DEBUG);
		
		// Stocker les informations de débogage dans une variable globale pour la page de test
		global $caldav_debug_info;
		if (!isset($caldav_debug_info)) {
			$caldav_debug_info = array();
		}
		$caldav_debug_info[] = array(
			'url' => $url,
			'method' => 'REPORT',
			'request_xml' => $xml,
			'request_headers' => $headers,
			'response_code' => $response['code'],
			'response_body' => $response['body'],
			'response_headers' => isset($response['headers']) ? $response['headers'] : '',
			'response_size' => strlen($response['body']),
			'error' => isset($response['error']) ? $response['error'] : null
		);
		
		if ($response['code'] >= 200 && $response['code'] < 300) {
			if (empty($response['body'])) {
				dol_syslog("CalDAV getEvents: Réponse vide (code ".$response['code'].")", LOG_WARNING);
				throw new RuntimeException("CalDAV REPORT returned an empty response");
			}
			
			// Sauvegarder un extrait de la réponse pour déboguer (uniquement si beaucoup de logs activés)
			if (getDolGlobalInt('SYSLOG_LEVEL') >= LOG_DEBUG) {
				dol_syslog("CalDAV getEvents: Extrait de la réponse (500 premiers caractères) = ".substr($response['body'], 0, 500), LOG_DEBUG);
			}
			
			$events = $this->parseCalendarResponse($response['body']);
			dol_syslog("CalDAV getEvents: ".count($events)." événement(s) parsé(s)", LOG_DEBUG);
			
			// Sauvegarder dans le cache
			if ($use_cache && isset($cache_file)) {
				@file_put_contents($cache_file, serialize($events));
			}
			
			return $events;
		} else {
			dol_syslog("CalDAV getEvents: Erreur HTTP ".$response['code']." - Réponse: ".substr($response['body'], 0, 500), LOG_WARNING);
			throw new RuntimeException("CalDAV REPORT failed (HTTP ".((int) $response['code']).")");
		}
	}

	/**
	 * Parser la réponse XML du serveur CalDAV pour extraire les événements iCalendar
	 *
	 * @param  string $xml_body   Corps XML de la réponse
	 * @return array              Tableau d'événements au format iCalendar
	 */
	private function parseCalendarResponse($xml_body)
	{
		$events = array();
		
		if (empty($xml_body)) {
			dol_syslog("CalDAV parseCalendarResponse: Corps XML vide", LOG_WARNING);
			throw new RuntimeException("CalDAV REPORT returned empty XML");
		}
		
		// Parser le XML
		libxml_use_internal_errors(true);
		$xml = @simplexml_load_string($xml_body);
		
		if ($xml === false) {
			$errors = libxml_get_errors();
			$error_msg = '';
			foreach ($errors as $error) {
				$error_msg .= trim($error->message)." (ligne ".$error->line.") ";
			}
			libxml_clear_errors();
			dol_syslog("CalDAV parseCalendarResponse: Erreur de parsing XML - ".$error_msg, LOG_WARNING);
			dol_syslog("CalDAV parseCalendarResponse: Extrait XML (500 premiers caractères) = ".substr($xml_body, 0, 500), LOG_DEBUG);
			throw new RuntimeException("Invalid CalDAV REPORT XML");
		}

		$root = dom_import_simplexml($xml);
		if ($root->localName !== 'multistatus' || $root->namespaceURI !== 'DAV:') {
			throw new RuntimeException("CalDAV REPORT is not a DAV multistatus");
		}
		
		// Enregistrer les namespaces
		$xml->registerXPathNamespace('d', 'DAV:');
		$xml->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');
		
		// Chercher tous les éléments response
		$responses = $xml->xpath('//d:response');
		
		dol_syslog("CalDAV parseCalendarResponse: ".count($responses)." élément(s) response trouvé(s)", LOG_DEBUG);
		
		if (empty($responses)) {
			// Essayer sans namespace
			$responses = $xml->xpath('//response');
			dol_syslog("CalDAV parseCalendarResponse: Tentative sans namespace - ".count($responses)." élément(s) response trouvé(s)", LOG_DEBUG);
		}
		
		foreach ($responses as $response) {
			$response->registerXPathNamespace('d', 'DAV:');
			$response->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');
			
			foreach ($response->xpath('.//d:status') as $status) {
				if (!preg_match('/^HTTP\/\S+ 2\d\d(?: |$)/', trim((string) $status))) {
					throw new RuntimeException("CalDAV REPORT contains a failed resource");
				}
			}

			// Récupérer l'ETag
			$etag = '';
			$etag_nodes = $response->xpath('.//d:getetag');
			if (empty($etag_nodes)) {
				$etag_nodes = $response->xpath('.//getetag');
			}
			if (count($etag_nodes) > 0) {
				$etag = (string) $etag_nodes[0];
			}
			
			// Récupérer l'URL de la ressource (pour le débogage)
			$href = '';
			$href_nodes = $response->xpath('.//d:href');
			if (empty($href_nodes)) {
				$href_nodes = $response->xpath('.//href');
			}
			if (count($href_nodes) > 0) {
				$href = (string) $href_nodes[0];
			}
			
			// Récupérer les données du calendrier
			$calendar_data_nodes = $response->xpath('.//c:calendar-data');
			if (empty($calendar_data_nodes)) {
				$calendar_data_nodes = $response->xpath('.//calendar-data');
			}
			
			if (count($calendar_data_nodes) > 0) {
				$ical_data = (string) $calendar_data_nodes[0];
				
				dol_syslog("CalDAV parseCalendarResponse: Données iCalendar trouvées pour ".$href." (".strlen($ical_data)." octets)", LOG_DEBUG);
				
				// Parser les événements depuis les données iCalendar
				$parsed_events = $this->parseICalendar($ical_data, $etag);
				dol_syslog("CalDAV parseCalendarResponse: ".count($parsed_events)." événement(s) parsé(s) depuis ".$href, LOG_DEBUG);
				preg_match_all('/BEGIN:VEVENT/i', $ical_data, $components);
				if (count($parsed_events) !== count($components[0]) || empty($parsed_events)) {
					throw new RuntimeException("CalDAV REPORT contains unparseable events");
				}
				$events = array_merge($events, $parsed_events);
			} else {
				throw new RuntimeException("CalDAV REPORT resource has no calendar-data");
			}
		}
		
		return $events;
	}
}
