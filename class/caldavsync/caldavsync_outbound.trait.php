<?php
/* Copyright (C) 2025-2026 MATER Stéphane
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       htdocs/custom/caldavclient/class/caldavsync/caldavsync_outbound.trait.php
 * \ingroup    caldavclient
 * \brief      Orchestration sync, poussée Dolibarr → CalDAV (y compris choix calendriers / ressources).
 */

trait CalDAVSyncOutboundTrait
{
	/**
	 * Synchroniser tous les calendriers selon le mode configuré
	 * Méthode appelée par le cron job
	 *
	 * @return int  0=OK, <0=KO
	 */
	public function syncAllCalendars()
	{
		global $conf, $user, $langs;

		dol_syslog("CalDAVSync::syncAllCalendars - Début de la synchronisation", LOG_INFO);

		// Vérifier que le module est activé
		if (empty($conf->caldavclient->enabled)) {
			$this->error = "Module CalDAV Client désactivé";
			dol_syslog("CalDAVSync::syncAllCalendars - ".$this->error, LOG_WARNING);
			return -1;
		}

		// Vérifier que la synchronisation automatique est activée
		if (empty($conf->global->CALDAVCLIENT_AUTO_SYNC_ENABLED)) {
			dol_syslog("CalDAVSync::syncAllCalendars - Synchronisation automatique désactivée", LOG_INFO);
			return 0;
		}

		// Récupérer le mode de synchronisation
		$sync_mode = !empty($conf->global->CALDAVCLIENT_SYNC_MODE) ? $conf->global->CALDAVCLIENT_SYNC_MODE : 'dolibarr_to_caldav';
		
		dol_syslog("CalDAVSync::syncAllCalendars - Mode: ".$sync_mode, LOG_INFO);

		// Récupérer tous les calendriers actifs
		$calendars = CalDAVCalendar::getAllActive($conf->entity);

		if (empty($calendars)) {
			dol_syslog("CalDAVSync::syncAllCalendars - Aucun calendrier actif trouvé", LOG_INFO);
			return 0;
		}

		dol_syslog("CalDAVSync::syncAllCalendars - ".count($calendars)." calendrier(s) à synchroniser", LOG_INFO);

		$success_count = 0;
		$error_count = 0;

		// En mode Dolibarr -> CalDAV, on ne doit PAS pousser le même événement vers tous les calendriers,
		// sinon on crée des doublons / conflits de mapping. On prend 1 calendrier cible:
		// - si des calendriers ont is_default_target=1, on prend ceux-là
		// - sinon on prend le premier calendrier actif
		if ($sync_mode === 'dolibarr_to_caldav') {
			$targets = array();
			foreach ($calendars as $cal) {
				if (property_exists($cal, 'is_default_target') && !empty($cal->is_default_target)) {
					$targets[] = $cal;
				}
			}
			if (!empty($targets)) {
				$calendars = $targets;
			} else {
				$calendars = array($calendars[0]);
			}
		}

		// Synchroniser chaque calendrier
		foreach ($calendars as $calendar) {
			$result = $this->syncCalendar($calendar->id, $sync_mode);
			
			if ($result >= 0) {
				$success_count++;
			} else {
				$error_count++;
				$this->errors[] = "Erreur calendrier ".$calendar->name.": ".$this->error;
			}
		}

		dol_syslog("CalDAVSync::syncAllCalendars - Terminé: ".$success_count." OK, ".$error_count." erreurs", LOG_INFO);

		return ($error_count > 0) ? -1 : 0;
	}

