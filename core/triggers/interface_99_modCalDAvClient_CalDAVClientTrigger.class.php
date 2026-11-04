<?php
/* Copyright (C) 2025-2026  MATER Stéphane
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/triggers/interface_99_modCalDAvClient_CalDAVClientTrigger.class.php
 * \ingroup caldavclient
 * \brief   Trigger pour synchroniser les événements Dolibarr avec CalDAV
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Classe pour gérer les triggers du module CalDAV Client
 */
class InterfaceCalDAvClientTrigger extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "caldavclient";
		$this->description = "Triggers du module CalDAV Client pour la synchronisation";
		$this->version = '0.22.1';
		$this->picto = 'caldavclient@caldavclient';
	}

	/**
	 * Fonction appelée lorsqu'un événement Dolibarr survient
	 *
	 * @param  string   $action Action code
	 * @param  object   $object Object
	 * @param  User     $user   Object user
	 * @param  Translate $langs Objet Translate
	 * @param  conf     $conf   Objet conf
	 * @return int              <0 si KO, 0 si rien à faire, >0 si OK
	 */
	public function runTrigger($action, $object, $user, $langs, $conf)
	{
		if (empty($conf->caldavclient->enabled) || empty($conf->global->CALDAVCLIENT_AUTO_SYNC_ENABLED)) {
			return 0;
		}

		// Charger les classes nécessaires
		dol_include_once('/caldavclient/class/caldavsync.class.php');
		dol_include_once('/caldavclient/class/caldaveventmapping.class.php');

		// Récupérer le mode de synchronisation
		$sync_mode = !empty($conf->global->CALDAVCLIENT_SYNC_MODE) ? $conf->global->CALDAVCLIENT_SYNC_MODE : 'dolibarr_to_caldav';

		// Ne synchroniser que si le mode le permet
		if ($sync_mode != 'dolibarr_to_caldav' && $sync_mode != 'bidirectional') {
			dol_syslog("CalDAVClientTrigger: Mode de synchronisation '".$sync_mode."' ne nécessite pas de trigger", LOG_DEBUG);
			return 0;
		}

		// Actions sur les événements (ActionComm)
		switch ($action) {
			case 'ACTION_CREATE':
				return $this->handleActionCreate($object, $user, $conf);
				
			case 'ACTION_MODIFY':
				return $this->handleActionModify($object, $user, $conf);
				
			case 'ACTION_DELETE':
				return $this->handleActionDelete($object, $user, $conf);
				
			default:
				return 0;
		}
	}

	/**
	 * Gérer la création d'un événement Dolibarr
	 *
	 * @param  ActionComm $object Événement créé
	 * @param  User       $user   Utilisateur
	 * @param  conf       $conf   Configuration
	 * @return int                <0 si KO, 0 si rien, >0 si OK
	 */
	private function handleActionCreate($object, $user, $conf)
	{
		dol_syslog("CalDAVClientTrigger::handleActionCreate - ID ".$object->id, LOG_DEBUG);

		// Vérifier si l'événement est déjà mappé (éviter les boucles)
		if (CalDAVEventMapping::isSynced($this->db, $object->id)) {
			dol_syslog("CalDAVClientTrigger: Événement déjà synchronisé, ignore", LOG_DEBUG);
			return 0;
		}

		// Synchroniser immédiatement UNIQUEMENT cet événement
		try {
			$sync = new CalDAVSync($this->db);
			$res = $sync->syncOneActioncommToCalDAV((int) $object->id);
			if ($res < 0) {
				dol_syslog("CalDAVClientTrigger: Erreur sync (create) ".$sync->error, LOG_ERR);
				return 0;
			}
			dol_syslog("CalDAVClientTrigger: Événement synchronisé (create) ID ".$object->id, LOG_INFO);
		} catch (Throwable $e) {
			dol_syslog("CalDAVClientTrigger: Exception sync (create) ".$e->getMessage(), LOG_ERR);
			return 0;
		}
		
		return 1;
	}

	/**
	 * Gérer la modification d'un événement Dolibarr
	 *
	 * @param  ActionComm $object Événement modifié
	 * @param  User       $user   Utilisateur
	 * @param  conf       $conf   Configuration
	 * @return int                <0 si KO, 0 si rien, >0 si OK
	 */
	private function handleActionModify($object, $user, $conf)
	{
		dol_syslog("CalDAVClientTrigger::handleActionModify - ID ".$object->id, LOG_DEBUG);

		// Synchroniser immédiatement UNIQUEMENT cet événement (update ou create)
		// En multi-utilisateurs, l'événement peut être synchronisé vers plusieurs calendriers:
		// on ne force donc plus un calendar_id unique.
		try {
			$sync = new CalDAVSync($this->db);
			$res = $sync->syncOneActioncommToCalDAV((int) $object->id, 0);
			if ($res < 0) {
				dol_syslog("CalDAVClientTrigger: Erreur sync (modify) ".$sync->error, LOG_ERR);
				return 0;
			}
			dol_syslog("CalDAVClientTrigger: Événement synchronisé (modify) ID ".$object->id, LOG_INFO);
		} catch (Throwable $e) {
			dol_syslog("CalDAVClientTrigger: Exception sync (modify) ".$e->getMessage(), LOG_ERR);
			return 0;
		}

		return 1;
	}

	/**
	 * Gérer la suppression d'un événement Dolibarr
	 *
	 * @param  ActionComm $object Événement supprimé
	 * @param  User       $user   Utilisateur
	 * @param  conf       $conf   Configuration
	 * @return int                <0 si KO, 0 si rien, >0 si OK
	 */
	private function handleActionDelete($object, $user, $conf)
	{
		dol_syslog("CalDAVClientTrigger::handleActionDelete - ID ".$object->id, LOG_DEBUG);

		// Multi-calendriers: un événement peut avoir plusieurs mappings
		$mappings = CalDAVEventMapping::getAllByActionComm($this->db, (int) $object->id);
		foreach ($mappings as $mapping) {
			$sync = new CalDAVSync($this->db);
			if ($sync->syncCalendar($mapping->fk_calendar, 'bidirectional', (int) $object->id) < 0) {
				dol_syslog('CalDAV deletion deferred: '.$sync->error, LOG_ERR);
			}
		}
		return 0;
	}
}
