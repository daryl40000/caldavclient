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
		$this->version = '0.22.0';
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
		if (empty($conf->caldavclient->enabled)) {
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
				return -1;
			}
			dol_syslog("CalDAVClientTrigger: Événement synchronisé (create) ID ".$object->id, LOG_INFO);
		} catch (Throwable $e) {
			dol_syslog("CalDAVClientTrigger: Exception sync (create) ".$e->getMessage(), LOG_ERR);
			return -1;
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
				return -1;
			}
			dol_syslog("CalDAVClientTrigger: Événement synchronisé (modify) ID ".$object->id, LOG_INFO);
		} catch (Throwable $e) {
			dol_syslog("CalDAVClientTrigger: Exception sync (modify) ".$e->getMessage(), LOG_ERR);
			return -1;
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

		if (!empty($mappings)) {
			$all_ok = true;
			foreach ($mappings as $mapping) {
				$delete_ok = false;
				try {
					dol_include_once('/caldavclient/class/caldavcalendar.class.php');
					dol_include_once('/caldavclient/class/caldavclient.class.php');
					dol_include_once('/caldavclient/lib/caldav.lib.php');

					// Charger le calendrier (pour avoir l'URL exacte du calendrier)
					$calendar = new CalDAVCalendar($this->db);
					if ($calendar->fetch($mapping->fk_calendar) > 0) {
						// Charger la connexion
						$connection = new CalDAVConnection($this->db);
						if ($connection->fetch($calendar->fk_connection) > 0) {
							// Créer le client
							$client = new CalDAVClient($connection);

							// Supprimer l'événement sur CalDAV (sur l'URL du calendrier)
							$etag = !empty($mapping->caldav_etag) ? $mapping->caldav_etag : null;
							$resDel = $client->deleteEventOnCalendar($calendar->url, $mapping->caldav_uid, $etag);

							if (!empty($resDel['success'])) {
								dol_syslog("CalDAVClientTrigger: Événement supprimé sur CalDAV (UID ".$mapping->caldav_uid.", cal=".$calendar->id.")", LOG_INFO);
								$delete_ok = true;
							} else {
								$code = isset($resDel['code']) ? (int) $resDel['code'] : 0;
								$url = !empty($resDel['url']) ? $resDel['url'] : '';
								dol_syslog("CalDAVClientTrigger: Erreur suppression sur CalDAV (HTTP ".$code.") UID ".$mapping->caldav_uid." URL ".$url, LOG_WARNING);
							}
						} else {
							dol_syslog("CalDAVClientTrigger: Suppression CalDAV impossible - connexion introuvable (fk_connection=".$calendar->fk_connection.")", LOG_WARNING);
						}
					} else {
						dol_syslog("CalDAVClientTrigger: Suppression CalDAV impossible - calendrier introuvable (fk_calendar=".$mapping->fk_calendar.")", LOG_WARNING);
					}
				} catch (Throwable $e) {
					dol_syslog("CalDAVClientTrigger: Exception suppression CalDAV ".$e->getMessage(), LOG_ERR);
				}

				// IMPORTANT: Ne supprimer le mapping QUE si la suppression côté CalDAV a réussi.
				if ($delete_ok) {
					$mapping->delete();
				} else {
					$all_ok = false;
				}
			}

			if (!$all_ok) {
				return -1;
			}
		} else {
			// Cas courant si la table llx_caldav_event_mapping a une FK fk_actioncomm avec "ON DELETE CASCADE":
			// le mapping est effacé automatiquement au moment du DELETE SQL sur llx_actioncomm,
			// donc le trigger ne peut plus retrouver l'UID CalDAV à supprimer.
			dol_syslog(
				"CalDAVClientTrigger: Aucun mapping trouvé pour l'actioncomm ID ".$object->id
				." (suppression CalDAV non possible). Si llx_caldav_event_mapping.fk_actioncomm est en ON DELETE CASCADE,"
				." il faut retirer ce CASCADE pour permettre la propagation de la suppression.",
				LOG_WARNING
			);
		}

		return 1;
	}
}
