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
 * \file       class/agenda/caldavclient_agenda_native_events.class.php
 * \ingroup    caldavclient
 * \brief      Hook getCalendarEvents — agenda classique comm/action (hors FullCalendar).
 */

require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavcalendar.class.php';

/**
 * Fusionne les événements distants dans le tableau agenda natif via $hookmanager->resArray.
 */
class CaldavclientAgendaNativeEvents
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
	 * @param  HookManager $hookmanager Pour resArray['eventarray']
	 * @return int                      0
	 */
	public function mergeIntoHookEventArray($hookmanager)
	{
		global $conf, $user, $langs;

		$all_calendars = CalDAVCalendar::getAllActive();

		$calendars = array();
		foreach ($all_calendars as $calendar) {
			$htmlname = md5('caldav_'.$calendar->url);
			$checkbox_name = 'check_ext'.$htmlname;
			$is_checked = GETPOST($checkbox_name, 'alpha');

			$should_include = ($is_checked === '') ? $calendar->active_by_default : ($is_checked == '1' || $is_checked === true);

			if ($should_include) {
				$calendars[] = $calendar;
			}
		}

		if (count($calendars) == 0) {
			return 0;
		}

		dol_syslog("CalDAV: ".count($calendars)." calendrier(s) sélectionné(s) pour l'agenda classique", LOG_DEBUG);

		$eventarray = array();
		$total_events = 0;

		foreach ($calendars as $calendar) {
			try {
				$connection = new CalDAVConnection($this->db);
				$connection->fetch($calendar->fk_connection);

				if (!$connection->enabled) {
					continue;
				}

				$client = new CalDAVClient($connection);

				$start = new DateTime();
				$start->modify('-1 month');
				$start->setTimezone(new DateTimeZone('UTC'));

				$end = new DateTime();
				$end->modify('+3 months');
				$end->setTimezone(new DateTimeZone('UTC'));

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
					if (strpos($color, '#') === 0) {
						$color = substr($color, 1);
					}

					$date_start_obj = new DateTime();
					$date_start_obj->setTimestamp($date_start_ts);
					$annee = (int) $date_start_obj->format('Y');
					$mois = (int) $date_start_obj->format('m');
					$jour = (int) $date_start_obj->format('d');
					$date_key = dol_mktime(0, 0, 0, $mois, $jour, $annee, 'gmt');

					if (!isset($eventarray[$date_key])) {
						$eventarray[$date_key] = array();
					}

					$actionComm = new stdClass();
					$actionComm->id = 'caldav_'.$calendar->id.'_'.$event['uid'];
					$actionComm->label = $event['summary'] ? $event['summary'] : $langs->trans("UntitledEvent");
					$actionComm->datep = $date_start_ts;
					$actionComm->datep2 = $date_end_ts;
					$actionComm->fulldayevent = $fulldayevent;
					$actionComm->type_code = 'CALDAV';
					$actionComm->type_label = $connection->name.' - '.($calendar->displayname ? $calendar->displayname : $calendar->name);
					$actionComm->type_color = $color;
					$actionComm->type_picto = 'rss';
					$actionComm->type = 'icalevent';
					$actionComm->percent = -1;
					$actionComm->note = $event['description'] ? $event['description'] : '';
					$actionComm->location = $event['location'] ? $event['location'] : '';
					$actionComm->socid = 0;
					$actionComm->socname = '';
					$actionComm->contactid = 0;
					$actionComm->contactname = '';
					$actionComm->userid = 0;
					$actionComm->username = '';

					$actionComm->userassigned = array($user->id => array('id' => $user->id, 'transparency' => 0));

					$actionComm->projectid = 0;
					$actionComm->projectname = '';

					$actionComm->icalname = 'caldav_'.$calendar->url;

					$eventarray[$date_key][] = $actionComm;
					$total_events++;
				}
			} catch (Exception $e) {
				dol_syslog("Erreur lors de la récupération des événements CalDAV pour le calendrier ".$calendar->name.": ".$e->getMessage(), LOG_WARNING);
			}
		}

		if (!empty($eventarray)) {
			$hookmanager->resArray['eventarray'] = $eventarray;
			dol_syslog("CalDAV: ".count($eventarray)." jour(s) avec événements ajouté(s) via hookmanager->resArray", LOG_DEBUG);
		}

		dol_syslog("CalDAV: Total de ".$total_events." événement(s) ajouté(s) à l'agenda classique", LOG_DEBUG);

		return 0;
	}
}
