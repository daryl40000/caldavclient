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
