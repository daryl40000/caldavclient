<?php
/* Copyright (C) 2025-2026  MATER Stéphane
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/sync.php
 * \ingroup caldavclient
 * \brief   Page de configuration de la synchronisation CalDAV
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/lib/caldavclient.lib.php';
dol_include_once('/caldavclient/class/caldavsync.class.php');

// Langues
$langs->loadLangs(array("admin", "caldavclient@caldavclient"));

// Vérification des droits
if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

if ($action == 'update') {
	$sync_mode = GETPOST('CALDAVCLIENT_SYNC_MODE', 'aZ09');
	$sync_interval = GETPOST('CALDAVCLIENT_SYNC_INTERVAL', 'int');
	$auto_sync_enabled = GETPOST('CALDAVCLIENT_AUTO_SYNC_ENABLED', 'int');

	// Valider le mode de synchronisation
	if (!in_array($sync_mode, array('dolibarr_to_caldav', 'caldav_to_dolibarr', 'bidirectional'))) {
		setEventMessages($langs->trans("InvalidSyncMode"), null, 'errors');
	} else {
		dolibarr_set_const($db, 'CALDAVCLIENT_SYNC_MODE', $sync_mode, 'chaine', 0, '', $conf->entity);
	}

	// Valider l'intervalle (min 5 minutes, max 1440 minutes = 24h)
	if ($sync_interval < 5 || $sync_interval > 1440) {
		setEventMessages($langs->trans("InvalidSyncInterval"), null, 'errors');
	} else {
		dolibarr_set_const($db, 'CALDAVCLIENT_SYNC_INTERVAL', $sync_interval, 'chaine', 0, '', $conf->entity);
	}

	// Activer/désactiver la synchronisation automatique
	dolibarr_set_const($db, 'CALDAVCLIENT_AUTO_SYNC_ENABLED', $auto_sync_enabled, 'chaine', 0, '', $conf->entity);

	setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}

if ($action == 'sync_now') {
	// Lancer une synchronisation manuelle
	try {
		$sync = new CalDAVSync($db);
		$results = $sync->manualSync();

		if (!empty($results['errors'])) {
			setEventMessages($langs->trans("SyncErrors").': '.count($results['details']), null, 'warnings');
		} else {
			setEventMessages($langs->trans("SyncSuccess").': '.$results['success'].' calendrier(s)', null, 'mesgs');
		}
	} catch (Throwable $e) {
		$results = array('success' => 0, 'errors' => 1, 'details' => array($e->getMessage()));
		setEventMessages($langs->trans("SyncErrors").': 1', array($e->getMessage()), 'errors');
		dol_syslog("CalDAV sync_now fatal: ".$e->getMessage(), LOG_ERR);
	}
	// Ne pas rediriger: on affiche les détails sur la page
}

/*
 * View
 */

$page_name = "CalDAVClientSetup";
llxHeader('', $langs->trans($page_name));

// Configuration du module
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("CalDAVSyncConfiguration"), $linkback, 'title_setup');

// Onglets
$head = caldavclient_admin_prepare_head();
print dol_get_fiche_head($head, 'sync', $langs->trans("CalDAVClientSetup"), -1, 'caldavclient@caldavclient');

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

// Mode de synchronisation
print '<tr class="oddeven">';
print '<td>';
print '<span class="fieldrequired">'.$langs->trans("SyncMode").'</span>';
print '<br><span class="opacitymedium">'.$langs->trans("SyncModeHelp").'</span>';
print '</td>';
print '<td>';

$current_mode = !empty($conf->global->CALDAVCLIENT_SYNC_MODE) ? $conf->global->CALDAVCLIENT_SYNC_MODE : 'dolibarr_to_caldav';

