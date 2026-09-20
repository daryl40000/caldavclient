<?php
/* Copyright (C) 2025-2026 MATER Stéphane
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       htdocs/custom/caldavclient/class/caldavsync/caldavsync_inbound.trait.php
 * \ingroup    caldavclient
 * \brief      Sync CalDAV → Dolibarr, bidirectionnel simple, CRUD événements et suppressions.
 */

trait CalDAVSyncInboundTrait
{
	/**
	 * Synchroniser CalDAV → Dolibarr (CalDAV est maître)
	 *
	 * @param  CalDAVCalendar   $calendar   Calendrier CalDAV
	 * @param  CalDAVConnection $connection Connexion CalDAV
	 * @return int                          Nombre d'événements synchronisés, <0 si erreur
	 */
	private function syncCalDAVToDolibarr($calendar, $connection)
	{
		global $user, $conf;

		dol_syslog("CalDAVSync::syncCalDAVToDolibarr - Début pour calendrier ".$calendar->name, LOG_DEBUG);

		// Créer le client CalDAV
		$client = new CalDAVClient($connection);

		// Récupérer les événements CalDAV (6 mois passés → 12 mois futurs)
		$start = new DateTime();
		$start->modify('-6 months');
		$end = new DateTime();
		$end->modify('+12 months');

		try {
			$events = $client->getEvents($calendar->url, $start, $end, false); // Pas de cache pour la sync
		} catch (Exception $e) {
			$this->error = $e->getMessage();
			dol_syslog("CalDAVSync::syncCalDAVToDolibarr - Erreur: ".$this->error, LOG_ERR);
			return -1;
		}

		dol_syslog("CalDAVSync::syncCalDAVToDolibarr - ".count($events)." événement(s) CalDAV récupéré(s)", LOG_DEBUG);

		$synced_count = 0;

		foreach ($events as $event) {
			// Vérifier si l'événement existe déjà dans le mapping
			$mapping = new CalDAVEventMapping($this->db);
			$mapping_exists = $mapping->fetchByCalDAVUID($event['uid'], $calendar->id);

			if ($mapping_exists > 0) {
				// Mise à jour d'un événement existant
				$result = $this->updateEventInDolibarr($event, $mapping, $calendar);
			} else {
				// Création d'un nouvel événement
				$result = $this->createEventInDolibarr($event, $calendar);
			}

			if ($result > 0) {
				$synced_count++;
			}
		}

		// Gérer les suppressions (événements dans Dolibarr mais plus sur CalDAV)
		$deleted_count = $this->handleDeletedCalDAVEvents($calendar, $events);
		
		dol_syslog("CalDAVSync::syncCalDAVToDolibarr - Terminé: ".$synced_count." synchronisé(s), ".$deleted_count." supprimé(s)", LOG_INFO);

		return $synced_count;
	}

	/**
	 * Synchronisation bidirectionnelle (le dernier modifié gagne)
	 *
	 * @param  CalDAVCalendar   $calendar   Calendrier CalDAV
	 * @param  CalDAVConnection $connection Connexion CalDAV
	 * @return int                          Nombre d'événements synchronisés, <0 si erreur
	 */
	private function syncBidirectional($calendar, $connection)
	{
		dol_syslog("CalDAVSync::syncBidirectional - Début pour calendrier ".$calendar->name, LOG_DEBUG);

		// En mode bidirectionnel, on fait les deux syncs en comparant les dates de modification
		$dolibarr_count = $this->syncDolibarrToCalDAV($calendar, $connection);
		$caldav_count = $this->syncCalDAVToDolibarr($calendar, $connection);

		$total = 0;
		if ($dolibarr_count >= 0) $total += $dolibarr_count;
		if ($caldav_count >= 0) $total += $caldav_count;

		dol_syslog("CalDAVSync::syncBidirectional - Terminé: ".$total." événement(s) synchronisé(s)", LOG_INFO);

		return ($dolibarr_count < 0 || $caldav_count < 0) ? -1 : $total;
	}

