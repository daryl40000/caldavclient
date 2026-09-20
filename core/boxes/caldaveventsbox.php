<?php
/* Copyright (C) 2025-2026 MATER Stéphane
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
 * \file       htdocs/custom/caldavclient/core/boxes/caldaveventsbox.php
 * \ingroup    caldavclient
 * \brief      Widget pour afficher les événements CalDAV à venir
 */

require_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

/**
 * Classe pour gérer le widget des événements CalDAV
 */
class caldaveventsbox extends ModeleBoxes
{
	public $boxcode = "caldavevents";
	public $boximg = "object_calendar";
	public $boxlabel = "CalDAVEventsBox";
	public $depends = array("caldavclient");

	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Info box title
	 */
	public $info_box_head = array();

	/**
	 * @var array Info box contents
	 */
	public $info_box_contents = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db      Database handler
	 * @param string $param   More parameters
	 */
	public function __construct($db, $param = '')
	{
		global $user, $conf, $langs;

		$langs->load("caldavclient@caldavclient");

		$this->db = $db;

		$this->boxcode = "caldavevents";
		$this->boximg = "object_calendar";
		$this->boxlabel = $langs->trans("CalDAVUpcomingEvents");
		$this->hidden = false;

		$this->depends = array("caldavclient");

		$this->enabled = 1;
	}