print '<select name="CALDAVCLIENT_SYNC_MODE" class="flat minwidth200">';
print '<option value="dolibarr_to_caldav"'.($current_mode == 'dolibarr_to_caldav' ? ' selected' : '').'>';
print $langs->trans("SyncModeDolibarrToCalDAV").' ('.$langs->trans("Default").')';
print '</option>';
print '<option value="caldav_to_dolibarr"'.($current_mode == 'caldav_to_dolibarr' ? ' selected' : '').'>';
print $langs->trans("SyncModeCalDAVToDolibarr");
print '</option>';
print '<option value="bidirectional"'.($current_mode == 'bidirectional' ? ' selected' : '').'>';
print $langs->trans("SyncModeBidirectional");
print '</option>';
print '</select>';

print '</td>';
print '</tr>';

// Intervalle de synchronisation
print '<tr class="oddeven">';
print '<td>';
print '<span class="fieldrequired">'.$langs->trans("SyncInterval").'</span>';
print '<br><span class="opacitymedium">'.$langs->trans("SyncIntervalHelp").'</span>';
print '</td>';
print '<td>';

$current_interval = !empty($conf->global->CALDAVCLIENT_SYNC_INTERVAL) ? $conf->global->CALDAVCLIENT_SYNC_INTERVAL : 15;

print '<input type="number" name="CALDAVCLIENT_SYNC_INTERVAL" value="'.$current_interval.'" min="5" max="1440" class="flat minwidth100">';
print ' '.$langs->trans("Minutes");

print '</td>';
print '</tr>';

// Activer la synchronisation automatique
print '<tr class="oddeven">';
print '<td>';
print $langs->trans("EnableAutoSync");
print '<br><span class="opacitymedium">'.$langs->trans("EnableAutoSyncHelp").'</span>';
print '</td>';
print '<td>';

$auto_sync_enabled = !empty($conf->global->CALDAVCLIENT_AUTO_SYNC_ENABLED) ? 1 : 0;

print '<input type="checkbox" name="CALDAVCLIENT_AUTO_SYNC_ENABLED" value="1"'.($auto_sync_enabled ? ' checked' : '').'>';

print '</td>';
print '</tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

// Afficher le détail des erreurs si on vient de faire une sync manuelle
if (!empty($results) && !empty($results['errors'])) {
	print '<br>';
	print '<div class="warning">';
	print '<strong>'.$langs->trans("SyncErrors").'</strong>';
	print '<br>';
	if (!empty($results['details']) && is_array($results['details'])) {
		print '<ul>';
		foreach ($results['details'] as $detail) {
			if (is_array($detail)) {
				$calid = !empty($detail['calendar_id']) ? (int) $detail['calendar_id'] : 0;
				$err = !empty($detail['error']) ? $detail['error'] : '';
				print '<li>Calendrier '.$calid.' : '.dol_escape_htmltag($err).'</li>';
			} else {
				print '<li>'.dol_escape_htmltag($detail).'</li>';
			}
		}
		print '</ul>';
	}
	print '</div>';
}

print '<br>';

// Synchronisation manuelle
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="sync_now">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans("ManualSync").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>';
print $langs->trans("ManualSyncHelp");
print '</td>';
print '<td class="center">';
print '<input type="submit" class="button" value="'.$langs->trans("SyncNow").'">';
print '</td>';
print '</tr>';

print '</table>';

print '</form>';

// Explications sur les modes
print '<br>';
print '<div class="info">';
print '<h3>'.$langs->trans("SyncModesExplanation").'</h3>';
print '<ul>';
print '<li><strong>'.$langs->trans("SyncModeDolibarrToCalDAV").' ('.$langs->trans("Default").')</strong>: ';
print $langs->trans("SyncModeDolibarrToCalDAVDesc");
print '</li>';
print '<li><strong>'.$langs->trans("SyncModeCalDAVToDolibarr").'</strong>: ';
print $langs->trans("SyncModeCalDAVToDolibarrDesc");
print '</li>';
print '<li><strong>'.$langs->trans("SyncModeBidirectional").'</strong>: ';
print $langs->trans("SyncModeBidirectionalDesc");
print '</li>';
print '</ul>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
