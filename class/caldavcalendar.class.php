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
 * \file       htdocs/custom/caldavclient/class/caldavcalendar.class.php
 * \ingroup    caldavclient
 * \brief      Classe pour gérer les calendriers CalDAV
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Classe pour gérer les calendriers CalDAV
 */
class CalDAVCalendar extends CommonObject
{
	/**
	 * @var int ID du calendrier
	 */
	public $id;

	/**
	 * @var int ID de la connexion CalDAV
	 */
	public $fk_connection;

	/**
	 * @var string Nom du calendrier
	 */
	public $name;

	/**
	 * @var string Nom d'affichage du calendrier
	 */
	public $displayname;

	/**
	 * @var string URL du calendrier
	 */
	public $url;

	/**
	 * @var string Couleur d'affichage
	 */
	public $color;

	/**
	 * @var int Actif (1) ou inactif (0)
	 */
	public $enabled;

	/**
	 * @var int Actif par défaut dans l'agenda
	 */
	public $active_by_default;

	/**
	 * @var int Entité Dolibarr
	 */
	public $entity;

	/**
	 * @var string Type de visibilité ('public' ou 'individual')
	 */
	public $visibility_type;

	/**
	 * @var int Calendrier cible par défaut pour Dolibarr -> CalDAV (0/1)
	 */
	public $is_default_target = 0;

	/**
	 * @var int|null Utilisateur Dolibarr associé à ce calendrier (propriétaire/organisateur)
	 */
	public $fk_user_owner = null;

