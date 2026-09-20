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
 * \file       htdocs/custom/caldavclient/admin/setup.php
 * \ingroup    caldavclient
 * \brief      Page de configuration des connexions CalDAV
 */

// Charger l'environnement Dolibarr
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavcalendar.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/lib/caldav.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/lib/caldavclient.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Vérification des permissions
if (!$user->admin && !$user->hasRight('caldavclient', 'read')) {
	accessforbidden();
}

// Charger les fichiers de traduction
$langs->loadLangs(array('caldavclient@caldavclient', 'admin', 'other'));

// Colonne ssl_verify (installations déjà en place avant 0.21.0)
caldavclient_ensure_connection_ssl_column($db);

// Actions
$action = GETPOST('action', 'alpha');
$id = GETPOSTINT('id');
$fk_connection = GETPOSTINT('fk_connection');

$form = new Form($db);
$formother = new FormOther($db);

// Traitement des actions
if ($action == 'add' || $action == 'edit') {
	// Récupérer les données du formulaire
	$connection = new CalDAVConnection($db);
	
	if ($action == 'edit' && $id > 0) {
		$connection->fetch($id);
	}
	
	$connection->name = GETPOST('name', 'alpha');
	$connection->url = GETPOST('url', 'alpha');
	$connection->username = GETPOST('username', 'alpha');
	$password = GETPOST('password', 'alpha');
	if (!empty($password)) {
		$connection->password = $password;
	}
	$connection->calendar_path = GETPOST('calendar_path', 'alpha');
	$connection->color = GETPOST('color', 'alpha');
	$connection->enabled = GETPOSTINT('enabled');
	$connection->active_by_default = GETPOSTINT('active_by_default');
	// Si le champ n'est pas dans le formulaire, on garde la vérification SSL (plus sûr).
	$connection->ssl_verify = GETPOSTISSET('ssl_verify') ? GETPOSTINT('ssl_verify') : 1;
	
	// Validation
	$error = 0;
	if (empty($connection->name)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->trans("Name")), null, 'errors');
		$error++;
	}
	if (empty($connection->url)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->trans("URL")), null, 'errors');
		$error++;
	} elseif (!CalDAVConnection::isHttpsUrl($connection->url)) {
		setEventMessages($langs->trans("ErrorCalDAVUrlMustBeHttps"), null, 'errors');
		$error++;
	}
	if (empty($connection->username)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->trans("Username")), null, 'errors');
		$error++;
	}
	if ($action == 'add' && empty($password)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->trans("Password")), null, 'errors');
		$error++;
	}
	
	if (!$error) {
		if ($action == 'add') {
			$result = $connection->create($user);
			if ($result > 0) {
				setEventMessages($langs->trans("ConnectionCreated"), null, 'mesgs');
				header('Location: '.$_SERVER['PHP_SELF']);
				exit;
			} else {
				setEventMessages($connection->error, null, 'errors');
			}
		} else {
			$result = $connection->update($user);
			if ($result > 0) {
				setEventMessages($langs->trans("ConnectionUpdated"), null, 'mesgs');
				header('Location: '.$_SERVER['PHP_SELF']);
				exit;
			} else {
				setEventMessages($connection->error, null, 'errors');
			}
		}
	}
} elseif ($action == 'discover' && $fk_connection > 0) {
	// Découvrir les calendriers d'une connexion
	$connection = new CalDAVConnection($db);
	$result = $connection->fetch($fk_connection);
	
	if ($result > 0) {
		// Vérifier que la connexion est activée
		if (!$connection->enabled) {
			setEventMessages($langs->trans("ConnectionMustBeEnabled"), null, 'warnings');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$fk_connection.'&action=view_calendars');
			exit;
		}
		
		try {
			$client = new CalDAVClient($connection);
			
			// Log pour déboguer
			dol_syslog("CalDAV Discovery: Début de la découverte pour la connexion ".$connection->name." (URL: ".$connection->url.", User: ".$connection->username.", Path: ".($connection->calendar_path ? $connection->calendar_path : 'auto').")", LOG_DEBUG);
			
			$discovered_calendars = $client->discoverCalendars($connection->calendar_path);
			
			dol_syslog("CalDAV Discovery: ".count($discovered_calendars)." calendrier(s) découvert(s)", LOG_DEBUG);
			
			// Sauvegarder les calendriers découverts
			$saved = 0;
			$updated = 0;
			foreach ($discovered_calendars as $cal_data) {
				dol_syslog("CalDAV Discovery: Traitement du calendrier '".$cal_data['name']."' (URL: ".$cal_data['url'].")", LOG_DEBUG);
				// Vérifier si le calendrier existe déjà
				$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."caldav_calendars";
				$sql .= " WHERE fk_connection = ".((int) $fk_connection);
				$sql .= " AND url = '".$db->escape($cal_data['url'])."'";
				$resql = $db->query($sql);
				
				if ($resql && $db->num_rows($resql) > 0) {
					// Mettre à jour le calendrier existant
					$obj = $db->fetch_object($resql);
					$calendar = new CalDAVCalendar($db);
					$calendar->fetch($obj->rowid);
					$calendar->name = $cal_data['name'];
					$calendar->displayname = $cal_data['displayname'];
					$calendar->update($user);
					$updated++;
				} else {
					// Créer un nouveau calendrier
					$calendar = new CalDAVCalendar($db);
					$calendar->fk_connection = $fk_connection;
					$calendar->name = $cal_data['name'];
					$calendar->displayname = $cal_data['displayname'];
					$calendar->url = $cal_data['url'];
					$calendar->color = $connection->color; // Utiliser la couleur de la connexion par défaut
					$calendar->enabled = 1;
					$calendar->active_by_default = $connection->active_by_default;
					$result = $calendar->create($user);
					if ($result > 0) {
						$saved++;
					}
				}
			}
			
			if ($saved > 0 || $updated > 0) {
				setEventMessages($langs->trans("CalendarsDiscovered", $saved, $updated), null, 'mesgs');
			} else {
				$error_msg = $langs->trans("NoCalendarsFound");
				if (!empty($client->last_response['error'])) {
					$error_msg .= "<br>".dol_escape_htmltag($client->last_response['error']);
				}
				$error_msg .= "<br>".$langs->trans("NoCalendarsFoundHelp");
				$error_msg .= "<br>".$langs->trans("CheckLogsForDetails");
				setEventMessages($error_msg, null, 'warnings');
			}
		} catch (Exception $e) {
			setEventMessages($langs->trans("ErrorDiscoveringCalendars").": ".$e->getMessage(), null, 'errors');
		}
	} else {
		setEventMessages($connection->error, null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$fk_connection.'&action=view_calendars');
	exit;
} elseif ($action == 'toggle_calendar' && $id > 0) {
	// Activer/désactiver un calendrier
	$calendar = new CalDAVCalendar($db);
	$calendar->fetch($id);
	// Sauvegarder la couleur avant de modifier
	$saved_color = $calendar->color;
	$calendar->enabled = $calendar->enabled ? 0 : 1;
	// S'assurer que la couleur n'est pas perdue
	if (empty($calendar->color)) {
		$calendar->color = $saved_color ? $saved_color : 'BECEDD';
	}
	$result = $calendar->update($user);
	if ($result > 0) {
		setEventMessages($langs->trans("CalendarUpdated"), null, 'mesgs');
	} else {
		setEventMessages($calendar->error, null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$calendar->fk_connection.'&action=view_calendars');
	exit;
} elseif ($action == 'edit_calendar' && $id > 0 && GETPOST('save', 'alpha')) {
	// Modifier un calendrier (uniquement si le formulaire a été soumis)
	$calendar = new CalDAVCalendar($db);
	$calendar->fetch($id);
	
	// Récupérer les valeurs du formulaire
	$displayname = GETPOST('displayname', 'alpha');
	if (!empty($displayname)) {
		$calendar->displayname = $displayname;
	}
	
	// Récupérer la couleur - le sélecteur de couleur retourne plusieurs formats possibles
	$color = GETPOST('color', 'alpha');
	// Log pour déboguer
	dol_syslog("CalDAV: Couleur reçue du formulaire: '".$color."'", LOG_DEBUG);
	
	if (!empty($color)) {
		// Enlever le # si présent au début
		$color = ltrim($color, '#');
		// S'assurer que la couleur est valide (format hexadécimal)
		if (preg_match('/^[0-9A-Fa-f]{6}$/', $color)) {
			$calendar->color = $color;
			dol_syslog("CalDAV: Couleur valide, sauvegarde: '".$color."'", LOG_DEBUG);
		} else {
			dol_syslog("CalDAV: Couleur invalide, format non reconnu: '".$color."'", LOG_WARNING);
		}
	} else {
		dol_syslog("CalDAV: Aucune couleur reçue depuis le formulaire", LOG_WARNING);
	}
	
	// Pour les valeurs yes/no, GETPOSTINT retourne toujours une valeur (0 ou 1)
	$calendar->enabled = GETPOSTINT('enabled');
	$calendar->active_by_default = GETPOSTINT('active_by_default');

	// Affecter le calendrier à un utilisateur Dolibarr (propriétaire/organisateur côté CalDAV)
	$fk_user_owner = GETPOSTINT('fk_user_owner');
	$calendar->fk_user_owner = $fk_user_owner > 0 ? $fk_user_owner : null;
	
	// Récupérer le type de visibilité
	$visibility_type = GETPOST('visibility_type', 'alpha');
	if (in_array($visibility_type, array('public', 'individual'))) {
		$calendar->visibility_type = $visibility_type;
	}
	
	dol_syslog("CalDAV: Sauvegarde du calendrier - displayname: '".$calendar->displayname."', color: '".$calendar->color."', enabled: ".$calendar->enabled.", active_by_default: ".$calendar->active_by_default.", visibility: ".$calendar->visibility_type.", fk_user_owner: ".($calendar->fk_user_owner ? $calendar->fk_user_owner : 'NULL'), LOG_DEBUG);
	
	$result = $calendar->update($user);
	if ($result > 0) {
		// Si le calendrier est en mode 'individual', assigner les utilisateurs sélectionnés
		if ($calendar->visibility_type == 'individual') {
			$assigned_users = GETPOST('assigned_users', 'array');
			if (empty($assigned_users)) {
				$assigned_users = array();
			}
			$calendar->setAssignedUsers($assigned_users);
		}
		
		setEventMessages($langs->trans("CalendarUpdated"), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$calendar->fk_connection.'&action=view_calendars');
		exit;
	} else {
		setEventMessages($calendar->error, null, 'errors');
	}
} elseif ($action == 'delete' && $id > 0) {
	$connection = new CalDAVConnection($db);
	$connection->fetch($id);
	$result = $connection->delete();
	if ($result > 0) {
		setEventMessages($langs->trans("ConnectionDeleted"), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	} else {
		setEventMessages($connection->error, null, 'errors');
	}
} elseif ($action == 'update_display_options') {
	$hide_sysauto = GETPOSTINT('CALDAVCLIENT_HIDE_AGENDA_SYSTEMAUTO');
	dolibarr_set_const($db, 'CALDAVCLIENT_HIDE_AGENDA_SYSTEMAUTO', $hide_sysauto ? '1' : '0', 'chaine', 0, '', $conf->entity);
	$tile_by_user = GETPOSTINT('CALDAVCLIENT_AGENDA_TILE_BY_USER');
	dolibarr_set_const($db, 'CALDAVCLIENT_AGENDA_TILE_BY_USER', $tile_by_user ? '1' : '0', 'chaine', 0, '', $conf->entity);
	$multi_hex = GETPOST('CALDAVCLIENT_AGENDA_MULTIUSER_COLOR', 'alpha');
	$multi_hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $multi_hex));
	if (strlen($multi_hex) > 6) {
		$multi_hex = substr($multi_hex, 0, 6);
	}
	if (strlen($multi_hex) !== 6) {
		$multi_hex = 'B85450';
	}
	dolibarr_set_const($db, 'CALDAVCLIENT_AGENDA_MULTIUSER_COLOR', $multi_hex, 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

// Liste des couleurs disponibles
$colorlist = array('BECEDD', 'DDBECE', 'BFDDBE', 'F598B4', 'F68654', 'CBF654', 'A4A4A5', 'FFD700', 'FF6347', '00CED1');

// Affichage
$arrayofjs = array();
$arrayofcss = array();

$wikihelp = '';
llxHeader('', $langs->trans("CalDAVClientSetup"), $wikihelp, '', 0, 0, $arrayofjs, $arrayofcss);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("CalDAVClientSetup"), $linkback, 'title_setup');

// Onglets
$head = caldavclient_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $langs->trans("CalDAVClientSetup"), -1, 'caldavclient@caldavclient');