	/**
	 * Synchroniser un calendrier spécifique
	 *
	 * @param  int    $calendar_id  ID du calendrier
	 * @param  string $sync_mode    Mode de synchronisation
	 * @return int                  Nombre d'événements synchronisés, <0 si erreur
	 */
	public function syncCalendar($calendar_id, $sync_mode = null)
	{
		global $conf, $user;

		if ($sync_mode === null) {
			$sync_mode = !empty($conf->global->CALDAVCLIENT_SYNC_MODE) ? $conf->global->CALDAVCLIENT_SYNC_MODE : 'dolibarr_to_caldav';
		}

		dol_syslog("CalDAVSync::syncCalendar - Calendrier ".$calendar_id.", mode: ".$sync_mode, LOG_DEBUG);

		// Charger le calendrier
		$calendar = new CalDAVCalendar($this->db);
		$result = $calendar->fetch($calendar_id);
		
		if ($result <= 0) {
			$this->error = "Calendrier introuvable";
			return -1;
		}

		if (!$calendar->enabled) {
			dol_syslog("CalDAVSync::syncCalendar - Calendrier désactivé", LOG_DEBUG);
			return 0;
		}

		// Charger la connexion
		$connection = new CalDAVConnection($this->db);
		$result = $connection->fetch($calendar->fk_connection);
		
		if ($result <= 0 || !$connection->enabled) {
			$this->error = "Connexion invalide ou désactivée";
			return -1;
		}

		// Exécuter la synchronisation selon le mode
		$synced_count = 0;
		
		switch ($sync_mode) {
			case 'dolibarr_to_caldav':
				$synced_count = $this->syncDolibarrToCalDAV($calendar, $connection);
				break;
				
			case 'caldav_to_dolibarr':
				$synced_count = $this->syncCalDAVToDolibarr($calendar, $connection);
				break;
				
			case 'bidirectional':
				$synced_count = $this->syncBidirectional($calendar, $connection);
				break;
				
			default:
				$this->error = "Mode de synchronisation inconnu: ".$sync_mode;
				return -1;
		}

		return $synced_count;
	}

	/**
	 * Synchroniser UNE action Dolibarr vers CalDAV (création/modification)
	 *
	 * @param  int $actioncomm_id ID de llx_actioncomm
	 * @param  int $calendar_id   (optionnel) ID du calendrier cible, sinon on choisit un calendrier par défaut
	 * @return int                >0 si synchronisé, 0 si ignoré, <0 si erreur
	 */
	public function syncOneActioncommToCalDAV($actioncomm_id, $calendar_id = 0)
	{
		global $conf, $user;

		// Sécurité
		if (empty($actioncomm_id)) return 0;

		// Charger l'action
		$actioncomm = new ActionComm($this->db);
		if ($actioncomm->fetch((int) $actioncomm_id) <= 0) {
			$this->error = "ActionComm introuvable";
			return -1;
		}

		// Ne JAMAIS synchroniser les événements automatiques système (devis, signature, etc.)
		if ($this->isSystemAutoActionComm($actioncomm)) {
			dol_syslog("CalDAVSync::syncOneActioncommToCalDAV - évènement systemauto ignoré (actioncomm_id=".$actioncomm->id.")", LOG_DEBUG);
			return 0;
		}

		// Si on force un calendrier, on synchronise uniquement vers celui-là (compatibilité)
		if (!empty($calendar_id)) {
			$tmp = new CalDAVCalendar($this->db);
			if ($tmp->fetch((int) $calendar_id) > 0 && !empty($tmp->enabled)) {
				return $this->syncOneActioncommToOneCalendar($actioncomm, $tmp);
			}
		}

		// Sinon: on synchronise vers tous les calendriers correspondant aux utilisateurs affectés
		$calendars = CalDAVCalendar::getAllActive($conf->entity);
		if (empty($calendars)) {
			$this->error = "Aucun calendrier actif";
			return -1;
		}

		$user_ids = $this->getUserIdsForActionComm($actioncomm);
		$target_calendars = array(); // key = calendar_id

		foreach ($user_ids as $uid) {
			$chosen = $this->chooseCalendarForUserId($calendars, (int) $uid);
			if (!empty($chosen) && !empty($chosen->id)) {
				$target_calendars[(int) $chosen->id] = $chosen;
			}
		}

		// Fallback: comportement historique si aucun user n'a un calendrier "owner"
		if (empty($target_calendars)) {
			$fallback = $this->chooseFallbackCalendar($calendars);
			$target_calendars[(int) $fallback->id] = $fallback;
		}

		$ok_count = 0;
		foreach ($target_calendars as $cal) {
			$res = $this->syncOneActioncommToOneCalendar($actioncomm, $cal);
			if ($res > 0) $ok_count++;
		}

		return ($ok_count > 0) ? 1 : -1;
	}