	/**
	 * Constructeur
	 *
	 * @param DoliDB $db Gestionnaire de base de données
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->table_element = 'caldav_calendars';
		$this->element = 'caldavcalendar';
	}

	/**
	 * Créer un calendrier dans la base de données
	 *
	 * @param  User    $user   Utilisateur qui crée
	 * @param  int     $notrigger 0=lance les triggers, 1=pas de trigger
	 * @return int              <0 si KO, >0 si OK
	 */
	public function create($user = null, $notrigger = 0)
	{
		global $conf;

		$error = 0;

		// Vérification des paramètres
		if (empty($this->fk_connection)) {
			$this->error = "L'ID de connexion est obligatoire";
			return -1;
		}
		if (empty($this->name)) {
			$this->error = "Le nom est obligatoire";
			return -1;
		}
		if (empty($this->url)) {
			$this->error = "L'URL est obligatoire";
			return -1;
		}

		// Valeurs par défaut
		if (empty($this->entity)) {
			$this->entity = $conf->entity;
		}
		if (empty($this->color)) {
			$this->color = 'BECEDD';
		}
		if ($this->enabled === null) {
			$this->enabled = 1;
		}
		if ($this->active_by_default === null) {
			$this->active_by_default = 1;
		}
		if (empty($this->visibility_type)) {
			$this->visibility_type = 'public';
		}

		$this->db->begin();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."caldav_calendars (";
		$sql .= "fk_connection, name, displayname, url, color, enabled, active_by_default, visibility_type, fk_user_owner, entity, date_creation, fk_user_author";
		$sql .= ") VALUES (";
		$sql .= ((int) $this->fk_connection).",";
		$sql .= "'".$this->db->escape($this->name)."',";
		$sql .= ($this->displayname ? "'".$this->db->escape($this->displayname)."'" : "NULL").",";
		$sql .= "'".$this->db->escape($this->url)."',";
		$sql .= "'".$this->db->escape($this->color)."',";
		$sql .= ((int) $this->enabled).",";
		$sql .= ((int) $this->active_by_default).",";
		$sql .= "'".$this->db->escape($this->visibility_type)."',";
		$sql .= (!empty($this->fk_user_owner) ? ((int) $this->fk_user_owner) : "NULL").",";
		$sql .= ((int) $this->entity).",";
		$sql .= "'".$this->db->idate(dol_now())."',";
		$sql .= ($user ? (int) $user->id : "NULL");
		$sql .= ")";

		$resql = $this->db->query($sql);
		if ($resql) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."caldav_calendars");
			$this->db->commit();
			return $this->id;
		} else {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Charger un calendrier depuis la base de données
	 *
	 * @param  int    $id   ID du calendrier
	 * @return int          <0 si KO, >0 si OK
	 */
	public function fetch($id)
	{
		// Note: is_default_target peut ne pas exister si la migration SQL n'a pas été appliquée.
		// On le récupère via une sous-requête d'information_schema quand c'est possible.
		$sql = "SELECT rowid, fk_connection, name, displayname, url, color, enabled, active_by_default, visibility_type, entity";
		$sql .= ", IFNULL(is_default_target, 0) as is_default_target";
		$sql .= ", fk_user_owner";
		$sql .= " FROM ".MAIN_DB_PREFIX."caldav_calendars";
		$sql .= " WHERE rowid = ".((int) $id);

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$this->id = $obj->rowid;
				$this->fk_connection = $obj->fk_connection;
				$this->name = $obj->name;
				$this->displayname = $obj->displayname;
				$this->url = $obj->url;
				$this->color = $obj->color;
				$this->enabled = $obj->enabled;
				$this->active_by_default = $obj->active_by_default;
				$this->visibility_type = $obj->visibility_type ? $obj->visibility_type : 'public';
				$this->entity = $obj->entity;
				$this->is_default_target = isset($obj->is_default_target) ? (int) $obj->is_default_target : 0;
				$this->fk_user_owner = isset($obj->fk_user_owner) ? (int) $obj->fk_user_owner : null;
				return 1;
			} else {
				$this->error = "Calendrier non trouvé";
				return 0;
			}
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Mettre à jour un calendrier
	 *
	 * @param  User    $user   Utilisateur qui modifie
	 * @param  int    $notrigger  0=lance les triggers, 1=pas de trigger
	 * @return int             <0 si KO, >0 si OK
	 */
	public function update($user = null, $notrigger = 0)
	{
		$this->db->begin();
		
		// S'assurer que la couleur n'est pas vide
		if (empty($this->color)) {
			$this->color = 'BECEDD';
		}
		
		// S'assurer que visibility_type a une valeur valide
		if (empty($this->visibility_type) || !in_array($this->visibility_type, array('public', 'individual'))) {
			$this->visibility_type = 'public';
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX."caldav_calendars SET";
		$sql .= " name = '".$this->db->escape($this->name)."',";
		$sql .= " displayname = ".($this->displayname ? "'".$this->db->escape($this->displayname)."'" : "NULL").",";
		$sql .= " url = '".$this->db->escape($this->url)."',";
		$sql .= " color = '".$this->db->escape($this->color)."',";
		$sql .= " enabled = ".((int) $this->enabled).",";
		$sql .= " active_by_default = ".((int) $this->active_by_default).",";
		$sql .= " visibility_type = '".$this->db->escape($this->visibility_type)."',";
		$sql .= " fk_user_owner = ".(!empty($this->fk_user_owner) ? ((int) $this->fk_user_owner) : "NULL").",";
		$sql .= " fk_user_modif = ".($user ? (int) $user->id : "NULL");
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			$this->db->commit();
			return 1;
		} else {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Supprimer un calendrier
	 *
	 * @param  int    $notrigger  0=lance les triggers, 1=pas de trigger
	 * @return int                <0 si KO, >0 si OK
	 */
	public function delete($notrigger = 0)
	{
		$this->db->begin();

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."caldav_calendars";
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			$this->db->commit();
			return 1;
		} else {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Récupérer tous les calendriers actifs d'une connexion
	 *
	 * @param  int    $fk_connection   ID de la connexion
	 * @param  int    $entity          Entité Dolibarr (optionnel)
	 * @return array                   Tableau des calendriers
	 */
	public static function getAllActiveByConnection($fk_connection, $entity = null)
	{
		global $db, $conf;

		if ($entity === null) {
			$entity = $conf->entity;
		}

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."caldav_calendars";
		$sql .= " WHERE fk_connection = ".((int) $fk_connection);
		$sql .= " AND enabled = 1";
		$sql .= " AND entity = ".((int) $entity);
		$sql .= " ORDER BY name";

		$calendars = array();
		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);
			for ($i = 0; $i < $num; $i++) {
				$obj = $db->fetch_object($resql);
				$calendar = new CalDAVCalendar($db);
				$calendar->fetch($obj->rowid);
				$calendars[] = $calendar;
			}
		}

		return $calendars;
	}

	/**
	 * Récupérer tous les calendriers actifs (visibles pour l'utilisateur courant)
	 *
	 * @param  int    $entity   Entité Dolibarr (optionnel)
	 * @param  int    $user_id  ID de l'utilisateur (optionnel, sinon utilise $user global)
	 * @return array            Tableau des calendriers
	 */
	public static function getAllActive($entity = null, $user_id = null)
	{
		global $db, $conf, $user;

		if ($entity === null) {
			$entity = $conf->entity;
		}
		
		if ($user_id === null && isset($user)) {
			$user_id = $user->id;
		}

		// Récupérer les calendriers publics OU les calendriers individuels assignés à l'utilisateur
		$sql = "SELECT DISTINCT c.rowid";
		$sql .= " FROM ".MAIN_DB_PREFIX."caldav_calendars as c";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."caldav_calendar_users as cu ON c.rowid = cu.fk_calendar";
		$sql .= " WHERE c.enabled = 1";
		$sql .= " AND c.entity = ".((int) $entity);
		$sql .= " AND (c.visibility_type = 'public'";
		if ($user_id) {
			$sql .= " OR (c.visibility_type = 'individual' AND cu.fk_user = ".((int) $user_id).")";
		}
		$sql .= ")";
		$sql .= " ORDER BY c.name";

		$calendars = array();
		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);
			for ($i = 0; $i < $num; $i++) {
				$obj = $db->fetch_object($resql);
				$calendar = new CalDAVCalendar($db);
				$calendar->fetch($obj->rowid);
				$calendars[] = $calendar;
			}
		}

		return $calendars;
	}

	/**
	 * Assigner des utilisateurs à un calendrier (pour visibilité 'individual')
	 *
	 * @param  array  $user_ids  Tableau des IDs utilisateurs
	 * @return int                <0 si KO, >0 si OK
	 */
	public function setAssignedUsers($user_ids)
	{
		// Supprimer les anciennes assignations
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."caldav_calendar_users";
		$sql .= " WHERE fk_calendar = ".((int) $this->id);
		
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		
		// Ajouter les nouvelles assignations
		if (!empty($user_ids) && is_array($user_ids)) {
			foreach ($user_ids as $user_id) {
				$sql = "INSERT INTO ".MAIN_DB_PREFIX."caldav_calendar_users";
				$sql .= " (fk_calendar, fk_user, date_creation)";
				$sql .= " VALUES";
				$sql .= " (".((int) $this->id).", ".((int) $user_id).", '".$this->db->idate(dol_now())."')";
				
				$resql = $this->db->query($sql);
				if (!$resql) {
					$this->error = $this->db->lasterror();
					return -1;
				}
			}
		}
		
		return 1;
	}

	/**
	 * Récupérer les utilisateurs assignés à un calendrier
	 *
	 * @return array  Tableau des IDs utilisateurs
	 */
	public function getAssignedUsers()
	{
		$user_ids = array();
		
		$sql = "SELECT fk_user";
		$sql .= " FROM ".MAIN_DB_PREFIX."caldav_calendar_users";
		$sql .= " WHERE fk_calendar = ".((int) $this->id);
		
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			for ($i = 0; $i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$user_ids[] = $obj->fk_user;
			}
		}
		
		return $user_ids;
	}
}
