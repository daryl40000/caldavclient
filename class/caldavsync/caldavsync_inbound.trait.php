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

		// A complete calendar inventory is required to detect deletions safely.

		try {
			$events = $client->getEvents($calendar->url, null, null, false, true); // Pas de cache pour la sync
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
		if ($deleted_count < 0) {
			return -1;
		}
		
		dol_syslog("CalDAVSync::syncCalDAVToDolibarr - Terminé: ".$synced_count." synchronisé(s), ".$deleted_count." supprimé(s)", LOG_INFO);

		return $synced_count;
	}

	/**
	 * Synchronisation bidirectionnelle avec baseline et conflits explicites
	 *
	 * @param  CalDAVCalendar   $calendar   Calendrier CalDAV
	 * @param  CalDAVConnection $connection Connexion CalDAV
	 * @return int                          Nombre d'événements synchronisés, <0 si erreur
	 */
	private function syncBidirectional($calendar, $connection, $only_actioncomm = 0)
	{
		global $conf, $user;
		$client = new CalDAVClient($connection);
		try {
			$events = $client->getEvents($calendar->url, null, null, false, true);
			$remote = array();
			foreach ($events as $event) {
				if (empty($event['etag'])) throw new RuntimeException('Remote event has no ETag');
				if (isset($remote[$event['uid']])) {
					throw new RuntimeException('Conflict: duplicate or recurring remote UID');
				}
				$remote[$event['uid']] = $event;
			}
			$mappings = CalDAVEventMapping::getAllByCalendar($this->db, $calendar->id);
			$count = 0;
			$failed = false;
			foreach ($mappings as $mapping) {
				$event = isset($remote[$mapping->caldav_uid]) ? $remote[$mapping->caldav_uid] : null;
				unset($remote[$mapping->caldav_uid]);
				if ($only_actioncomm && (int) $mapping->fk_actioncomm !== (int) $only_actioncomm) continue;
				$local = new ActionComm($this->db);
				$exists = $local->fetch($mapping->fk_actioncomm);
				if ($exists < 0) throw new RuntimeException($local->error);
				if ($exists > 0 && (int) $local->entity !== (int) $conf->entity) {
					throw new RuntimeException('ActionComm belongs to another entity');
				}
				if ($exists > 0 && $this->isSystemAutoActionComm($local)) continue;
				$result = $this->syncMappedEvent($exists > 0 ? $local : null, $event, $mapping, $calendar, $client);
				if ($result < 0) $failed = true; else $count += $result;
			}
			$sql = 'SELECT a.id FROM '.MAIN_DB_PREFIX.'actioncomm a LEFT JOIN '.MAIN_DB_PREFIX.'c_actioncomm ca ON ca.id=a.fk_action';
			$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'caldav_event_mapping m ON m.fk_actioncomm=a.id AND m.fk_calendar='.(int) $calendar->id;
			$sql .= ' WHERE a.entity='.(int) $conf->entity." AND (ca.type IS NULL OR ca.type <> 'systemauto') AND m.rowid IS NULL";
			if ($only_actioncomm) $sql .= ' AND a.id='.(int) $only_actioncomm;
			$res = $this->db->query($sql);
			if (!$res) throw new RuntimeException($this->db->lasterror());
			while ($row = $this->db->fetch_object($res)) {
				$local = new ActionComm($this->db);
				if ($local->fetch($row->id) <= 0 || $this->isSystemAutoActionComm($local)) continue;
				$uid = $this->generateCalDAVUID($local->id, (int) $calendar->id);
				if (isset($remote[$uid])) {
					// Recover a successful PUT followed by failed mapping persistence without another PUT.
					if (!$this->sameEventContent($local, $remote[$uid]) || empty($remote[$uid]['etag'])) {
						$this->error = 'Conflict: unmapped deterministic UID '.$uid;
						$failed = true;
					} else {
						$mapping = new CalDAVEventMapping($this->db);
						if ($mapping->create($local->id, $uid, $calendar->id, 'dolibarr', $remote[$uid]['etag'], $this->localSyncHash($local)) < 0) {
							$this->error = $mapping->error; $failed = true;
						}
					}
					unset($remote[$uid]);
				} else {
					$result = $this->createEventOnCalDAV($local, $calendar, $client, $uid);
					if ($result < 0) $failed = true; else $count += $result;
				}
			}
			if (!$only_actioncomm) {
				foreach ($remote as $event) {
					if (!empty($event['unsupported_recurrence'])) {
						$this->error = 'Conflict: unsupported remote recurrence'; $failed = true; continue;
					}
					$result = $this->createEventInDolibarr($event, $calendar);
					if ($result < 0) $failed = true; else $count++;
				}
			}
			return $failed ? -1 : $count;
		} catch (Exception $e) {
			$this->error = $e->getMessage();
			return -1;
		}
	}

	private function localSyncHash($local)
	{
		return hash('sha256', json_encode(array((string) $local->label, (string) $local->note_private,
			(string) $local->location, (int) $local->datep, (int) $local->datef, (int) !empty($local->fulldayevent)), JSON_UNESCAPED_UNICODE));
	}

	private function sameEventContent($local, $remote)
	{
		return (string) $local->label === (string) $remote['summary']
			&& (string) $local->note_private === (string) $remote['description']
			&& (string) $local->location === (string) $remote['location']
			&& (int) $local->datep === $remote['start']->getTimestamp()
			&& (int) $local->datef === $remote['end']->getTimestamp() - (!empty($remote['all_day']) ? 1 : 0)
			&& (bool) $local->fulldayevent === !empty($remote['all_day']);
	}

	private function syncMappedEvent($local, $remote, $mapping, $calendar, $client)
	{
		global $user;
		$hash = $local ? $this->localSyncHash($local) : null;
		if (empty($mapping->local_sync_hash)) {
			if ($local && $remote && $this->sameEventContent($local, $remote)) {
				return $mapping->update('caldav', $remote['etag'], $hash) < 0 ? -1 : 0;
			}
			$this->error = 'Conflict: missing synchronization baseline for '.$mapping->caldav_uid;
			return -1;
		}
		$local_changed = !$local || $hash !== $mapping->local_sync_hash;
		$remote_changed = !$remote || empty($remote['etag']) || $remote['etag'] !== $mapping->caldav_etag;
		if (!$local && !$remote) return $mapping->delete() < 0 ? -1 : 0;
		if ($local_changed && $remote_changed) {
			$this->error = 'Conflict: both sides changed '.$mapping->caldav_uid;
			return -1;
		}
		if (!$local) {
			$result = $client->deleteEventOnCalendar($calendar->url, $mapping->caldav_uid, $remote['etag'], $remote['href']);
			if (empty($result['success'])) { $this->error = 'Remote deletion failed (HTTP '.$result['code'].')'; return -1; }
			return $mapping->delete() < 0 ? -1 : 1;
		}
		if (!$remote) {
			return $this->deleteEventInDolibarr($mapping, $hash);
		}
		if (!$local_changed && !$remote_changed) return 0;
		if (!empty($remote['unsupported_recurrence'])) { $this->error = 'Conflict: unsupported recurrence'; return -1; }
		if ($remote_changed && $this->sameEventContent($local, $remote)) {
			return $mapping->update('caldav', $remote['etag'], $hash) < 0 ? -1 : 0;
		}
		if ($remote_changed) return $this->updateEventInDolibarr($remote, $mapping, $calendar, $hash);
		return $this->updateEventOnCalDAV($local, $mapping, $calendar, $client, $remote);
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
				$mapping_result = $mapping->create($actioncomm->id, $caldav_uid, $calendar->id, 'dolibarr', !empty($res['etag']) ? $res['etag'] : null, $this->localSyncHash($actioncomm));

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
	private function updateEventOnCalDAV($actioncomm, $mapping, $calendar, $client, $remote_event = null)
	{
		dol_syslog("CalDAVSync::updateEventOnCalDAV - Événement Dolibarr ID ".$actioncomm->id.", UID ".$mapping->caldav_uid, LOG_DEBUG);

		// Convertir ActionComm en tableau pour CalDAVClient
		$event_data = $this->convertDolibarrToEventArray($actioncomm, $mapping->caldav_uid, $calendar);

		if (!$event_data) {
			$this->error = "Impossible de convertir l'événement";
			return -1;
		}

		try {
			if ($remote_event === null) {
				foreach ($client->getEvents($calendar->url, null, null, false, true) as $candidate) {
					if ($candidate['uid'] === $mapping->caldav_uid) { $remote_event = $candidate; break; }
				}
				if ($remote_event === null) throw new RuntimeException('Mapped remote event is missing');
			}
			// Mettre à jour l'événement sur CalDAV
			$etag = !empty($mapping->caldav_etag) ? $mapping->caldav_etag : null;
			$res = $client->updateEventOnCalendar($calendar->url, $mapping->caldav_uid, $etag, $event_data, $remote_event);
			$result = (!empty($res['success']));

			if ($result) {
				// Mettre à jour le mapping
				if ($mapping->update('dolibarr', !empty($res['etag']) ? $res['etag'] : null, $this->localSyncHash($actioncomm)) < 0) {
					$this->error = $mapping->error; return -1;
				}
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
		global $user;
		return $this->inboundTransaction(0, null, function ($actioncomm) use ($event, $calendar, $user) {
			// Remplir les propriétés
			$actioncomm->type_code = 'AC_OTH'; // Type "Autre"
			$actioncomm->label = !empty($event['summary']) ? $event['summary'] : 'Événement CalDAV';
			$actioncomm->note_private = !empty($event['description']) ? $event['description'] : '';
			$actioncomm->location = !empty($event['location']) ? $event['location'] : '';

			// Dates
			if ($event['start']) {
				$actioncomm->datep = $event['start']->getTimestamp();
				$actioncomm->datef = $event['end']->getTimestamp() - (!empty($event['all_day']) ? 1 : 0);
			}

			// Événement toute la journée
			$actioncomm->fulldayevent = !empty($event['all_day']) ? 1 : 0;

			// Assigner à l'utilisateur par défaut (admin ou utilisateur système)
			$actioncomm->userownerid = $user->id ? $user->id : 1;
			$actioncomm->percentage = -1; // Non applicable

			// Créer l'événement
			$result = $actioncomm->create($user, 1);

			if ($result > 0) {
				// Créer le mapping
				$mapping = new CalDAVEventMapping($this->db);
				$mapping_result = $mapping->create($actioncomm->id, $event['uid'], $calendar->id, 'caldav', !empty($event['etag']) ? $event['etag'] : null, $this->localSyncHash($actioncomm));

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
		});
	}

	/**
	 * Mettre à jour un événement dans Dolibarr depuis CalDAV
	 *
	 * @param  array               $event   Événement CalDAV
	 * @param  CalDAVEventMapping  $mapping Mapping existant
	 * @param  CalDAVCalendar      $calendar Calendrier CalDAV
	 * @return int                          >0 si OK, <0 si KO
	 */
	private function updateEventInDolibarr($event, $mapping, $calendar, $expected_hash = null)
	{
		global $user;
		return $this->inboundTransaction($mapping->fk_actioncomm, $expected_hash, function ($actioncomm) use ($event, $mapping, $user) {
			// Mettre à jour les propriétés
			$actioncomm->label = (string) $event['summary'];
			$actioncomm->note_private = !empty($event['description']) ? $event['description'] : '';
			$actioncomm->location = !empty($event['location']) ? $event['location'] : '';

			// Dates
			if ($event['start']) {
				$actioncomm->datep = $event['start']->getTimestamp();
				$actioncomm->datef = $event['end']->getTimestamp() - (!empty($event['all_day']) ? 1 : 0);
			}

			$actioncomm->fulldayevent = !empty($event['all_day']) ? 1 : 0;

			// Mettre à jour l'événement
			$result = $actioncomm->update($user, 1);

			if ($result > 0) {
				// Mettre à jour le mapping
				if ($mapping->update('caldav', !empty($event['etag']) ? $event['etag'] : null, $this->localSyncHash($actioncomm)) < 0) {
					$this->error = $mapping->error; return -1;
				}
				dol_syslog("CalDAVSync::updateEventInDolibarr - Événement mis à jour avec succès", LOG_INFO);
				return 1;
			} else {
				$this->error = $actioncomm->error;
				dol_syslog("CalDAVSync::updateEventInDolibarr - Erreur: ".$this->error, LOG_ERR);
				return -1;
			}
		});
	}

	private function deleteEventInDolibarr($mapping, $expected_hash = null)
	{
		global $user;
		return $this->inboundTransaction($mapping->fk_actioncomm, $expected_hash, function ($actioncomm) use ($mapping, $user) {
			if ($actioncomm->delete($user, 1) <= 0) { $this->error = $actioncomm->error; return -1; }
			if ($mapping->delete() < 0) { $this->error = $mapping->error; return -1; }
			return 1;
		});
	}

	/** Keep the native mutation and its mapping atomic; no remote I/O inside this transaction. */
	private function inboundTransaction($action_id, $expected_hash, $operation)
	{
		global $conf;
		if ($this->db->begin() <= 0) { $this->error = $this->db->lasterror(); return -1; }
		$savepoint = 'caldavclient_inbound';
		try {
			if (!$this->db->query('SAVEPOINT '.$savepoint)) throw new RuntimeException($this->db->lasterror());
			$actioncomm = new ActionComm($this->db);
			if ($action_id) {
				if (!$this->db->query('SELECT id FROM '.MAIN_DB_PREFIX.'actioncomm WHERE id='.(int) $action_id.' FOR UPDATE')) {
					throw new RuntimeException($this->db->lasterror());
				}
				if ($actioncomm->fetch($action_id) <= 0) throw new RuntimeException('Conflict: native event no longer exists');
				if ((int) $actioncomm->entity !== (int) $conf->entity) throw new RuntimeException('ActionComm belongs to another entity');
				if ($expected_hash !== null && $this->localSyncHash($actioncomm) !== $expected_hash) {
					throw new RuntimeException('Conflict: native event changed during synchronization');
				}
			}
			$result = $operation($actioncomm);
			if ($result < 0) throw new RuntimeException($this->error ?: 'Native synchronization failed');
			if (!$this->db->query('RELEASE SAVEPOINT '.$savepoint)) throw new RuntimeException($this->db->lasterror());
			if ($this->db->commit() <= 0) throw new RuntimeException('Native synchronization commit failed');
			return $result;
		} catch (Throwable $e) {
			$this->db->query('ROLLBACK TO SAVEPOINT '.$savepoint);
			$this->db->query('RELEASE SAVEPOINT '.$savepoint);
			$this->db->rollback();
			$this->error = $e->getMessage();
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
					$result = $this->deleteEventInDolibarr($mapping, $this->localSyncHash($actioncomm));
					if ($result < 0) return -1;
					$deleted_count += $result;
				}
			}
		}

		return $deleted_count;
	}
}
