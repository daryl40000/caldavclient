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
 * \file    admin/about.php
 * \ingroup caldavclient
 * \brief   Page "A propos" du module CalDAV Client
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Main include failed");
}

require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/lib/caldavclient.lib.php';

$langs->loadLangs(array("admin", "caldavclient@caldavclient"));

if (!$user->admin) {
	accessforbidden();
}

$moduleVersion = '0.20.0';
$versionFile = DOL_DOCUMENT_ROOT.'/custom/caldavclient/VERSION';
if (is_readable($versionFile)) {
	$versionContent = trim((string) @file_get_contents($versionFile));
	if ($versionContent !== '') {
		$moduleVersion = $versionContent;
	}
}

llxHeader('', "CalDAV Client - A propos");

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre("A propos - CalDAV Client", $linkback, 'title_setup');

$head = caldavclient_admin_prepare_head();
print dol_get_fiche_head($head, 'about', $langs->trans("CalDAVClientSetup"), -1, 'caldavclient@caldavclient');

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">Informations du module</td></tr>';

print '<tr class="oddeven">';
print '<td>Nom</td>';
print '<td>CalDAV Client</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Version</td>';
print '<td>'.dol_escape_htmltag($moduleVersion).'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Auteur</td>';
print '<td>MATER Stéphane</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Description</td>';
print '<td>Synchronisation entre Dolibarr et des calendriers CalDAV externes (Nextcloud, iCloud, etc.).</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("License").'</td>';
print '<td>'.dol_escape_htmltag($langs->trans("ModuleCalDAvClientLicense"));
print ' — <a href="'.DOL_URL_ROOT.'/custom/caldavclient/LICENSE" target="_blank" rel="noopener noreferrer">LICENSE</a>';
print ' / <a href="'.DOL_URL_ROOT.'/custom/caldavclient/COPYING" target="_blank" rel="noopener noreferrer">COPYING</a>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Fichiers de documentation</td>';
print '<td>';
print '<a href="'.DOL_URL_ROOT.'/custom/caldavclient/README.md" target="_blank" rel="noopener noreferrer">README.md</a>';
print ' - ';
print '<a href="'.DOL_URL_ROOT.'/custom/caldavclient/CHANGELOG.md" target="_blank" rel="noopener noreferrer">CHANGELOG.md</a>';
print '</td>';
print '</tr>';

print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();