	/**
	 * Synchroniser un événement Dolibarr vers un calendrier précis
	 *
	 * @param  ActionComm     $actioncomm
	 * @param  CalDAVCalendar $calendar
	 * @return int            >0 si OK, <0 si KO
	 */
	private function syncOneActioncommToOneCalendar($actioncomm, $calendar)
	{
		// Log de diagnostic: quel calendrier est ciblé
		dol_syslog(
			"CalDAVSync::syncOneActioncommToOneCalendar - actioncomm_id=".$actioncomm->id
			." calendar_id=".(!empty($calendar->id) ? $calendar->id : 0)
			." calendar_name=".(!empty($calendar->name) ? $calendar->name : '')
			." calendar_owner=".(!empty($calendar->fk_user_owner) ? $calendar->fk_user_owner : 'NULL')
			." is_default_target=".(!empty($calendar->is_default_target) ? 1 : 0),
			LOG_DEBUG
		);

		// Charger la connexion
		$connection = new CalDAVConnection($this->db);
		if ($connection->fetch($calendar->fk_connection) <= 0 || empty($connection->enabled)) {
			$this->error = "Connexion CalDAV invalide ou désactivée";
			return -1;
		}

		$client = new CalDAVClient($connection);

		// Mapping existant pour CE calendrier ?
		$mapping = new CalDAVEventMapping($this->db);
		$mapping_exists = $mapping->fetchByActionCommAndCalendar($actioncomm->id, $calendar->id);

		if ($mapping_exists > 0) {
			return $this->updateEventOnCalDAV($actioncomm, $mapping, $calendar, $client);
		}

		// Création
		$caldav_uid = $this->generateCalDAVUID($actioncomm->id, (int) $calendar->id);
		return $this->createEventOnCalDAV($actioncomm, $calendar, $client, $caldav_uid);
	}