// Options d'affichage de l'agenda natif (hors écrans connexion / calendriers)
if (!in_array($action, array('create', 'edit', 'view_calendars', 'edit_calendar'), true)) {
	$hide_agenda_sysauto = getDolGlobalString('CALDAVCLIENT_HIDE_AGENDA_SYSTEMAUTO');
	$tile_by_user = getDolGlobalString('CALDAVCLIENT_AGENDA_TILE_BY_USER');
	$multi_color = getDolGlobalString('CALDAVCLIENT_AGENDA_MULTIUSER_COLOR', 'B85450');
	$multi_color = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $multi_color));
	if (strlen($multi_color) !== 6) {
		$multi_color = 'B85450';
	}
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update_display_options">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("CalDAVAgendaDisplayOptions").'</td></tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("CalDAVHideAgendaSystemAuto").'<br><span class="opacitymedium">'.$langs->trans("CalDAVHideAgendaSystemAutoHelp").'</span></td>';
	print '<td class="nowrap"><input type="checkbox" name="CALDAVCLIENT_HIDE_AGENDA_SYSTEMAUTO" value="1"'.($hide_agenda_sysauto ? ' checked' : '').'></td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("CalDAVAgendaTileColorByUser").'<br><span class="opacitymedium">'.$langs->trans("CalDAVAgendaTileColorByUserHelp").'</span></td>';
	print '<td class="nowrap"><input type="checkbox" name="CALDAVCLIENT_AGENDA_TILE_BY_USER" value="1"'.($tile_by_user ? ' checked' : '').'></td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("CalDAVAgendaMultiUserTileColor").'<br><span class="opacitymedium">'.$langs->trans("CalDAVAgendaMultiUserTileColorHelp").'</span></td>';
	print '<td class="nowrap"><input type="color" class="flat minwidth100" name="CALDAVCLIENT_AGENDA_MULTIUSER_COLOR" value="#'.dol_escape_htmltag($multi_color).'"></td>';
	print '</tr>';
	print '</table>';
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans("Save").'"></div>';
	print '</form>';
	print '<br>';
}

