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
	/** Parse VEVENTs; DATE boundaries use UTC midnight and DTEND stays exclusive. */
	private function parseICalendar($ical_data, $etag)
	{
		$events = array();
		// XML decoding belongs to the XML reader, never to the embedded TEXT values.
		$ical_data = preg_replace('/\r?\n[ \t]/', '', $ical_data);
		preg_match_all('/^BEGIN:VEVENT\r?\n.*?^END:VEVENT\r?$/msi', $ical_data, $matches);
		foreach ($matches[0] as $vevent) {
			$event = array('etag' => $etag, 'uid' => '', 'summary' => '', 'description' => '',
				'location' => '', 'url' => '', 'start' => null, 'end' => null, 'all_day' => false,
				'last_modified' => null, 'raw_ical' => $vevent, 'unsupported_recurrence' => false);
			$properties = array();
			$componentDepth = 0;
			foreach (preg_split('/\r?\n/', $vevent) as $line) {
				if (stripos($line, 'BEGIN:') === 0) {
					$componentDepth++;
					continue;
				}
				if (stripos($line, 'END:') === 0) {
					$componentDepth--;
					continue;
				}
				if ($componentDepth !== 1 || !preg_match('/^([A-Z-]+)((?:;[^:]*)?):(.*)$/i', $line, $property)) {
					continue;
				}
				$name = strtoupper($property[1]);
				$properties[$name] = array($property[2], $property[3]);
			}
			foreach (array('UID' => 'uid', 'SUMMARY' => 'summary', 'DESCRIPTION' => 'description', 'LOCATION' => 'location', 'URL' => 'url') as $name => $key) {
				if (isset($properties[$name])) {
					$event[$key] = preg_replace_callback('/\\\\([nN,;\\\\])/', static function ($m) {
						return strtolower($m[1]) === 'n' ? "\n" : $m[1];
					}, $properties[$name][1]);
				}
			}
			$event['unsupported_recurrence'] = isset($properties['RRULE']) || isset($properties['RDATE']) || isset($properties['EXDATE']) || isset($properties['RECURRENCE-ID']);
			foreach (array('DTSTART' => 'start', 'DTEND' => 'end', 'LAST-MODIFIED' => 'last_modified') as $name => $key) {
				if (isset($properties[$name])) {
					$event[$key] = $this->parseICalendarDate($properties[$name][1], $properties[$name][0]);
				}
			}
			if (!$event['start'] || $event['uid'] === '') {
				throw new UnexpectedValueException('Invalid CalDAV VEVENT UID or DTSTART');
			}
			$event['all_day'] = preg_match('/^\d{8}$/', $properties['DTSTART'][1]) === 1;
			if (isset($properties['DTEND']) && (!$event['end'] || $event['all_day'] !== (preg_match('/^\d{8}$/', $properties['DTEND'][1]) === 1))) {
				throw new UnexpectedValueException('Invalid CalDAV DTEND');
			}
			if (!$event['end']) {
				$event['end'] = clone $event['start'];
				if ($event['all_day']) {
					$event['end']->modify('+1 day');
				}
				if (isset($properties['DURATION'])) {
					throw new UnexpectedValueException('CalDAV DURATION is not supported');
				}
			}
			if ($event['end'] < $event['start'] || ($event['all_day'] && $event['end'] == $event['start'])) {
				throw new UnexpectedValueException('CalDAV DTEND precedes DTSTART');
			}
			$events[] = $event;
		}
		return $events;
	}

	/** Parse without PHP date normalization or dependence on the process timezone. */
	private function parseICalendarDate($date_str, $parameters = '')
	{
		$isDate = preg_match('/^\d{8}$/', $date_str) === 1;
		if (!$isDate && !preg_match('/^\d{8}T\d{6}Z?$/', $date_str)) {
			return null;
		}
		if (preg_match('/;VALUE=([^;]+)/i', $parameters, $valueType) && strtoupper($valueType[1]) !== ($isDate ? 'DATE' : 'DATE-TIME')) {
			return null;
		}
		$tzid = getDolGlobalString('MAIN_DOLIBARR_USER_TIMEZONE', '') ?: date_default_timezone_get();
		if ($isDate || substr($date_str, -1) === 'Z') {
			$tzid = 'UTC';
		} elseif (preg_match('/;TZID=("[^"]+"|[^;]+)/i', $parameters, $zone)) {
			$tzid = trim($zone[1], '"');
		}
		try {
			$format = $isDate ? 'Ymd' : 'Ymd\\THis'.(substr($date_str, -1) === 'Z' ? '\\Z' : '');
			$date = DateTime::createFromFormat('!'.$format, $date_str, new DateTimeZone($tzid));
			$errors = DateTime::getLastErrors();
			return $date && (!$errors || (!$errors['warning_count'] && !$errors['error_count'])) && $date->format($format) === $date_str ? $date : null;
		} catch (Exception $e) {
			return null;
		}
	}
}
