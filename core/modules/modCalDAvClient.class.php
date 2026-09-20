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
 * 	\defgroup   caldavclient     Module CalDAV Client
 *  \brief      Permet à Dolibarr d'agir comme un client CalDAV pour intégrer des calendriers externes
 *  \file       htdocs/custom/caldavclient/core/modules/modCalDAvClient.class.php
 *  \ingroup    caldavclient
 *  \brief      Description et activation du module CalDAV Client
 *
 *  \license    GPL-3.0-or-later Tout le module est sous GNU General Public License version 3
 *              ou toute version ultérieure ; voir les fichiers LICENSE et COPYING à la racine du module.
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';


/**
 *  Description et activation de la classe pour le module CalDAV Client
 */
class modCalDAvClient extends DolibarrModules
{
	/**
	 *   Constructeur. Définit les noms, constantes, répertoires, boîtes, permissions
	 *
	 *   @param      DoliDB		$db      Gestionnaire de base de données
	 */
	function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		// Identifiant unique pour le module
		// Utilisez un ID libre (voir dans Accueil -> Informations système -> Dolibarr pour la liste des modules utilisés)
		$this->numero = 192076;
		
		// Clé texte utilisée pour identifier le module (pour les permissions, menus, etc...)
		$this->rights_class = 'caldavclient';

		// Famille peut être 'crm','financial','hr','projects','products','ecm','technic','other'
		// Utilisé pour regrouper les modules dans la page de configuration
		$this->family = "technic";
		
		// Position du module dans la famille sur 2 chiffres
		$this->module_position = '90';
		
		// Nom du module (pas d'espace autorisé), utilisé si la chaîne de traduction 'ModuleXXXName' n'est pas trouvée
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		
		// Description du module, utilisée si la chaîne de traduction 'ModuleXXXDesc' n'est pas trouvée
		$this->description = "Synchronisation bidirectionnelle entre Dolibarr et des calendriers CalDAV externes (Nextcloud, etc.).";
		
		// Description longue
		$this->descriptionlong = "Ce module permet à Dolibarr de se connecter et synchroniser avec des serveurs CalDAV externes (comme Nextcloud). Trois modes de synchronisation : Dolibarr → CalDAV (défaut), CalDAV → Dolibarr, ou Bidirectionnel. Les événements sont stockés dans Dolibarr et synchronisés automatiquement.";
		
	// Éditeur
	$this->editor_name = 'MATER Stéphane';
	$this->editor_url = '';
		
	// Valeurs possibles pour version: 'development', 'experimental', 'dolibarr' ou version
	$this->version = '0.22.0';
		// Licence : GNU GPL version 3 ou ultérieure (SPDX GPL-3.0-or-later) — fichiers LICENSE et COPYING à la racine du module.

		// Clé utilisée dans la table llx_const pour sauvegarder le statut activé/désactivé du module
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		
		// Nom du fichier image utilisé pour ce module
		$this->picto = 'technic';
		
		// Définit toutes les parties du module (triggers, login, substitutions, menus, css, etc...)
	$this->module_parts = array(
		'triggers' => 1, // Triggers activés pour la synchronisation
		'hooks' => array('agenda', 'agendalist', 'fullcalendarinterface'), // agenda + liste (filtre auto) + vue agenda JS cœur (hook updateFullcalendarEvents ; pas le module tiers « fullcalendar »)
		// Feuille de style agenda natif (mois / semaine / jour) — sans modifier le cœur Dolibarr
		'css' => array('/custom/caldavclient/css/caldavclient_agenda.css'),
	);

		// Répertoires de données à créer lorsque le module est activé
		$this->dirs = array('/custom/caldavclient/temp');

		// Pages de configuration. Liste des pages php, stockées dans le répertoire admin, pour configurer le module
		$this->config_page_url = array('setup.php@caldavclient');

		// Dépendances
		$this->hidden = false;			// Condition pour masquer le module
		$this->depends = array();		// Liste des IDs de modules qui doivent être activés si ce module est activé
		$this->requiredby = array();	// Liste des IDs de modules à désactiver si celui-ci est désactivé
		$this->conflictwith = array();	// Liste des IDs de modules en conflit avec ce module
		$this->phpmin = array(7,4);	// Version minimale de PHP requise par le module
		$this->need_dolibarr_version = array(16,0);	// Version minimale de Dolibarr requise
		$this->langfiles = array("caldavclient@caldavclient");

