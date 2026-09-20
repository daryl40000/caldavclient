<?php
/* Copyright (C) 2025-2026 MATER Stéphane
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       htdocs/custom/caldavclient/class/caldavsync/caldavsync_support.trait.php
 * \ingroup    caldavclient
 * \brief      Conversion ActionComm ↔ tableau événement, UID, synchronisation manuelle.
 */

trait CalDAVSyncSupportTrait
{
	/**
	 * Détecter si un événement Dolibarr est un événement "automatique système".
	 *
	 * Objectif: ne jamais envoyer sur CalDAV les événements natifs (devis, signature, etc.)
	 * qui ont un type de catégorie "systemauto" (table llx_c_actioncomm.type) ou un code
	 * d'action finissant par "_AUTO" (AC_..._AUTO).
	 *
	 * @param  ActionComm $actioncomm
	 * @return bool
	 */
	private function isSystemAutoActionComm($actioncomm)
	{
		// Cas fréquent: Dolibarr expose directement le code (ex: AC_OTH_AUTO, AC_PROPAL_AUTO, etc.)
		if (!empty($actioncomm->type_code) && preg_match('/_AUTO$/', (string) $actioncomm->type_code)) {
			return true;
		}

		// Cas robuste: on vérifie la catégorie du type d'action via llx_c_actioncomm.type = 'systemauto'
		// (c'est ce qui est utilisé pour filtrer l'agenda natif).
		if (!empty($actioncomm->fk_action)) {
			$sql = "SELECT c.type";
			$sql .= " FROM ".MAIN_DB_PREFIX."c_actioncomm as c";
			$sql .= " WHERE c.id = ".((int) $actioncomm->fk_action);
			$sql .= " LIMIT 1";

			$resql = $this->db->query($sql);
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				if (!empty($obj) && isset($obj->type) && (string) $obj->type === 'systemauto') {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Convertir un événement Dolibarr en tableau pour CalDAVClient
	 *
	 * @param  ActionComm $actioncomm Événement Dolibarr
	 * @param  string     $uid        UID CalDAV
	 * @return array                  Tableau de données événement
	 */
	private function convertDolibarrToEventArray($actioncomm, $uid, $calendar = null)
	{
		// Utiliser le fuseau horaire utilisateur Dolibarr si possible.
		// Si non défini, on force un défaut France (Europe/Paris) pour éviter le fallback UTC.
		$tzid = getDolGlobalString('MAIN_DOLIBARR_USER_TIMEZONE', '');
		if (empty($tzid) || $tzid === 'UTC') {
			$tzid = 'Europe/Paris';
		}
		try {
			$tz = new DateTimeZone($tzid);
		} catch (Exception $e) {
			$tzid = 'UTC';
			$tz = new DateTimeZone('UTC');
		}

		$start_date = new DateTime('@'.((int) $actioncomm->datep));
		$start_date->setTimezone($tz);

		// Dolibarr peut stocker la date de fin dans datep2 (et mappe vers datef à la lecture).
		// On sécurise: si datef est vide ou incohérente, on force une durée minimale de 1h.
		$end_timestamp = !empty($actioncomm->datef) ? (int) $actioncomm->datef : 0;
		if (empty($end_timestamp) || $end_timestamp <= (int) $actioncomm->datep) {
			$end_timestamp = (int) $actioncomm->datep + 3600;
		}
		$end_date = new DateTime('@'.$end_timestamp);
		$end_date->setTimezone($tz);

		$event_data = array(
			'uid' => $uid,
			'summary' => $actioncomm->label,
			'description' => $actioncomm->note_private,
			'location' => $actioncomm->location,
			'start' => $start_date,
			'end' => $end_date,
			'all_day' => $actioncomm->fulldayevent ? true : false,
			'status' => 'CONFIRMED',
			'transparent' => false
		);

		// Définir l'organisateur (ORGANIZER) pour que l'utilisateur soit correct côté CalDAV.
		// Priorité:
		// - utilisateur "propriétaire" assigné au calendrier (admin > setup)
		// - sinon l'utilisateur principal détecté sur l'événement (resources/fk_user_action)
		$organizer_user_id = 0;
		if (is_object($calendar) && !empty($calendar->fk_user_owner)) {
			$organizer_user_id = (int) $calendar->fk_user_owner;
		} else {
			$organizer_user_id = $this->getMainUserIdForActionComm($actioncomm);
		}
		if ($organizer_user_id > 0) {
			require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
			$orgUser = new User($this->db);
			if ($orgUser->fetch($organizer_user_id) > 0) {
				$email = !empty($orgUser->email) ? $orgUser->email : '';
				$cn = trim(dolGetFirstLastname($orgUser->firstname, $orgUser->lastname));
				if (!empty($email)) {
					$event_data['organizer_email'] = $email;
					$event_data['organizer_cn'] = !empty($cn) ? $cn : $orgUser->login;
				}
			}
		}

		return $event_data;
	}

	/**
	 * Générer un UID CalDAV unique basé sur l'ID Dolibarr
	 *
	 * @param  int    $actioncomm_id  ID événement Dolibarr
	 * @return string                 UID CalDAV
	 */
	private function generateCalDAVUID($actioncomm_id, $calendar_id = 0)
	{
		global $conf, $user;
		
		// Format: dolibarr-ENTITY-ID[-CAL]-TIMESTAMP@domain
		$domain = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'dolibarr.local';
		$calpart = ($calendar_id > 0) ? ('-cal'.$calendar_id) : '';
		return 'dolibarr-'.$conf->entity.'-'.$actioncomm_id.$calpart.'-'.time().'@'.$domain;
	}

	/**
	 * Synchronisation manuelle (bouton dans l'interface)
	 *
	 * @param  int    $calendar_id  ID du calendrier (optionnel, tous si null)
	 * @return array                Résultats de la synchronisation
	 */
	public function manualSync($calendar_id = null)
	{
		global $conf, $user;

		$results = array(
			'success' => 0,
			'errors' => 0,
			'details' => array()
		);

		$sync_mode = !empty($conf->global->CALDAVCLIENT_SYNC_MODE) ? $conf->global->CALDAVCLIENT_SYNC_MODE : 'dolibarr_to_caldav';

		if ($calendar_id) {
			// Synchroniser un seul calendrier
			$count = $this->syncCalendar($calendar_id, $sync_mode);
			if ($count >= 0) {
				$results['success']++;
				$results['details'][] = array('calendar_id' => $calendar_id, 'count' => $count);
			} else {
				$results['errors']++;
				$results['details'][] = array('calendar_id' => $calendar_id, 'error' => $this->error);
			}
		} else {
			// Synchroniser tous les calendriers
			$result = $this->syncAllCalendars();
			if ($result >= 0) {
				// Récupérer tous les calendriers actifs
				$calendars = CalDAVCalendar::getAllActive($conf->entity);
				$results['success'] = is_array($calendars) ? count($calendars) : 0;
			} else {
				$results['errors'] = count($this->errors);
				$results['details'] = $this->errors;
			}
		}

		return $results;
	}
}