	/**
	 * Créer un événement sur CalDAV depuis Dolibarr
	 *
	 * @param  ActionComm       $actioncomm Événement Dolibarr
	 * @param  CalDAVCalendar   $calendar   Calendrier CalDAV
	 * @param  CalDAVClient     $client     Client CalDAV
	 * @return int                          >0 si OK, <0 si KO
	 */
	private function createEventOnCalDAV($actioncomm, $calendar, $client, $forced_uid = null)
	{
		dol_syslog("CalDAVSync::createEventOnCalDAV - Événement Dolibarr ID ".$actioncomm->id, LOG_DEBUG);

		// Générer un UID CalDAV unique (ou utiliser celui fourni)
		$caldav_uid = !empty($forced_uid) ? $forced_uid : $this->generateCalDAVUID($actioncomm->id, !empty($calendar->id) ? (int) $calendar->id : 0);

		// Convertir ActionComm en tableau pour CalDAVClient
		$event_data = $this->convertDolibarrToEventArray($actioncomm, $caldav_uid, $calendar);

		if (!$event_data) {
			$this->error = "Impossible de convertir l'événement";
			return -1;
		}

		try {
			// Créer l'événement sur CalDAV
			$res = $client->createEventOnCalendar($calendar->url, $event_data);
			$result = (!empty($res['success']));

			if ($result) {
				// Créer le mapping
				$mapping = new CalDAVEventMapping($this->db);
				$mapping_result = $mapping->create($actioncomm->id, $caldav_uid, $calendar->id, 'dolibarr', !empty($res['etag']) ? $res['etag'] : null);

				if ($mapping_result > 0) {
					dol_syslog("CalDAVSync::createEventOnCalDAV - Événement créé et mappé avec succès", LOG_INFO);
					return 1;
				} else {
					$this->error = "Événement créé mais mapping échoué: ".$mapping->error;
					return -1;
				}
			} else {
				$code = isset($res['code']) ? (int) $res['code'] : 0;
				$url = !empty($res['url']) ? $res['url'] : '';
				$this->error = "Échec de création sur CalDAV (HTTP ".$code.") ".$url;
				return -1;
			}
		} catch (Exception $e) {
			$this->error = $e->getMessage();
			dol_syslog("CalDAVSync::createEventOnCalDAV - Erreur: ".$this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 * Mettre à jour un événement sur CalDAV depuis Dolibarr
	 *
	 * @param  ActionComm          $actioncomm Événement Dolibarr
	 * @param  CalDAVEventMapping  $mapping    Mapping existant
	 * @param  CalDAVCalendar      $calendar   Calendrier CalDAV
	 * @param  CalDAVClient        $client     Client CalDAV
	 * @return int                             >0 si OK, <0 si KO
	 */
	private function updateEventOnCalDAV($actioncomm, $mapping, $calendar, $client)
	{
		dol_syslog("CalDAVSync::updateEventOnCalDAV - Événement Dolibarr ID ".$actioncomm->id.", UID ".$mapping->caldav_uid, LOG_DEBUG);

		// Convertir ActionComm en tableau pour CalDAVClient
		$event_data = $this->convertDolibarrToEventArray($actioncomm, $mapping->caldav_uid, $calendar);

		if (!$event_data) {
			$this->error = "Impossible de convertir l'événement";
			return -1;
		}

		try {
			// Mettre à jour l'événement sur CalDAV
			$etag = !empty($mapping->caldav_etag) ? $mapping->caldav_etag : null;
			$res = $client->updateEventOnCalendar($calendar->url, $mapping->caldav_uid, $etag, $event_data);
			$result = (!empty($res['success']));

			if ($result) {
				// Mettre à jour le mapping
				$mapping->update('dolibarr', !empty($res['etag']) ? $res['etag'] : null);
				dol_syslog("CalDAVSync::updateEventOnCalDAV - Événement mis à jour avec succès", LOG_INFO);
				return 1;
			} else {
				$code = isset($res['code']) ? (int) $res['code'] : 0;
				$url = !empty($res['url']) ? $res['url'] : '';
				$this->error = "Échec de mise à jour sur CalDAV (HTTP ".$code.") ".$url;
				return -1;
			}
		} catch (Exception $e) {
			$this->error = $e->getMessage();
			dol_syslog("CalDAVSync::updateEventOnCalDAV - Erreur: ".$this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 * Créer un événement dans Dolibarr depuis CalDAV
	 *
	 * @param  array          $event    Événement CalDAV
	 * @param  CalDAVCalendar $calendar Calendrier CalDAV
	 * @return int                      >0 si OK, <0 si KO
	 */
	private function createEventInDolibarr($event, $calendar)
	{
		global $user, $conf;

		dol_syslog("CalDAVSync::createEventInDolibarr - UID CalDAV ".$event['uid'], LOG_DEBUG);

		// Créer un nouvel ActionComm
		$actioncomm = new ActionComm($this->db);
		
		// Remplir les propriétés
		$actioncomm->type_code = 'AC_OTH'; // Type "Autre"
		$actioncomm->label = !empty($event['summary']) ? $event['summary'] : 'Événement CalDAV';
		$actioncomm->note_private = !empty($event['description']) ? $event['description'] : '';
		$actioncomm->location = !empty($event['location']) ? $event['location'] : '';
		
		// Dates
		if ($event['start']) {
			$actioncomm->datep = $event['start']->getTimestamp();
			$actioncomm->datef = $event['end'] ? $event['end']->getTimestamp() : ($event['start']->getTimestamp() + 3600);
		}
		
		// Événement toute la journée
		$actioncomm->fulldayevent = !empty($event['all_day']) ? 1 : 0;
		
		// Assigner à l'utilisateur par défaut (admin ou utilisateur système)
		$actioncomm->userownerid = $user->id ? $user->id : 1;
		$actioncomm->percentage = -1; // Non applicable
		
		// Créer l'événement
		$result = $actioncomm->create($user);

		if ($result > 0) {
			// Créer le mapping
			$mapping = new CalDAVEventMapping($this->db);
			$mapping_result = $mapping->create($actioncomm->id, $event['uid'], $calendar->id, 'caldav', !empty($event['etag']) ? $event['etag'] : null);

			if ($mapping_result > 0) {
				dol_syslog("CalDAVSync::createEventInDolibarr - Événement créé avec succès, ID ".$actioncomm->id, LOG_INFO);
				return $actioncomm->id;
			} else {
				$this->error = "Événement créé mais mapping échoué";
				return -1;
			}
		} else {
			$this->error = $actioncomm->error;
			dol_syslog("CalDAVSync::createEventInDolibarr - Erreur création: ".$this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 * Mettre à jour un événement dans Dolibarr depuis CalDAV
	 *
	 * @param  array               $event   Événement CalDAV
	 * @param  CalDAVEventMapping  $mapping Mapping existant
	 * @param  CalDAVCalendar      $calendar Calendrier CalDAV
	 * @return int                          >0 si OK, <0 si KO
	 */
	private function updateEventInDolibarr($event, $mapping, $calendar)
	{
		global $user;

		dol_syslog("CalDAVSync::updateEventInDolibarr - ActionComm ID ".$mapping->fk_actioncomm.", UID ".$event['uid'], LOG_DEBUG);

		// Charger l'événement Dolibarr
		$actioncomm = new ActionComm($this->db);
		$result = $actioncomm->fetch($mapping->fk_actioncomm);

		if ($result <= 0) {
			$this->error = "Événement Dolibarr introuvable";
			return -1;
		}

		// Mettre à jour les propriétés
		$actioncomm->label = !empty($event['summary']) ? $event['summary'] : $actioncomm->label;
		$actioncomm->note_private = !empty($event['description']) ? $event['description'] : '';
		$actioncomm->location = !empty($event['location']) ? $event['location'] : '';
		
		// Dates
		if ($event['start']) {
			$actioncomm->datep = $event['start']->getTimestamp();
			$actioncomm->datef = $event['end'] ? $event['end']->getTimestamp() : ($event['start']->getTimestamp() + 3600);
		}
		
		$actioncomm->fulldayevent = !empty($event['all_day']) ? 1 : 0;

		// Mettre à jour l'événement
		$result = $actioncomm->update($user);

		if ($result > 0) {
			// Mettre à jour le mapping
			$mapping->update('caldav', !empty($event['etag']) ? $event['etag'] : null);
			dol_syslog("CalDAVSync::updateEventInDolibarr - Événement mis à jour avec succès", LOG_INFO);
			return 1;
		} else {
			$this->error = $actioncomm->error;
			dol_syslog("CalDAVSync::updateEventInDolibarr - Erreur: ".$this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 * Gérer les événements supprimés sur CalDAV
	 *
	 * @param  CalDAVCalendar $calendar Calendrier CalDAV
	 * @param  array          $caldav_events Événements présents sur CalDAV
	 * @return int                      Nombre d'événements supprimés
	 */
	private function handleDeletedCalDAVEvents($calendar, $caldav_events)
	{
		global $user;

		// Créer un tableau des UIDs présents sur CalDAV
		$caldav_uids = array();
		foreach ($caldav_events as $event) {
			$caldav_uids[] = $event['uid'];
		}

		// Récupérer tous les mappings pour ce calendrier
		$mappings = CalDAVEventMapping::getAllByCalendar($this->db, $calendar->id);

		$deleted_count = 0;

		foreach ($mappings as $mapping) {
			// Si l'UID n'est plus sur CalDAV, supprimer l'événement Dolibarr
			if (!in_array($mapping->caldav_uid, $caldav_uids)) {
				dol_syslog("CalDAVSync::handleDeletedCalDAVEvents - Événement supprimé sur CalDAV, UID ".$mapping->caldav_uid, LOG_INFO);

				// Charger et supprimer l'événement Dolibarr
				$actioncomm = new ActionComm($this->db);
				if ($actioncomm->fetch($mapping->fk_actioncomm) > 0) {
					$result = $actioncomm->delete($user);
					if ($result > 0) {
						// Le mapping sera supprimé automatiquement (CASCADE)
						$deleted_count++;
					}
				}
			}
		}

		return $deleted_count;
	}
}