		// Constantes
		// Liste des constantes particulières à ajouter lorsque le module est activé
		$this->const = array(
			// Mode de synchronisation
			0 => array(
				'CALDAVCLIENT_SYNC_MODE',
				'chaine',
				'dolibarr_to_caldav',
				'Mode de synchronisation: dolibarr_to_caldav (défaut), caldav_to_dolibarr, ou bidirectional',
				0
			),
			// Intervalle de synchronisation (en minutes)
			1 => array(
				'CALDAVCLIENT_SYNC_INTERVAL',
				'chaine',
				'15',
				'Intervalle de synchronisation automatique (en minutes)',
				0
			),
			// Activer la synchronisation automatique
			2 => array(
				'CALDAVCLIENT_AUTO_SYNC_ENABLED',
				'chaine',
				'1',
				'Activer la synchronisation automatique (1=oui, 0=non)',
				0
			),
			3 => array(
				'CALDAVCLIENT_HIDE_AGENDA_SYSTEMAUTO',
				'chaine',
				'0',
				'Masquer dans l\'agenda natif les événements automatiques système (type systemauto)',
				0
			),
			4 => array(
				'CALDAVCLIENT_AGENDA_TILE_BY_USER',
				'chaine',
				'0',
				'Couleur des tuiles agenda : 1 utilisateur affecté = couleur fiche utilisateur ; plusieurs = couleur configurable',
				0
			),
			5 => array(
				'CALDAVCLIENT_AGENDA_MULTIUSER_COLOR',
				'chaine',
				'B85450',
				'Couleur bordure tuile agenda lorsque plusieurs utilisateurs sont affectés (hex 6 car. sans #)',
				0
			)
		);

		// Tableaux pour ajouter de nouveaux onglets
		$this->tabs = array();

		// Dictionnaires
		if (!isset($conf->caldavclient->enabled)) {
			$conf->caldavclient = new stdClass();
			$conf->caldavclient->enabled = 0;
		}
	$this->dictionaries = array();

	// Boîtes (Widgets pour la page d'accueil)
	$this->boxes = array(
		0 => array(
			'file' => 'caldaveventsbox.php@caldavclient',
			'note' => 'Widget des événements CalDAV à venir',
			'enabledbydefaulton' => 'Home'
		)
	);

	// Permissions
		$this->rights = array();
		$r = 0;
		
		// Permission pour lire les calendriers CalDAV
		$this->rights[$r][0] = 192076001;
		$this->rights[$r][1] = 'Lire les calendriers CalDAV';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'read';
		$this->rights[$r][5] = '';
		$r++;
		
		// Permission pour écrire/modifier les calendriers CalDAV
		$this->rights[$r][0] = 192076002;
		$this->rights[$r][1] = 'Écrire dans les calendriers CalDAV';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'write';
		$this->rights[$r][5] = '';
		$r++;

		// Cron jobs (Tâches programmées pour la synchronisation)
		$this->cronjobs = array();
		$r = 0;
		
		// Job de synchronisation CalDAV
		$this->cronjobs[$r] = array(
			'label' => 'Synchronisation événements CalDAV',
			'jobtype' => 'method',
			'class' => '/caldavclient/class/caldavsync.class.php',
			'objectname' => 'CalDAVSync',
			'method' => 'syncAllCalendars',
			'parameters' => '',
			'comment' => 'Synchronise les événements entre Dolibarr et les serveurs CalDAV selon le mode configuré',
			'frequency' => 1, // Fréquence en minutes (sera surchargée par la config)
			'unitfrequency' => 60, // 1 = secondes, 60 = minutes, 3600 = heures, 86400 = jours
			'status' => 0, // 0=désactivé par défaut, 1=activé
			// Note: en mode Dolibarr -> CalDAV, la synchro est déclenchée par trigger (création/modification),
			// donc on ne veut pas lancer un batch régulier.
			'test' => '$conf->caldavclient->enabled && !empty($conf->global->CALDAVCLIENT_AUTO_SYNC_ENABLED) && (!isset($conf->global->CALDAVCLIENT_SYNC_MODE) || $conf->global->CALDAVCLIENT_SYNC_MODE != \"dolibarr_to_caldav\")',
			'priority' => 50
		);
		$r++;

		// Entrées du menu principal
		$this->menu = array();
		$r = 0;
		
