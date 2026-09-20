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
 * \file       class/agenda/caldavclient_agenda_calendar_js_hook.class.php
 * \ingroup    caldavclient
 * \brief      Hook Dolibarr updateFullcalendarEvents : fusion des événements CalDAV dans le tableau $TEvent
 *            (vue agenda JavaScript du cœur — contexte fullcalendarinterface). Nom volontairement distinct du
 *            module tiers « fullcalendar » pour éviter toute confusion.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavcalendar.class.php';

/**
 * Alimente le tableau $TEvent de la vue agenda JS Dolibarr (hook updateFullcalendarEvents).
 */
class CaldavclientAgendaCalendarJsHook
{
	/** @var DoliDB */
	public $db;

	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Ajoute les événements CalDAV à $TEvent (référence).
	 *
	 * @param  array $TEvent Référence sur le tableau d'événements (vue agenda JS Dolibarr)
	 * @return int            0
	 */
	public function appendCalDavEventsToTEvent(&$TEvent)
	{
		global $langs;

		$date_start = GETPOST('start', 'none');
		$date_end = GETPOST('end', 'none');

		if (empty($date_start) || empty($date_end)) {
			dol_syslog("CalDAV: Dates non définies (date_start=".$date_start.", date_end=".$date_end.")", LOG_DEBUG);
			return 0;
		}

		$t_start = strtotime($date_start);
		$t_end = strtotime($date_end);

		dol_syslog("CalDAV: Récupération des événements pour la période ".date('Y-m-d H:i:s', $t_start)." à ".date('Y-m-d H:i:s', $t_end), LOG_DEBUG);

		$start = new DateTime();
		$start->setTimestamp($t_start);
		$start->setTimezone(new DateTimeZone('UTC'));

		$end = new DateTime();
		$end->setTimestamp($t_end);
		$end->setTimezone(new DateTimeZone('UTC'));

		$all_calendars = CalDAVCalendar::getAllActive();

		$has_any_checkbox = false;
		foreach ($all_calendars as $cal) {
			$htmlname = md5('caldav_'.$cal->url);
			$checkbox_name = 'check_ext'.$htmlname;
			if (GETPOST($checkbox_name, 'alpha') !== '') {
				$has_any_checkbox = true;
				break;
			}
		}

		$calendars = array();
		foreach ($all_calendars as $calendar) {
			$htmlname = md5('caldav_'.$calendar->url);
			$checkbox_name = 'check_ext'.$htmlname;
			$is_checked = GETPOST($checkbox_name, 'alpha');

			if (!$has_any_checkbox) {
				$should_include = true;
			} elseif ($is_checked === '') {
				$should_include = $calendar->active_by_default;
			} else {
				$should_include = ($is_checked == '1' || $is_checked === true);
			}

			if ($should_include) {
				$calendars[] = $calendar;
			}
		}

		if (count($calendars) == 0) {
			return 0;
		}

		dol_syslog("CalDAV: ".count($calendars)." calendrier(s) sélectionné(s)", LOG_DEBUG);

		if (!is_array($TEvent)) {
			$TEvent = array();
		}

		$total_events = 0;
		$caldavEvents = array();

		foreach ($calendars as $calendar) {
			try {
				$connection = new CalDAVConnection($this->db);
				$connection->fetch($calendar->fk_connection);

				if (!$connection->enabled) {
					continue;
				}

				$client = new CalDAVClient($connection);

				$events = $client->getEvents($calendar->url, $start, $end, true);

				foreach ($events as $event) {
					$date_start_ts = $event['start'] ? $event['start']->getTimestamp() : time();
					$date_end_ts = $event['end'] ? $event['end']->getTimestamp() : ($event['start'] ? $event['start']->getTimestamp() + 3600 : time() + 3600);

					$fulldayevent = 0;
					if ($event['start'] && $event['end']) {
						$duration = $date_end_ts - $date_start_ts;
						if ($duration >= 86400 && ($duration % 86400) < 3600) {
							$fulldayevent = 1;
						}
					}

					$color = $calendar->color ? $calendar->color : ($connection->color ? $connection->color : 'BECEDD');
					if (strpos($color, '#') !== 0) {
						$color = '#'.$color;
					}

					$isDark = false;
					if (function_exists('isDarkColor')) {
						$isDark = isDarkColor($color);
					}

					$startDateString = dol_print_date($date_start_ts, $fulldayevent ? '%Y-%m-%d' : '%Y-%m-%d %H:%M:%S', 'tzuser');
					$endDateString = dol_print_date($date_end_ts, $fulldayevent ? '%Y-%m-%d' : '%Y-%m-%d %H:%M:%S', 'tzuser');

					if ($fulldayevent && !empty($endDateString)) {
						$dtEndDate = new DateTime($endDateString);
						$dtEndDate->add(new DateInterval('P1D'));
						$endDateString = $dtEndDate->format('Y-m-d');
					}

					$tmpEvent = array(
						'id' => 'caldav_'.$calendar->id.'_'.$event['uid'],
						'title' => $event['summary'] ? $event['summary'] : $langs->trans("UntitledEvent"),
						'allDay' => (bool) $fulldayevent,
						'start' => $startDateString,
						'end' => $endDateString,
						'url_title' => '',
						'editable' => false,
						'color' => $color,
						'isDarkColor' => $isDark,
						'colors' => '',
						'note' => $event['description'] ? $event['description'] : '',
						'statut' => '',
						'fk_soc' => 0,
						'fk_contact' => 0,
						'fk_user' => 0,
						'TFk_user' => array(),
						'fk_project' => 0,
						'societe' => '',
						'contact' => '',
						'user' => '',
						'project' => '',
						'type_code' => 'CALDAV',
						'percentage' => -1,
						'project_order' => '',
						'fk_project_order' => '0',
						'splitedfulldayevent' => 0,
						'fulldayevent' => $fulldayevent,
						'more' => $connection->name.' - '.($calendar->displayname ? $calendar->displayname : $calendar->name),
						'location' => $event['location'] ? $event['location'] : '',
						'caldav_connection_id' => $connection->id,
						'caldav_calendar_id' => $calendar->id,
						'caldav_uid' => $event['uid'],
						'caldav_etag' => $event['etag'] ?? '',
						'caldav_url' => $event['url'] ?? ''
					);

					$caldavEvents[] = $tmpEvent;

					dol_syslog("CalDAV: Événement ajouté - ID: ".$tmpEvent['id'].", Titre: ".$tmpEvent['title'].", Start: ".$tmpEvent['start'].", End: ".$tmpEvent['end'], LOG_DEBUG);

					$total_events++;
				}
			} catch (Exception $e) {
				dol_syslog("Erreur lors de la récupération des événements CalDAV pour le calendrier ".$calendar->name.": ".$e->getMessage(), LOG_WARNING);
			}
		}

		foreach ($caldavEvents as $event) {
			$TEvent[] = $event;
		}

		dol_syslog("CalDAV: Total de ".$total_events." événement(s) ajouté(s) au tableau TEvent (vue agenda JS Dolibarr)", LOG_DEBUG);

		return 0;
	}
}