// Formulaire d'ajout/modification
if ($action == 'create' || ($action == 'edit' && $id > 0)) {
	$connection = new CalDAVConnection($db);
	if ($action == 'edit' && $id > 0) {
		$connection->fetch($id);
	}
	
	print '<form name="connectionform" action="'.$_SERVER["PHP_SELF"].'" method="post">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.($action == 'create' ? 'add' : 'edit').'">';
	if ($action == 'edit') {
		print '<input type="hidden" name="id" value="'.$connection->id.'">';
	}
	
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	
	print '<tr class="liste_titre">';
	print '<th colspan="2">'.($action == 'create' ? $langs->trans("NewConnection") : $langs->trans("EditConnection")).'</th>';
	print '</tr>';
	
	// Nom
	print '<tr class="oddeven">';
	print '<td class="fieldrequired">'.$langs->trans("Name").'</td>';
	print '<td><input type="text" name="name" value="'.dol_escape_htmltag($connection->name).'" class="minwidth200"></td>';
	print '</tr>';
	
	// URL (HTTPS obligatoire : le mot de passe voyage dans les requêtes)
	print '<tr class="oddeven">';
	print '<td class="fieldrequired">'.$langs->trans("URL").'</td>';
	print '<td>';
	print '<input type="url" name="url" value="'.dol_escape_htmltag($connection->url).'" class="minwidth300" placeholder="https://nextcloud.example.com" required>';
	print '<br><span class="opacitymedium">'.$langs->trans("CalDAVUrlHttpsHelp").'</span>';
	print '</td>';
	print '</tr>';
	
	// Nom d'utilisateur
	print '<tr class="oddeven">';
	print '<td class="fieldrequired">'.$langs->trans("Username").'</td>';
	print '<td><input type="text" name="username" value="'.dol_escape_htmltag($connection->username).'" class="minwidth200"></td>';
	print '</tr>';
	
	// Mot de passe
	print '<tr class="oddeven">';
	print '<td class="'.($action == 'create' ? 'fieldrequired' : '').'">'.$langs->trans("Password").'</td>';
	print '<td><input type="password" name="password" value="" class="minwidth200" placeholder="'.($action == 'edit' ? $langs->trans("LeaveEmptyToKeepCurrent") : '').'"></td>';
	print '</tr>';
	
	// Chemin du calendrier
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("CalendarPath").'</td>';
	print '<td><input type="text" name="calendar_path" value="'.dol_escape_htmltag($connection->calendar_path).'" class="minwidth300" placeholder="/remote.php/dav/calendars/username/calendarname/"></td>';
	print '<td><span class="opacitymedium">'.$langs->trans("CalendarPathHelp").'</span></td>';
	print '</tr>';
	
	// Couleur
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("Color").'</td>';
	print '<td>';
	print $formother->selectColor($connection->color ? $connection->color : 'BECEDD', "color", '', 1, $colorlist);
	print '</td>';
	print '</tr>';
	
	// Actif
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("Enabled").'</td>';
	print '<td>';
	print $form->selectyesno("enabled", $connection->enabled, 1);
	print '</td>';
	print '</tr>';
	
	// Actif par défaut
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("ActiveByDefault").'</td>';
	print '<td>';
	print $form->selectyesno("active_by_default", $connection->active_by_default, 1);
	print '</td>';
	print '</tr>';

	// Vérification SSL (Oui par défaut). Non = certificat auto-signé / machine de test.
	$ssl_verify_form = isset($connection->ssl_verify) ? (int) $connection->ssl_verify : 1;
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("CalDAVSslVerify").'</td>';
	print '<td>';
	print $form->selectyesno("ssl_verify", $ssl_verify_form, 1);
	print '<br><span class="opacitymedium">'.$langs->trans("CalDAVSslVerifyHelp").'</span>';
	print '</td>';
	print '</tr>';
	
	print '</table>';
	print '</div>';
	
	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
	print ' <a href="'.$_SERVER['PHP_SELF'].'" class="button button-cancel">'.$langs->trans("Cancel").'</a>';
	print '</div>';
	
	print '</form>';
	print '<br>';
}

