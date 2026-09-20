<?php
/* Copyright (C) 2025-2026  MATER Stéphane
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/caldavclient.lib.php
 * \ingroup caldavclient
 * \brief   Fonctions utilitaires pour le module CalDAV Client
 */

/**
 * Préparer les onglets de configuration du module
 *
 * @return array  Tableau des onglets
 */
function caldavclient_admin_prepare_head()
{
	global $langs, $conf;

	$langs->load("caldavclient@caldavclient");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/caldavclient/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/caldavclient/admin/sync.php", 1);
	$head[$h][1] = $langs->trans("Synchronization");
	$head[$h][2] = 'sync';
	$h++;

	$head[$h][0] = dol_buildpath("/caldavclient/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'caldavclient');

	return $head;
}

/**
 * Ajoute la colonne ssl_verify sur les connexions si elle manque
 * (mise à jour depuis une version antérieure à 0.21.0).
 *
 * @param  DoliDB $db Gestionnaire de base de données
 * @return void
 */
function caldavclient_ensure_connection_ssl_column($db)
{
	if (empty($db) || !is_object($db)) {
		return;
	}

	$table = MAIN_DB_PREFIX.'caldav_connections';

	$sql = "SELECT COUNT(*) as nb FROM information_schema.TABLES";
	$sql .= " WHERE TABLE_SCHEMA = DATABASE()";
	$sql .= " AND TABLE_NAME = '".$db->escape($table)."'";
	$resql = $db->query($sql);
	if (!$resql) {
		return;
	}
	$obj = $db->fetch_object($resql);
	if (!$obj || (int) $obj->nb === 0) {
		return;
	}

	$sql = "SELECT COUNT(*) as nb FROM information_schema.COLUMNS";
	$sql .= " WHERE TABLE_SCHEMA = DATABASE()";
	$sql .= " AND TABLE_NAME = '".$db->escape($table)."'";
	$sql .= " AND COLUMN_NAME = 'ssl_verify'";
	$resql = $db->query($sql);
	if (!$resql) {
		return;
	}
	$obj = $db->fetch_object($resql);
	if ($obj && (int) $obj->nb > 0) {
		return;
	}

	$sql = "ALTER TABLE ".$table." ADD COLUMN ssl_verify TINYINT(1) NOT NULL DEFAULT 1";
	$sql .= " COMMENT '1=vérifier le certificat SSL, 0=désactiver (serveur de test)' AFTER active_by_default";
	if ($db->query($sql)) {
		dol_syslog("CalDAVClient: colonne ssl_verify ajoutée sur ".$table, LOG_INFO);
	} else {
		dol_syslog("CalDAVClient: échec ADD ssl_verify (".$db->lasterror().")", LOG_ERR);
	}
}
