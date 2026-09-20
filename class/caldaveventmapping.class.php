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
 */

/**
 * \file    class/caldaveventmapping.class.php
 * \ingroup caldavclient
 * \brief   Classe pour gérer le mapping entre événements Dolibarr et événements CalDAV
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Classe pour gérer le mapping entre événements Dolibarr et CalDAV
 */
class CalDAVEventMapping extends CommonObject
{
	public $rowid;
	public $fk_actioncomm;
	public $caldav_uid;
	public $fk_calendar;
	public $last_sync_date;
	public $last_sync_direction;
	public $caldav_etag;
	public $date_creation;
	public $tms;

	/**
	 * Constructeur
	 *
	 * @param DoliDB $db Gestionnaire de base de données
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Créer un mapping
	 *
	 * @param  int    $fk_actioncomm  ID événement Dolibarr
	 * @param  string $caldav_uid     UID CalDAV
	 * @param  int    $fk_calendar    ID calendrier CalDAV
	 * @param  string $direction      Direction sync ('dolibarr' ou 'caldav')
	 * @param  string $etag           ETag CalDAV (optionnel)
	 * @return int                    <0 si KO, >0 si OK
	 */
	public function create($fk_actioncomm, $caldav_uid, $fk_calendar, $direction = 'dolibarr', $etag = null)
	{
		$now = dol_now();

		// Important: on utilise ON DUPLICATE KEY pour éviter les erreurs fatales
		// si un mapping existe déjà pour le couple (fk_actioncomm, fk_calendar).
		// Cela rend la synchronisation idempotente (rejouable sans crash).
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."caldav_event_mapping";
		$sql .= " (fk_actioncomm, caldav_uid, fk_calendar, last_sync_date, last_sync_direction, caldav_etag, date_creation)";
		$sql .= " VALUES";
		$sql .= " (".((int) $fk_actioncomm).", '".$this->db->escape($caldav_uid)."', ".((int) $fk_calendar).", '".$this->db->idate($now)."', '".$this->db->escape($direction)."', ".($etag ? "'".$this->db->escape($etag)."'" : "NULL").", '".$this->db->idate($now)."')";
		$sql .= " ON DUPLICATE KEY UPDATE";
		$sql .= " rowid = LAST_INSERT_ID(rowid),";
		$sql .= " caldav_uid = VALUES(caldav_uid),";
		$sql .= " last_sync_date = VALUES(last_sync_date),";
		$sql .= " last_sync_direction = VALUES(last_sync_direction),";
		$sql .= " caldav_etag = VALUES(caldav_etag)";

		$resql = $this->db->query($sql);
		if ($resql) {
			$this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX."caldav_event_mapping");
			// Remplir l'objet avec les bonnes valeurs (utile si c'était un doublon)
			$this->fk_actioncomm = (int) $fk_actioncomm;
			$this->caldav_uid = $caldav_uid;
			$this->fk_calendar = (int) $fk_calendar;
			$this->last_sync_date = $now;
			$this->last_sync_direction = $direction;
			$this->caldav_etag = $etag;
			$this->date_creation = $now;
			return $this->rowid;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Mettre à jour un mapping
	 *
	 * @param  string $direction  Direction sync
	 * @param  string $etag       ETag CalDAV (optionnel)
	 * @return int                <0 si KO, >0 si OK
	 */
	public function update($direction = null, $etag = null)
	{
		$now = dol_now();

		$sql = "UPDATE ".MAIN_DB_PREFIX."caldav_event_mapping SET";
		$sql .= " last_sync_date = '".$this->db->idate($now)."'";
		if ($direction !== null) {
			$sql .= ", last_sync_direction = '".$this->db->escape($direction)."'";
		}
		if ($etag !== null) {
			$sql .= ", caldav_etag = '".$this->db->escape($etag)."'";
		}
		$sql .= " WHERE rowid = ".((int) $this->rowid);

		$resql = $this->db->query($sql);
		if ($resql) {
			return 1;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Récupérer un mapping par ID événement Dolibarr
	 *
	 * @param  int  $fk_actioncomm  ID événement Dolibarr
	 * @return int                  <0 si KO, 0 si non trouvé, >0 si OK
	 */
	public function fetchByActionComm($fk_actioncomm)
	{
		$sql = "SELECT rowid, fk_actioncomm, caldav_uid, fk_calendar, last_sync_date, last_sync_direction, caldav_etag, date_creation";
		$sql .= " FROM ".MAIN_DB_PREFIX."caldav_event_mapping";
		$sql .= " WHERE fk_actioncomm = ".((int) $fk_actioncomm);
		$sql .= " ORDER BY rowid ASC";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$this->rowid = $obj->rowid;
				$this->fk_actioncomm = $obj->fk_actioncomm;
				$this->caldav_uid = $obj->caldav_uid;
				$this->fk_calendar = $obj->fk_calendar;
				$this->last_sync_date = $this->db->jdate($obj->last_sync_date);
				$this->last_sync_direction = $obj->last_sync_direction;
				$this->caldav_etag = $obj->caldav_etag;
				$this->date_creation = $this->db->jdate($obj->date_creation);
				return 1;
			}
			return 0;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Récupérer un mapping par couple (ActionComm, calendrier)
	 *
	 * @param  int  $fk_actioncomm  ID événement Dolibarr
	 * @param  int  $fk_calendar    ID calendrier CalDAV
	 * @return int                  <0 si KO, 0 si non trouvé, >0 si OK
	 */
	public function fetchByActionCommAndCalendar($fk_actioncomm, $fk_calendar)
	{
		$sql = "SELECT rowid, fk_actioncomm, caldav_uid, fk_calendar, last_sync_date, last_sync_direction, caldav_etag, date_creation";
		$sql .= " FROM ".MAIN_DB_PREFIX."caldav_event_mapping";
		$sql .= " WHERE fk_actioncomm = ".((int) $fk_actioncomm);
		$sql .= " AND fk_calendar = ".((int) $fk_calendar);

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$this->rowid = $obj->rowid;
				$this->fk_actioncomm = $obj->fk_actioncomm;
				$this->caldav_uid = $obj->caldav_uid;
				$this->fk_calendar = $obj->fk_calendar;
				$this->last_sync_date = $this->db->jdate($obj->last_sync_date);
				$this->last_sync_direction = $obj->last_sync_direction;
				$this->caldav_etag = $obj->caldav_etag;
				$this->date_creation = $this->db->jdate($obj->date_creation);
				return 1;
			}
			return 0;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Récupérer tous les mappings d'un événement Dolibarr (multi-calendriers)
	 *
	 * @param  DoliDB $db            Base de données
	 * @param  int    $fk_actioncomm ID événement Dolibarr
	 * @return array                 Tableau de mappings
	 */
	public static function getAllByActionComm($db, $fk_actioncomm)
	{
		$sql = "SELECT rowid, fk_actioncomm, caldav_uid, fk_calendar, last_sync_date, last_sync_direction, caldav_etag, date_creation";
		$sql .= " FROM ".MAIN_DB_PREFIX."caldav_event_mapping";
		$sql .= " WHERE fk_actioncomm = ".((int) $fk_actioncomm);
		$sql .= " ORDER BY fk_calendar ASC, rowid ASC";

		$mappings = array();
		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);
			for ($i = 0; $i < $num; $i++) {
				$obj = $db->fetch_object($resql);
				if ($obj) {
					$map = new CalDAVEventMapping($db);
					$map->rowid = $obj->rowid;
					$map->fk_actioncomm = $obj->fk_actioncomm;
					$map->caldav_uid = $obj->caldav_uid;
					$map->fk_calendar = $obj->fk_calendar;
					$map->last_sync_date = $db->jdate($obj->last_sync_date);
					$map->last_sync_direction = $obj->last_sync_direction;
					$map->caldav_etag = $obj->caldav_etag;
					$map->date_creation = $db->jdate($obj->date_creation);
					$mappings[] = $map;
				}
			}
		}

		return $mappings;
	}

	/**
	 * Récupérer un mapping par UID CalDAV
	 *
	 * @param  string $caldav_uid   UID CalDAV
	 * @param  int    $fk_calendar  ID calendrier CalDAV
	 * @return int                  <0 si KO, 0 si non trouvé, >0 si OK
	 */
	public function fetchByCalDAVUID($caldav_uid, $fk_calendar)
	{
		$sql = "SELECT rowid, fk_actioncomm, caldav_uid, fk_calendar, last_sync_date, last_sync_direction, caldav_etag, date_creation";
		$sql .= " FROM ".MAIN_DB_PREFIX."caldav_event_mapping";
		$sql .= " WHERE caldav_uid = '".$this->db->escape($caldav_uid)."'";
		$sql .= " AND fk_calendar = ".((int) $fk_calendar);

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$this->rowid = $obj->rowid;
				$this->fk_actioncomm = $obj->fk_actioncomm;
				$this->caldav_uid = $obj->caldav_uid;
				$this->fk_calendar = $obj->fk_calendar;
				$this->last_sync_date = $this->db->jdate($obj->last_sync_date);
				$this->last_sync_direction = $obj->last_sync_direction;
				$this->caldav_etag = $obj->caldav_etag;
				$this->date_creation = $this->db->jdate($obj->date_creation);
				return 1;
			}
			return 0;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Supprimer un mapping
	 *
	 * @return int  <0 si KO, >0 si OK
	 */
	public function delete()
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."caldav_event_mapping";
		$sql .= " WHERE rowid = ".((int) $this->rowid);

		$resql = $this->db->query($sql);
		if ($resql) {
			return 1;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Supprimer un mapping par ID ActionComm
	 *
	 * @param  int  $fk_actioncomm  ID événement Dolibarr
	 * @return int                  <0 si KO, >=0 si OK (nombre de lignes supprimées)
	 */
	public static function deleteByActionComm($db, $fk_actioncomm)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."caldav_event_mapping";
		$sql .= " WHERE fk_actioncomm = ".((int) $fk_actioncomm);

		$resql = $db->query($sql);
		if ($resql) {
			return $db->affected_rows($resql);
		} else {
			return -1;
		}
	}

	/**
	 * Récupérer tous les mappings pour un calendrier
	 *
	 * @param  DoliDB $db          Base de données
	 * @param  int    $fk_calendar ID calendrier
	 * @return array               Tableau de mappings
	 */
	public static function getAllByCalendar($db, $fk_calendar)
	{
		$sql = "SELECT rowid, fk_actioncomm, caldav_uid, fk_calendar, last_sync_date, last_sync_direction, caldav_etag, date_creation";
		$sql .= " FROM ".MAIN_DB_PREFIX."caldav_event_mapping";
		$sql .= " WHERE fk_calendar = ".((int) $fk_calendar);
		$sql .= " ORDER BY last_sync_date DESC";

		$mappings = array();
		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);
			for ($i = 0; $i < $num; $i++) {
				$obj = $db->fetch_object($resql);
				$mapping = new CalDAVEventMapping($db);
				$mapping->rowid = $obj->rowid;
				$mapping->fk_actioncomm = $obj->fk_actioncomm;
				$mapping->caldav_uid = $obj->caldav_uid;
				$mapping->fk_calendar = $obj->fk_calendar;
				$mapping->last_sync_date = $db->jdate($obj->last_sync_date);
				$mapping->last_sync_direction = $obj->last_sync_direction;
				$mapping->caldav_etag = $obj->caldav_etag;
				$mapping->date_creation = $db->jdate($obj->date_creation);
				$mappings[] = $mapping;
			}
		}

		return $mappings;
	}

	/**
	 * Vérifier si un événement Dolibarr est synchronisé avec CalDAV
	 *
	 * @param  DoliDB $db            Base de données
	 * @param  int    $fk_actioncomm ID événement Dolibarr
	 * @return bool                  true si synchronisé, false sinon
	 */
	public static function isSynced($db, $fk_actioncomm)
	{
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."caldav_event_mapping";
		$sql .= " WHERE fk_actioncomm = ".((int) $fk_actioncomm);
		$sql .= " LIMIT 1";

		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			return !empty($obj);
		}
		return false;
	}
}