// Liste des connexions
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Name").'</th>';
print '<th>'.$langs->trans("URL").'</th>';
print '<th>'.$langs->trans("Username").'</th>';
print '<th class="center">'.$langs->trans("Color").'</th>';
print '<th class="center">'.$langs->trans("Enabled").'</th>';
print '<th class="center">'.$langs->trans("ActiveByDefault").'</th>';
print '<th class="center">'.$langs->trans("Actions").'</th>';
print '</tr>';

// Récupérer toutes les connexions
// Vérifier d'abord si la table existe
$table_exists = false;
$sql_check = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."caldav_connections'";
$resql_check = $db->query($sql_check);
if ($resql_check && $db->num_rows($resql_check) > 0) {
	$table_exists = true;
}

if ($table_exists) {
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."caldav_connections";
	$sql .= " WHERE entity = ".((int) $conf->entity);
	$sql .= " ORDER BY name";
	
	$resql = $db->query($sql);
} else {
	$resql = false;
	$num = 0;
}
if ($resql) {
	$num = $db->num_rows($resql);
	$i = 0;
	
	if ($num == 0) {
		print '<tr class="oddeven">';
		print '<td colspan="7" class="opacitymedium">'.$langs->trans("NoConnectionFound").'</td>';
		print '</tr>';
	} else {
		while ($i < $num) {
			$obj = $db->fetch_object($resql);
			$connection = new CalDAVConnection($db);
			$connection->fetch($obj->rowid);
			
			print '<tr class="oddeven">';
			print '<td>'.dol_escape_htmltag($connection->name).'</td>';
			print '<td>'.dol_escape_htmltag($connection->url).'</td>';
			print '<td>'.dol_escape_htmltag($connection->username).'</td>';
			print '<td class="center">';
			if ($connection->color) {
				print '<span style="background-color: #'.$connection->color.'; padding: 5px 15px; border-radius: 3px;">&nbsp;</span>';
			}
			print '</td>';
			print '<td class="center">';
			print $connection->enabled ? $langs->trans("Yes") : $langs->trans("No");
			print '</td>';
			print '<td class="center">';
			print $connection->active_by_default ? $langs->trans("Yes") : $langs->trans("No");
			print '</td>';
			print '<td class="center">';
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.$connection->id.'&token='.newToken().'" class="butAction">'.$langs->trans("Modify").'</a>';
			print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$connection->id.'&action=view_calendars&token='.newToken().'" class="butAction">'.$langs->trans("ViewCalendars").'</a>';
			print ' <a href="'.$_SERVER['PHP_SELF'].'?action=delete&id='.$connection->id.'&token='.newToken().'" class="butActionDelete" onclick="return confirm(\''.$langs->trans("ConfirmDelete").'\')">'.$langs->trans("Delete").'</a>';
			print '</td>';
			print '</tr>';
			
			$i++;
		}
	}
	$db->free($resql);
} else {
	dol_print_error($db);
}