	/**
	 * Récupérer les IDs utilisateurs affectés à l'événement (multi)
	 *
	 * @param  ActionComm $actioncomm
	 * @return array<int>
	 */
	private function getUserIdsForActionComm($actioncomm)
	{
		$user_ids = array();

		// Utilisateur principal (si trouvé)
		$main = $this->getMainUserIdForActionComm($actioncomm);
		if ($main > 0) $user_ids[] = (int) $main;

		// Ressources utilisateurs affectées
		$sql = "SELECT ar.fk_element as fk_user";
		$sql .= " FROM ".MAIN_DB_PREFIX."actioncomm_resources as ar";
		$sql .= " WHERE ar.fk_actioncomm = ".((int) $actioncomm->id);
		$sql .= " AND ar.element_type = 'user'";
		$sql .= " AND ar.fk_element IS NOT NULL";
		$sql .= " ORDER BY ar.mandatory DESC, ar.fk_element ASC";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				if (!empty($obj->fk_user)) $user_ids[] = (int) $obj->fk_user;
			}
		}

		$user_ids = array_values(array_unique(array_filter($user_ids, function ($v) { return ((int) $v) > 0; })));
		return $user_ids;
	}

	/**
	 * Choisir un calendrier pour un utilisateur (via fk_user_owner)
	 *
	 * @param  array $calendars
	 * @param  int   $user_id
	 * @return CalDAVCalendar|null
	 */
	private function chooseCalendarForUserId($calendars, $user_id)
	{
		if (empty($user_id)) return null;

		foreach ($calendars as $cal) {
			if (!empty($cal->fk_user_owner) && (int) $cal->fk_user_owner === (int) $user_id
				&& property_exists($cal, 'is_default_target') && !empty($cal->is_default_target)) {
				return $cal;
			}
		}
		foreach ($calendars as $cal) {
			if (!empty($cal->fk_user_owner) && (int) $cal->fk_user_owner === (int) $user_id) {
				return $cal;
			}
		}
		return null;
	}

	/**
	 * Choisir un calendrier fallback (cible par défaut sinon premier)
	 *
	 * @param  array $calendars
	 * @return CalDAVCalendar
	 */
	private function chooseFallbackCalendar($calendars)
	{
		foreach ($calendars as $cal) {
			if (property_exists($cal, 'is_default_target') && !empty($cal->is_default_target)) {
				return $cal;
			}
		}
		return $calendars[0];
	}

	/**
	 * Trouver l'utilisateur principal d'un événement Dolibarr
	 *
	 * @param  ActionComm $actioncomm
	 * @return int ID utilisateur, 0 si inconnu
	 */
	private function getMainUserIdForActionComm($actioncomm)
	{
		global $conf, $user;

		if (!empty($actioncomm->fk_user_action)) {
			return (int) $actioncomm->fk_user_action;
		}

		// Chercher dans les ressources (le plus courant pour les événements)
		$sql = "SELECT ar.fk_element as fk_user";
		$sql .= " FROM ".MAIN_DB_PREFIX."actioncomm_resources as ar";
		$sql .= " WHERE ar.fk_actioncomm = ".((int) $actioncomm->id);
		$sql .= " AND ar.element_type = 'user'";
		$sql .= " AND ar.fk_element IS NOT NULL";
		$sql .= " ORDER BY ar.mandatory DESC, ar.fk_element ASC";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if (!empty($obj->fk_user)) {
				return (int) $obj->fk_user;
			}
		}

		// Fallback: auteur de l'événement
		if (!empty($actioncomm->fk_user_author)) {
			return (int) $actioncomm->fk_user_author;
		}

		// Dernier fallback: utilisateur courant
		if (!empty($user) && !empty($user->id)) {
			return (int) $user->id;
		}

		return 0;
	}

	/**
	 * Synchroniser Dolibarr → CalDAV (Dolibarr est maître)
	 *
	 * @param  CalDAVCalendar   $calendar   Calendrier CalDAV
	 * @param  CalDAVConnection $connection Connexion CalDAV
	 * @return int                          Nombre d'événements synchronisés, <0 si erreur
	 */
	private function syncDolibarrToCalDAV($calendar, $connection)
	{
		global $conf, $user;

		dol_syslog("CalDAVSync::syncDolibarrToCalDAV - Début pour calendrier ".$calendar->name, LOG_DEBUG);

		// Créer le client CalDAV
		$client = new CalDAVClient($connection);

		// Récupérer tous les événements Dolibarr non synchronisés ou modifiés
		// Important: selon les versions Dolibarr, la colonne "note_private" peut ne pas exister.
		// Comme on recharge ensuite l'objet ActionComm complet avec ->fetch(), on sélectionne uniquement
		// les colonnes nécessaires pour filtrer/ordonner.
		$sql = "SELECT a.id, a.datep, a.tms";
		$sql .= " FROM ".MAIN_DB_PREFIX."actioncomm as a";
		// Exclure les événements natifs automatiques (llx_c_actioncomm.type = 'systemauto')
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_actioncomm as ca ON ca.id = a.fk_action";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."caldav_event_mapping as m ON a.id = m.fk_actioncomm";
		$sql .= " WHERE a.entity = ".$conf->entity;
		$sql .= " AND (ca.type IS NULL OR ca.type <> 'systemauto')";
		// Événements non synchronisés OU modifiés après la dernière sync
		$sql .= " AND (m.rowid IS NULL OR a.tms > m.last_sync_date)";
		$sql .= " ORDER BY a.datep ASC";
		$sql .= " LIMIT 100"; // Limiter pour éviter les timeouts

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$synced_count = 0;
		$num = $this->db->num_rows($resql);

		dol_syslog("CalDAVSync::syncDolibarrToCalDAV - ".$num." événement(s) à traiter", LOG_DEBUG);

		while ($obj = $this->db->fetch_object($resql)) {
			// Charger l'événement complet
			$actioncomm = new ActionComm($this->db);
			$actioncomm->fetch($obj->id);

			// Double sécurité: si un événement auto passe malgré le filtre SQL, on le saute ici.
			if ($this->isSystemAutoActionComm($actioncomm)) {
				continue;
			}

			// Vérifier s'il existe déjà un mapping (au moins un) pour cet événement
			$mapping = new CalDAVEventMapping($this->db);
			$mapping_exists = $mapping->fetchByActionComm($actioncomm->id);

			if ($mapping_exists > 0) {
				// Mise à jour d'un événement existant
				$result = $this->updateEventOnCalDAV($actioncomm, $mapping, $calendar, $client);
			} else {
				// Création d'un nouvel événement
				$result = $this->createEventOnCalDAV($actioncomm, $calendar, $client);
			}

			if ($result > 0) {
				$synced_count++;
			}
		}

		dol_syslog("CalDAVSync::syncDolibarrToCalDAV - Terminé: ".$synced_count." événement(s) synchronisé(s)", LOG_INFO);

		return $synced_count;
	}
}