		// Menu dans l'agenda pour configurer les connexions CalDAV
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=agenda',
			'type' => 'left',
			'titre' => 'CalDAV Client',
			'mainmenu' => 'agenda',
			'url' => '/custom/caldavclient/admin/setup.php?mainmenu=agenda',
			'langs' => 'caldavclient@caldavclient',
			'position' => 200,
			'enabled' => '$conf->caldavclient->enabled',
			'perms' => '$user->rights->caldavclient->read',
			'target' => '',
			'user' => 0
		);
	}

	/**
	 *		Fonction appelée lorsque le module est activé.
	 *		La fonction init ajoute les constantes, boîtes, permissions et menus (définis dans le constructeur) dans la base de données Dolibarr.
	 *		Elle crée également les répertoires de données
	 *
	 *      @param      string	$options    Options lors de l'activation du module ('', 'noboxes')
	 *      @return     int             	1 si OK, 0 si KO
	 */
	function init($options = '')
	{
		global $langs;
		$sql = array();
		
		// Charger les fichiers SQL pour créer les tables nécessaires
		$result = $this->_load_tables('/custom/caldavclient/sql/');
		if ($result < 0) {
			return -1;
		}

		// Appliquer automatiquement les correctifs SQL nécessaires (sans action manuelle).
		// Important: on ne doit pas dépendre d'un script SQL "update_*.sql" lancé dans un ordre de tri.
		$this->applyDatabaseFixes();

		return $this->_init($sql, $options);
	}

	/**
	 * Appliquer des correctifs de schéma (idempotents)
	 *
	 * @return void
	 */
	private function applyDatabaseFixes()
	{
		include_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

		// Base déjà créée avant cette version : CREATE TABLE IF NOT EXISTS ne rajoute pas les colonnes.
		require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/lib/caldavclient.lib.php';
		caldavclient_ensure_connection_ssl_column($this->db);
		$this->ensureCalDAVCalendarsSchema();
		$this->removeEventMappingActioncommForeignKey();
		$this->ensureEventMappingMultiCalendarIndex();
	}

	/**
	 * Vérifie l'existence d'une colonne (information_schema, MySQL/MariaDB).
	 *
	 * @param  string $table  Nom complet de table (ex: llx_caldav_calendars)
	 * @param  string $column Nom de colonne
	 * @return bool
	 */
	private function dbColumnExists($table, $column)
	{
		$sql = "SELECT COUNT(*) as nb FROM information_schema.COLUMNS";
		$sql .= " WHERE TABLE_SCHEMA = DATABASE()";
		$sql .= " AND TABLE_NAME = '".$this->db->escape($table)."'";
		$sql .= " AND COLUMN_NAME = '".$this->db->escape($column)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		return ($obj && (int) $obj->nb > 0);
	}

	/**
	 * Vérifie l'existence d'un index.
	 *
	 * @param  string $table     Nom complet de table
	 * @param  string $indexName Nom de l'index
	 * @return bool
	 */
	private function dbIndexExists($table, $indexName)
	{
		$sql = "SELECT COUNT(*) as nb FROM information_schema.STATISTICS";
		$sql .= " WHERE TABLE_SCHEMA = DATABASE()";
		$sql .= " AND TABLE_NAME = '".$this->db->escape($table)."'";
		$sql .= " AND INDEX_NAME = '".$this->db->escape($indexName)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		return ($obj && (int) $obj->nb > 0);
	}

	/**
	 * Ajoute les colonnes manquantes sur llx_caldav_calendars (anciennes bases).
	 *
	 * @return void
	 */
	private function ensureCalDAVCalendarsSchema()
	{
		$table = MAIN_DB_PREFIX."caldav_calendars";
		if (!$this->dbColumnExists($table, 'rowid')) {
			// Table absente : sera créée par _load_tables
			return;
		}

		if (!$this->dbColumnExists($table, 'visibility_type')) {
			$sql = "ALTER TABLE ".$table." ADD COLUMN visibility_type VARCHAR(32) DEFAULT 'public' COMMENT 'public ou individual' AFTER active_by_default";
			if ($this->db->query($sql)) {
				dol_syslog("CalDAVClient: colonne visibility_type ajoutée sur ".$table, LOG_INFO);
			} else {
				dol_syslog("CalDAVClient: échec ADD visibility_type (".$this->db->lasterror().")", LOG_ERR);
			}
		}

		if (!$this->dbColumnExists($table, 'is_default_target')) {
			$sql = "ALTER TABLE ".$table." ADD COLUMN is_default_target TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Cible par défaut sync Dolibarr -> CalDAV' AFTER visibility_type";
			if ($this->db->query($sql)) {
				dol_syslog("CalDAVClient: colonne is_default_target ajoutée sur ".$table, LOG_INFO);
			} else {
				dol_syslog("CalDAVClient: échec ADD is_default_target (".$this->db->lasterror().")", LOG_ERR);
			}
		}

		if (!$this->dbColumnExists($table, 'fk_user_owner')) {
			$sql = "ALTER TABLE ".$table." ADD COLUMN fk_user_owner INTEGER DEFAULT NULL COMMENT 'Utilisateur Dolibarr propriétaire' AFTER is_default_target";
			if ($this->db->query($sql)) {
				dol_syslog("CalDAVClient: colonne fk_user_owner ajoutée sur ".$table, LOG_INFO);
			} else {
				dol_syslog("CalDAVClient: échec ADD fk_user_owner (".$this->db->lasterror().")", LOG_ERR);
			}
		}

		if ($this->dbColumnExists($table, 'fk_user_owner') && !$this->dbIndexExists($table, 'idx_caldav_calendars_owner')) {
			$sql = "CREATE INDEX idx_caldav_calendars_owner ON ".$table." (fk_user_owner, enabled)";
			if ($this->db->query($sql)) {
				dol_syslog("CalDAVClient: index idx_caldav_calendars_owner créé sur ".$table, LOG_INFO);
			}
		}
	}

	/**
	 * Supprime la FK llx_caldav_event_mapping.fk_actioncomm -> llx_actioncomm si elle existe.
	 *
	 * @return void
	 */
	private function removeEventMappingActioncommForeignKey()
	{
		$table = MAIN_DB_PREFIX."caldav_event_mapping";
		if (!$this->dbColumnExists($table, 'rowid')) {
			return;
		}

		$sql = "SELECT rc.CONSTRAINT_NAME as cname, rc.DELETE_RULE as delete_rule";
		$sql .= " FROM information_schema.KEY_COLUMN_USAGE kcu";
		$sql .= " INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc";
		$sql .= " ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME";
		$sql .= " WHERE kcu.CONSTRAINT_SCHEMA = DATABASE()";
		$sql .= " AND kcu.TABLE_NAME = '".$this->db->escape($table)."'";
		$sql .= " AND kcu.COLUMN_NAME = 'fk_actioncomm'";
		$sql .= " AND kcu.REFERENCED_TABLE_NAME IS NOT NULL";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog("CalDAVClient: removeEventMappingActioncommForeignKey - information_schema (".$this->db->lasterror().")", LOG_WARNING);
			return;
		}

		$obj = $this->db->fetch_object($resql);
		if (empty($obj) || empty($obj->cname)) {
			return;
		}

		$deleteRule = strtoupper((string) $obj->delete_rule);
		$constraintName = (string) $obj->cname;
		$alterDrop = "ALTER TABLE ".$table." DROP FOREIGN KEY ".$constraintName;
		$this->db->query($alterDrop);
		dol_syslog("CalDAVClient: FK ".$constraintName." supprimée sur ".$table.".fk_actioncomm (delete_rule=".$deleteRule.")", LOG_INFO);
	}

	/**
	 * Remplace l'ancien UNIQUE (fk_actioncomm seul) par (fk_actioncomm, fk_calendar) si besoin.
	 *
	 * @return void
	 */
	private function ensureEventMappingMultiCalendarIndex()
	{
		$table = MAIN_DB_PREFIX."caldav_event_mapping";
		if (!$this->dbColumnExists($table, 'rowid')) {
			return;
		}

		$oldUk = 'uk_caldav_event_mapping_actioncomm';
		$newUk = 'uk_caldav_event_mapping_actioncomm_calendar';

		if ($this->dbIndexExists($table, $oldUk) && !$this->dbIndexExists($table, $newUk)) {
			$sql = "ALTER TABLE ".$table." DROP INDEX ".$oldUk;
			if ($this->db->query($sql)) {
				dol_syslog("CalDAVClient: index ".$oldUk." supprimé sur ".$table, LOG_INFO);
			} else {
				dol_syslog("CalDAVClient: échec DROP INDEX ".$oldUk." (".$this->db->lasterror().")", LOG_ERR);
				return;
			}
		}

		if (!$this->dbIndexExists($table, $newUk)) {
			$sql = "ALTER TABLE ".$table." ADD UNIQUE KEY ".$newUk." (fk_actioncomm, fk_calendar)";
			if ($this->db->query($sql)) {
				dol_syslog("CalDAVClient: UNIQUE ".$newUk." ajouté sur ".$table, LOG_INFO);
			} else {
				dol_syslog("CalDAVClient: échec ADD UNIQUE ".$newUk." (".$this->db->lasterror().")", LOG_ERR);
			}
		}
	}

	/**
	 *		Fonction appelée lorsque le module est désactivé.
	 *      Supprime de la base de données les constantes, boîtes et permissions de la base de données Dolibarr.
	 *		Les répertoires de données ne sont pas supprimés
	 *
	 *      @param      string	$options    Options lors de la désactivation du module ('', 'noboxes')
	 *      @return     int             	1 si OK, 0 si KO
	 */
	function remove($options = '')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}
}