	/**
	 * Load data into info_box_contents array to show array later
	 *
	 * @param  int    $max  Maximum number of records to load
	 * @return void
	 */
	public function loadBox($max = 5)
	{
		global $user, $langs, $conf, $db;

		$this->max = $max;

		$langs->load("caldavclient@caldavclient");

		// En-tête du widget
		$this->info_box_head = array(
			'text' => $langs->trans("CalDAVUpcomingEvents").' ('.$langs->trans("TodayAndTomorrow").')',
			'limit' => dol_strlen($this->boxlabel),
		);

		// Vérifier les permissions
		if (empty($user->rights->caldavclient) || empty($user->rights->caldavclient->read)) {
			$this->info_box_contents[0][0] = array(
				'td' => 'class="center"',
				'text' => $langs->trans("ReadPermissionNotAllowed")
			);
			return;
		}

		// Charger les classes nécessaires
		dol_include_once('/caldavclient/class/caldavclient.class.php');
		dol_include_once('/caldavclient/class/caldavcalendar.class.php');
		dol_include_once('/caldavclient/lib/caldav.lib.php');

		// Protection contre les erreurs fatales
		try {
			// Récupérer les calendriers CalDAV accessibles par l'utilisateur
			$calendar = new CalDAVCalendar($db);
			$calendars = $calendar->getAllActive($conf->entity, $user->id);

			if (empty($calendars)) {
				$this->info_box_contents[0][0] = array(
					'td' => 'class="center opacitymedium"',
					'text' => $langs->trans("NoCalDAVCalendarConfigured")
				);
				return;
			}

			// Dates pour aujourd'hui et demain
			$date_start = dol_now();
			$date_end = dol_mktime(23, 59, 59, date('m'), date('d') + 1, date('Y')); // Fin de demain

			// Récupérer tous les événements
			$all_events = array();

			foreach ($calendars as $cal) {
				// Vérifier que le calendrier est activé
				if (!$cal->enabled) {
					continue;
				}

				// Charger la connexion
				$connection = new CalDAVConnection($db);
				$result = $connection->fetch($cal->fk_connection);

				if ($result <= 0 || !$connection->enabled) {
					continue;
				}

				try {
					// Créer le client CalDAV
					$client = new CalDAVClient($connection);

					// Récupérer les événements
					$events = $client->getEvents(
						date('Y-m-d', $date_start),
						date('Y-m-d', $date_end + 86400) // +1 jour pour être sûr
					);

					// Ajouter les métadonnées du calendrier à chaque événement
					foreach ($events as &$event) {
						$event['calendar_name'] = $cal->displayname ? $cal->displayname : $cal->name;
						$event['calendar_color'] = $cal->color;
						$event['connection_name'] = $connection->name;
					}

					$all_events = array_merge($all_events, $events);

				} catch (Exception $e) {
					dol_syslog("CalDAV Widget: Erreur lors de la récupération des événements pour le calendrier ".$cal->name.": ".$e->getMessage(), LOG_WARNING);
					continue;
				}
			}

			// Filtrer les événements pour ne garder que ceux d'aujourd'hui et demain
			$filtered_events = array();
			$now = dol_now();
			$today_start = dol_mktime(0, 0, 0, date('m'), date('d'), date('Y'));
			$tomorrow_end = dol_mktime(23, 59, 59, date('m'), date('d') + 1, date('Y'));

			foreach ($all_events as $event) {
				$event_start = $event['start']->getTimestamp();
				
				// Garder les événements qui commencent entre aujourd'hui et demain
				// OU les événements en cours (commencés avant mais pas encore terminés)
				if ($event_start <= $tomorrow_end) {
					if (isset($event['end']) && $event['end']) {
						$event_end = $event['end']->getTimestamp();
						// Événement pas encore terminé
						if ($event_end >= $now) {
							$filtered_events[] = $event;
						}
					} else {
						// Pas de date de fin, on garde si c'est aujourd'hui ou demain
						if ($event_start >= $today_start && $event_start <= $tomorrow_end) {
							$filtered_events[] = $event;
						}
					}
				}
			}

			// Trier les événements par date de début
			usort($filtered_events, function($a, $b) {
				return $a['start']->getTimestamp() - $b['start']->getTimestamp();
			});

			// Limiter le nombre d'événements affichés
			$filtered_events = array_slice($filtered_events, 0, $max);

			// Afficher les événements
			if (empty($filtered_events)) {
				$this->info_box_contents[0][0] = array(
					'td' => 'class="center opacitymedium"',
					'text' => $langs->trans("NoCalDAVEventsUpcoming")
				);
			} else {
				$line = 0;
				foreach ($filtered_events as $event) {
					// Déterminer si c'est aujourd'hui ou demain
					$event_start = $event['start']->getTimestamp();
					$is_today = (date('Y-m-d', $event_start) == date('Y-m-d', $now));
					$day_label = $is_today ? $langs->trans("Today") : $langs->trans("Tomorrow");

					// Formater l'heure
					$time_str = '';
					if (!empty($event['all_day'])) {
						$time_str = $langs->trans("AllDay");
					} else {
						$time_str = dol_print_date($event_start, 'hour');
						if (isset($event['end']) && $event['end']) {
							$time_str .= ' - '.dol_print_date($event['end']->getTimestamp(), 'hour');
						}
					}

					// Couleur du calendrier
					$color = !empty($event['calendar_color']) ? $event['calendar_color'] : '#999999';

					// Ligne du widget
					$this->info_box_contents[$line][] = array(
						'td' => 'class="tdoverflowmax100"',
						'text' => '<span style="display:inline-block;width:10px;height:10px;background-color:'.$color.';border-radius:2px;margin-right:5px;"></span>'.
						          '<strong>'.$day_label.'</strong>',
						'tooltip' => $event['connection_name'].' - '.$event['calendar_name']
					);

					$this->info_box_contents[$line][] = array(
						'td' => 'class="tdoverflowmax100"',
						'text' => $time_str
					);

					$this->info_box_contents[$line][] = array(
						'td' => 'class="tdoverflowmax200"',
						'text' => dol_escape_htmltag($event['summary']),
						'tooltip' => $event['summary'].
						            (!empty($event['location']) ? "\n".$langs->trans("Location").': '.$event['location'] : '').
						            (!empty($event['description']) ? "\n".$event['description'] : '')
					);

					$line++;
				}
			}
		} catch (Exception $e) {
			// En cas d'erreur fatale, afficher un message d'erreur dans le widget
			dol_syslog("CalDAV Widget: Erreur fatale: ".$e->getMessage(), LOG_ERR);
			$this->info_box_contents[0][0] = array(
				'td' => 'class="center warning"',
				'text' => 'Erreur lors du chargement des événements CalDAV'
			);
			return;
		}
	}

	/**
	 * Method to show box
	 *
	 * @param  array  $head     Array with properties of box title
	 * @param  array  $contents Array with properties of box lines
	 * @param  int    $nooutput No print, only return string
	 * @return string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
