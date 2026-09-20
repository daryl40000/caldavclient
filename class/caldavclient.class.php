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
 * \file       htdocs/custom/caldavclient/class/caldavclient.class.php
 * \ingroup    caldavclient
 * \brief      Classe pour gérer les connexions CalDAV
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';

/**
 * Classe pour gérer les connexions CalDAV
 */
class CalDAVConnection extends CommonObject
{
	/**
	 * @var int ID de la connexion
	 */
	public $id;

	/**
	 * @var string Nom de la connexion
	 */
	public $name;

	/**
	 * @var string URL du serveur CalDAV
	 */
	public $url;

	/**
	 * @var string Nom d'utilisateur
	 */
	public $username;

	/**
	 * @var string Mot de passe (chiffré)
	 */
	public $password;

	/**
	 * @var string Chemin du calendrier
	 */
	public $calendar_path;

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
	 * Constructeur
	 *
	 * @param DoliDB $db Gestionnaire de base de données
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->table_element = 'caldav_connections';
		$this->element = 'caldavconnection';
	}

	/**
	 * Créer une connexion CalDAV dans la base de données
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
		if (empty($this->name)) {
			$this->error = "Le nom est obligatoire";
			return -1;
		}
		if (empty($this->url)) {
			$this->error = "L'URL est obligatoire";
			return -1;
		}
		if (empty($this->username)) {
			$this->error = "Le nom d'utilisateur est obligatoire";
			return -1;
		}

		// Chiffrer le mot de passe
		if (!empty($this->password)) {
			$this->password = dol_encode($this->password);
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

		$this->db->begin();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."caldav_connections (";
		$sql .= "name, url, username, password, calendar_path, color, enabled, active_by_default, entity, date_creation, fk_user_author";
		$sql .= ") VALUES (";
		$sql .= "'".$this->db->escape($this->name)."',";
		$sql .= "'".$this->db->escape($this->url)."',";
		$sql .= "'".$this->db->escape($this->username)."',";
		$sql .= "'".$this->db->escape($this->password)."',";
		$sql .= ($this->calendar_path ? "'".$this->db->escape($this->calendar_path)."'" : "NULL").",";
		$sql .= "'".$this->db->escape($this->color)."',";
		$sql .= ((int) $this->enabled).",";
		$sql .= ((int) $this->active_by_default).",";
		$sql .= ((int) $this->entity).",";
		$sql .= "'".$this->db->idate(dol_now())."',";
		$sql .= ($user ? (int) $user->id : "NULL");
		$sql .= ")";

		$resql = $this->db->query($sql);
		if ($resql) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."caldav_connections");
			$this->db->commit();
			return $this->id;
		} else {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Charger une connexion depuis la base de données
	 *
	 * @param  int    $id   ID de la connexion
	 * @return int          <0 si KO, >0 si OK
	 */
	public function fetch($id)
	{
		$sql = "SELECT rowid, name, url, username, password, calendar_path, color, enabled, active_by_default, entity";
		$sql .= " FROM ".MAIN_DB_PREFIX."caldav_connections";
		$sql .= " WHERE rowid = ".((int) $id);

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$this->id = $obj->rowid;
				$this->name = $obj->name;
				$this->url = $obj->url;
				$this->username = $obj->username;
				$this->password = dol_decode($obj->password); // Déchiffrer le mot de passe
				$this->calendar_path = $obj->calendar_path;
				$this->color = $obj->color;
				$this->enabled = $obj->enabled;
				$this->active_by_default = $obj->active_by_default;
				$this->entity = $obj->entity;
				return 1;
			} else {
				$this->error = "Connexion non trouvée";
				return 0;
			}
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Mettre à jour une connexion
	 *
	 * @param  User    $user   Utilisateur qui modifie
	 * @param  int    $notrigger  0=lance les triggers, 1=pas de trigger
	 * @return int             <0 si KO, >0 si OK
	 */
	public function update($user = null, $notrigger = 0)
	{
		$error = 0;

		// Chiffrer le mot de passe s'il a été modifié
		if (!empty($this->password) && !preg_match('/^[a-f0-9]{32}$/', $this->password)) {
			$this->password = dol_encode($this->password);
		}

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."caldav_connections SET";
		$sql .= " name = '".$this->db->escape($this->name)."',";
		$sql .= " url = '".$this->db->escape($this->url)."',";
		$sql .= " username = '".$this->db->escape($this->username)."',";
		if (!empty($this->password)) {
			$sql .= " password = '".$this->db->escape($this->password)."',";
		}
		$sql .= " calendar_path = ".($this->calendar_path ? "'".$this->db->escape($this->calendar_path)."'" : "NULL").",";
		$sql .= " color = '".$this->db->escape($this->color)."',";
		$sql .= " enabled = ".((int) $this->enabled).",";
		$sql .= " active_by_default = ".((int) $this->active_by_default).",";
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
	 * Supprimer une connexion
	 *
	 * @param  int    $notrigger  0=lance les triggers, 1=pas de trigger
	 * @return int                <0 si KO, >0 si OK
	 */
	public function delete($notrigger = 0)
	{
		$this->db->begin();

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."caldav_connections";
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
	 * Récupérer toutes les connexions actives
	 *
	 * @param  int    $entity   Entité Dolibarr (optionnel)
	 * @return array            Tableau des connexions
	 */
	public static function getAllActive($entity = null)
	{
		global $db, $conf;

		if ($entity === null) {
			$entity = $conf->entity;
		}

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."caldav_connections";
		$sql .= " WHERE enabled = 1";
		$sql .= " AND entity = ".((int) $entity);
		$sql .= " ORDER BY name";

		$connections = array();
		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);
			for ($i = 0; $i < $num; $i++) {
				$obj = $db->fetch_object($resql);
				$conn = new CalDAVConnection($db);
				$conn->fetch($obj->rowid);
				$connections[] = $conn;
			}
		}

		return $connections;
	}
}
