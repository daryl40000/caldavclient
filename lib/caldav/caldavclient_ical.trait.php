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
 * \file       htdocs/custom/caldavclient/lib/caldav/caldavclient_ical.trait.php
 * \ingroup    caldavclient
 * \brief      Lecture et écriture des données iCalendar (VEVENT).
 */

trait CalDAVClientICalTrait
{
	/**
	 * Parser les données iCalendar pour extraire les événements
	 *
	 * @param  string $ical_data   Données iCalendar
	 * @param  string $etag        ETag de la ressource
	 * @return array               Tableau d'événements
	 */
	private function parseICalendar($ical_data, $etag)
	{
		$events = array();
		
		if (empty($ical_data)) {
			dol_syslog("CalDAV parseICalendar: Données iCalendar vides", LOG_WARNING);
			return $events;
		}
		
	// Décoder les données si elles sont encodées (CDATA dans XML)
	$ical_data = html_entity_decode($ical_data, ENT_QUOTES | ENT_XML1, 'UTF-8');
	
	// Extraire les composants VEVENT complets (entre BEGIN:VEVENT et END:VEVENT)
	preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/is', $ical_data, $matches);
	$vevents = $matches[0];
	
	$event_count = count($vevents);
	if ($event_count > 0) {
		dol_syslog("CalDAV parseICalendar: ".$event_count." composant(s) VEVENT complet(s) trouvé(s)", LOG_DEBUG);
	}
	
	foreach ($vevents as $index => $vevent) {
		if (empty($vevent)) {
			continue;
		}
		
		// Normaliser le VEVENT : gérer les lignes continuées et supprimer les VALARM en une seule passe
		$vevent_normalized = preg_replace([
			'/\r\n[ \t]/',                      // Lignes continuées (CRLF + espace/tab)
			'/\n[ \t]/',                        // Lignes continuées (LF + espace/tab)
			'/BEGIN:VALARM.*?END:VALARM/is'    // Composants VALARM (rappels)
		], '', $vevent);
		
		$event = array(
			'etag' => $etag,
			'uid' => '',
			'summary' => '',
			'description' => '',
			'start' => null,
			'end' => null,
			'location' => '',
			'url' => ''
		);
		
		if (preg_match('/UID[:\s]+(.*?)(?:\r\n|\n|$)/i', $vevent_normalized, $matches)) {
			$event['uid'] = trim($matches[1]);
		}
		if (preg_match('/SUMMARY[:\s]+(.*?)(?:\r\n|\n|$)/i', $vevent_normalized, $matches)) {
			$event['summary'] = trim($matches[1]);
		}
		if (preg_match('/DESCRIPTION[:\s]+(.*?)(?:\r\n|\n|$)/i', $vevent_normalized, $matches)) {
			$event['description'] = trim($matches[1]);
		}
		if (preg_match('/LOCATION[:\s]+(.*?)(?:\r\n|\n|$)/i', $vevent_normalized, $matches)) {
			$event['location'] = trim($matches[1]);
		}
		if (preg_match('/URL[:\s]+(.*?)(?:\r\n|\n|$)/i', $vevent_normalized, $matches)) {
			$event['url'] = trim($matches[1]);
		}
			
			// Extraire les dates (gérer les paramètres comme VALUE=DATE)
			if (preg_match('/DTSTART(?:;.*?)?[:\s]+([^\r\n]+)/i', $vevent_normalized, $matches)) {
				$date_str = trim($matches[1]);
				$event['start'] = $this->parseICalendarDate($date_str);
				if (!$event['start']) {
					dol_syslog("CalDAV parseICalendar: Impossible de parser DTSTART: ".$date_str, LOG_WARNING);
				}
			}
			if (preg_match('/DTEND(?:;.*?)?[:\s]+([^\r\n]+)/i', $vevent_normalized, $matches)) {
				$date_str = trim($matches[1]);
				$event['end'] = $this->parseICalendarDate($date_str);
				if (!$event['end']) {
					dol_syslog("CalDAV parseICalendar: Impossible de parser DTEND: ".$date_str, LOG_WARNING);
				}
			}
			
		// Validation: un événement doit avoir au minimum un UID et une date de début
		// On ignore les événements sans titre ET sans date de début (probablement des fragments mal parsés)
		if (!empty($event['uid']) && $event['start'] !== null) {
			$events[] = $event;
		} elseif (getDolGlobalInt('SYSLOG_LEVEL') >= LOG_WARNING) {
			// Logs de débogage uniquement si le niveau de log est élevé
			if (empty($event['uid'])) {
				dol_syslog("CalDAV parseICalendar: Événement ignoré (pas d'UID)", LOG_WARNING);
			} else {
				dol_syslog("CalDAV parseICalendar: Événement ignoré (pas de date de début valide) - UID: ".$event['uid'], LOG_WARNING);
			}
		}
		}
		
		return $events;
	}

	/**
	 * Parser une date au format iCalendar
	 *
	 * @param  string $date_str   Chaîne de date iCalendar
	 * @return DateTime|null      Objet DateTime ou null
	 */
	private function parseICalendarDate($date_str)
	{
		// Format de base: YYYYMMDDTHHMMSSZ ou YYYYMMDD
		if (preg_match('/^(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2})(\d{2})(Z)?)?$/', $date_str, $matches)) {
			$year = (int) $matches[1];
			$month = (int) $matches[2];
			$day = (int) $matches[3];
			$hour = isset($matches[4]) ? (int) $matches[4] : 0;
			$minute = isset($matches[5]) ? (int) $matches[5] : 0;
			$second = isset($matches[6]) ? (int) $matches[6] : 0;
			$is_utc = isset($matches[7]) && $matches[7] == 'Z';
			
			try {
				$date = new DateTime();
				$date->setDate($year, $month, $day);
				$date->setTime($hour, $minute, $second);
				if ($is_utc) {
					$date->setTimezone(new DateTimeZone('UTC'));
				}
				return $date;
			} catch (Exception $e) {
				return null;
			}
		}
		
		return null;
	}
}