print '</table>';
print '</div>';

print '<div class="tabsAction">';
print '<a href="'.$_SERVER['PHP_SELF'].'?action=create&token='.newToken().'" class="butAction">'.$langs->trans("NewConnection").'</a>';
print '</div>';

// Affichage des calendriers d'une connexion
// Note: on accepte aussi 'edit_calendar' pour afficher le formulaire de modification
if (($action == 'view_calendars' || $action == 'edit_calendar') && ($id > 0 || $fk_connection > 0)) {
	$connection = new CalDAVConnection($db);
	// Pour 'edit_calendar', utiliser fk_connection au lieu de id (qui est l'id du calendrier)
	$connection_id = ($action == 'edit_calendar' && $fk_connection > 0) ? $fk_connection : $id;
	$connection->fetch($connection_id);
	
	print '<br>';
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	
	print '<tr class="liste_titre">';
	print '<th colspan="7">'.$langs->trans("CalendarsForConnection", $connection->name).'</th>';
	print '</tr>';
	
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans("Name").'</th>';
	print '<th>'.$langs->trans("DisplayName").'</th>';
	print '<th>'.$langs->trans("URL").'</th>';
	print '<th class="center">'.$langs->trans("Color").'</th>';
	print '<th class="center">'.$langs->trans("Visibility").'</th>';
	print '<th class="center">'.$langs->trans("Enabled").'</th>';
	print '<th class="center">'.$langs->trans("Actions").'</th>';
	print '</tr>';
	
	// Récupérer les calendriers de cette connexion
	// Vérifier d'abord si la table existe
	$table_exists = false;
	$sql_check = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."caldav_calendars'";
	$resql_check = $db->query($sql_check);
	if ($resql_check && $db->num_rows($resql_check) > 0) {
		$table_exists = true;
	}
	
	if ($table_exists) {
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."caldav_calendars";
		$sql .= " WHERE fk_connection = ".((int) $connection->id);
		$sql .= " ORDER BY name";
		
		$resql = $db->query($sql);
	} else {
		$resql = false;
		$num = 0;
	}
	if ($resql) {
		$num = $db->num_rows($resql);
		
		if ($num == 0) {
			print '<tr class="oddeven">';
			print '<td colspan="7" class="opacitymedium">'.$langs->trans("NoCalendarsFound").'</td>';
			print '</tr>';
		} else {
			$i = 0;
			while ($i < $num) {
				$obj = $db->fetch_object($resql);
				$calendar = new CalDAVCalendar($db);
				$calendar->fetch($obj->rowid);
				
				print '<tr class="oddeven">';
				print '<td>'.dol_escape_htmltag($calendar->name).'</td>';
				print '<td>'.dol_escape_htmltag($calendar->displayname ? $calendar->displayname : $calendar->name).'</td>';
				print '<td class="opacitymedium">'.dol_escape_htmltag($calendar->url).'</td>';
				print '<td class="center">';
				if ($calendar->color) {
					print '<span style="background-color: #'.$calendar->color.'; padding: 5px 15px; border-radius: 3px;">&nbsp;</span>';
				}
				print '</td>';
				print '<td class="center">';
				// Afficher la visibilité
				if ($calendar->visibility_type == 'individual') {
					$assigned_users = $calendar->getAssignedUsers();
					print '<span class="badge badge-status4">'.$langs->trans("Individual").'</span>';
					if (!empty($assigned_users)) {
						print '<br><span class="opacitymedium">('.count($assigned_users).' '.$langs->trans("users").')</span>';
					}
				} else {
					print '<span class="badge badge-status4">'.$langs->trans("Public").'</span>';
				}
				print '</td>';
				print '<td class="center">';
				print $calendar->enabled ? $langs->trans("Yes") : $langs->trans("No");
				print '</td>';
				print '<td class="center">';
				print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$calendar->id.'&fk_connection='.$connection->id.'&action=edit_calendar&token='.newToken().'" class="butAction">'.$langs->trans("Modify").'</a>';
				print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$calendar->id.'&fk_connection='.$connection->id.'&action=toggle_calendar&token='.newToken().'" class="butAction">'.($calendar->enabled ? $langs->trans("Disable") : $langs->trans("Enable")).'</a>';
				print '</td>';
				print '</tr>';
				
				$i++;
			}
		}
		$db->free($resql);
	}
	
	print '</table>';
	print '</div>';
	
	print '<div class="tabsAction">';
	print '<a href="'.$_SERVER['PHP_SELF'].'?fk_connection='.$connection->id.'&action=discover&token='.newToken().'" class="butAction">'.$langs->trans("DiscoverCalendars").'</a>';
	print ' <a href="'.$_SERVER['PHP_SELF'].'" class="butAction">'.$langs->trans("BackToList").'</a>';
	print '</div>';
	
	// Formulaire de modification d'un calendrier
	if ($action == 'edit_calendar' && GETPOSTINT('id') > 0) {
		$calendar_id = GETPOSTINT('id');
		$calendar = new CalDAVCalendar($db);
		$calendar->fetch($calendar_id);
		
		print '<br>';
		print '<form name="calendarform" action="'.$_SERVER["PHP_SELF"].'" method="post">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="edit_calendar">';
		print '<input type="hidden" name="id" value="'.$calendar->id.'">';
		print '<input type="hidden" name="fk_connection" value="'.$calendar->fk_connection.'">';
		
		print '<div class="div-table-responsive">';
		print '<table class="noborder centpercent">';
		
		print '<tr class="liste_titre">';
		print '<th colspan="2">'.$langs->trans("EditCalendar").'</th>';
		print '</tr>';
		
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans("Name").'</td>';
		print '<td class="opacitymedium">'.dol_escape_htmltag($calendar->name).' <span class="opacitymedium">('.$langs->trans("ReadOnly").')</span></td>';
		print '</tr>';
		
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans("DisplayName").'</td>';
		print '<td><input type="text" name="displayname" value="'.dol_escape_htmltag($calendar->displayname ? $calendar->displayname : $calendar->name).'" class="minwidth300"></td>';
		print '</tr>';
		
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans("Color").'</td>';
		print '<td>';
		// S'assurer que la couleur a le format correct (sans #)
		$current_color = $calendar->color ? $calendar->color : 'BECEDD';
		$current_color = ltrim($current_color, '#');
		print $formother->selectColor($current_color, "color", '', 0);
		print ' <span class="opacitymedium">'.$langs->trans("CurrentColor").': <span style="display:inline-block;width:20px;height:20px;background-color:#'.$current_color.';border:1px solid #ccc;vertical-align:middle;"></span></span>';
		print '</td>';
		print '</tr>';
		
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans("Visibility").'</td>';
		print '<td>';
		$visibility_options = array('public' => $langs->trans("Public").' - '.$langs->trans("VisibleByAllUsers"), 'individual' => $langs->trans("Individual").' - '.$langs->trans("VisibleBySelectedUsers"));
		print $form->selectarray("visibility_type", $visibility_options, $calendar->visibility_type ? $calendar->visibility_type : 'public', 0, 0, 0, '', 0, 0, 0, '', 'minwidth200', 1);
		print '</td>';
		print '</tr>';

		// Utilisateur propriétaire (sert à définir ORGANIZER côté CalDAV)
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans("CalendarOwnerUser").'</td>';
		print '<td>';
		print $form->select_dolusers($calendar->fk_user_owner ? $calendar->fk_user_owner : 0, 'fk_user_owner', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth300');
		print '<br><span class="opacitymedium">'.$langs->trans("CalendarOwnerUserHelp").'</span>';
		print '</td>';
		print '</tr>';
		
		// Si visibilité individuelle, afficher la liste des utilisateurs
		print '<tr class="oddeven" id="assigned_users_row">';
		print '<td>'.$langs->trans("AssignedUsers").'</td>';
		print '<td>';
		// Récupérer la liste des utilisateurs actifs
		$sql_users = "SELECT rowid, firstname, lastname, login FROM ".MAIN_DB_PREFIX."user WHERE statut = 1 AND entity IN (0,".$conf->entity.") ORDER BY lastname, firstname";
		$resql_users = $db->query($sql_users);
		$users_list = array();
		if ($resql_users) {
			while ($obj_user = $db->fetch_object($resql_users)) {
				$users_list[$obj_user->rowid] = dolGetFirstLastname($obj_user->firstname, $obj_user->lastname).' ('.$obj_user->login.')';
			}
		}
		// Récupérer les utilisateurs actuellement assignés
		$assigned_user_ids = $calendar->getAssignedUsers();
		print $form->multiselectarray('assigned_users', $users_list, $assigned_user_ids, 0, 0, 'minwidth300', 0, 0, '', '', '', 1);
		print '<br><span class="opacitymedium">'.$langs->trans("OnlyForIndividualVisibility").'</span>';
		print '</td>';
		print '</tr>';
		
		// JavaScript pour afficher/masquer la sélection d'utilisateurs selon le type de visibilité
		print '<script type="text/javascript">
		jQuery(document).ready(function() {
			function toggleAssignedUsers() {
				var visibility = jQuery("select[name=\'visibility_type\']").val();
				if (visibility == "individual") {
					jQuery("#assigned_users_row").show();
				} else {
					jQuery("#assigned_users_row").hide();
				}
			}
			toggleAssignedUsers();
			jQuery("select[name=\'visibility_type\']").on("change", function() {
				toggleAssignedUsers();
			});
		});
		</script>';
		
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans("Enabled").'</td>';
		print '<td>';
		print $form->selectyesno("enabled", $calendar->enabled, 1);
		print '</td>';
		print '</tr>';
		
		print '<tr class="oddeven">';
		print '<td>'.$langs->trans("ActiveByDefault").'</td>';
		print '<td>';
		print $form->selectyesno("active_by_default", $calendar->active_by_default, 1);
		print '</td>';
		print '</tr>';
		
		print '</table>';
		print '</div>';
		
		print '<div class="center">';
		print '<input type="submit" class="button button-save" name="save" value="'.$langs->trans("Save").'">';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$calendar->fk_connection.'&action=view_calendars" class="button button-cancel">'.$langs->trans("Cancel").'</a>';
		print '</div>';
		
		print '</form>';
	}
}

print dol_get_fiche_end();

// Fin de page
llxFooter();
$db->close();
