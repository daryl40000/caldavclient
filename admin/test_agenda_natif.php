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
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Script de test pour diagnostiquer l'affichage des événements CalDAV dans l'agenda natif.
 */

// Chargement de l'environnement Dolibarr
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; 
$tmp2 = realpath(__FILE__); 
$i = strlen($tmp) - 1; 
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Erreur: Impossible de charger main.inc.php");
}

// Restreindre aux administrateurs
if (!$user->admin) {
    accessforbidden();
}

require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavcalendar.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/lib/caldav.lib.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>\n";
echo "<html><head><meta charset='UTF-8'><title>Test Agenda Natif CalDAV</title>\n";
echo "<style>body{font-family:Arial;padding:20px;} table{border-collapse:collapse;width:100%;margin:20px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#4CAF50;color:white;} .success{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#f4f4f4;padding:10px;overflow:auto;}</style>\n";
echo "</head><body>\n";

echo "<h1>🔍 Test de l'agenda natif Dolibarr - Événements CalDAV</h1>\n";

// Test 1: Vérifier que le module est activé
echo "<h2>1. Vérification du module</h2>\n";
if (!empty($conf->caldavclient->enabled)) {
    echo "<p class='success'>✅ Module CalDAV Client activé</p>\n";
} else {
    echo "<p class='error'>❌ Module CalDAV Client désactivé</p>\n";
    echo "</body></html>";
    exit;
}

// Test 2: Vérifier les permissions
echo "<h2>2. Permissions utilisateur</h2>\n";
echo "<p>Utilisateur: <strong>".$user->login."</strong></p>\n";
if (!empty($user->rights->caldavclient->read)) {
    echo "<p class='success'>✅ Permission de lecture OK</p>\n";
} else {
    echo "<p class='error'>❌ Pas de permission de lecture</p>\n";
}

// Test 3: Récupérer les calendriers actifs
echo "<h2>3. Calendriers CalDAV configurés</h2>\n";
$calendars = CalDAVCalendar::getAllActive($conf->entity, $user->id);
echo "<p>Nombre de calendriers accessibles: <strong>".count($calendars)."</strong></p>\n";

if (empty($calendars)) {
    echo "<p class='error'>❌ Aucun calendrier trouvé</p>\n";
    echo "</body></html>";
    exit;
}

echo "<table>\n";
echo "<tr><th>ID</th><th>Nom</th><th>Nom affichage</th><th>Connexion</th><th>Actif</th><th>Visibilité</th><th>Couleur</th></tr>\n";
foreach ($calendars as $cal) {
    $connection = new CalDAVConnection($db);
    $connection->fetch($cal->fk_connection);
    echo "<tr>";
    echo "<td>".$cal->id."</td>";
    echo "<td>".$cal->name."</td>";
    echo "<td>".($cal->displayname ? $cal->displayname : '-')."</td>";
    echo "<td>".$connection->name."</td>";
    echo "<td>".($cal->enabled ? '✅' : '❌')."</td>";
    echo "<td>".$cal->visibility_type."</td>";
    echo "<td><span style='display:inline-block;width:30px;height:15px;background:".$cal->color."'></span> ".$cal->color."</td>";
    echo "</tr>\n";
}
echo "</table>\n";

// Test 4: Tester le hook addCalendarChoice
echo "<h2>4. Test du hook addCalendarChoice (Checkboxes)</h2>\n";
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
$hookmanager = new HookManager($db);
$hookmanager->initHooks(array('agenda'));

// Simuler l'appel du hook
$parameters = array();
$object = new stdClass();
$action = '';

$reshook = $hookmanager->executeHooks('addCalendarChoice', $parameters, $object, $action);

if ($reshook < 0) {
    echo "<p class='error'>❌ Erreur lors de l'exécution du hook</p>\n";
} else {
    echo "<p class='success'>✅ Hook exécuté sans erreur</p>\n";
}

if (!empty($hookmanager->resPrint)) {
    echo "<p class='success'>✅ HTML des checkboxes généré (".strlen($hookmanager->resPrint)." caractères)</p>\n";
    echo "<details><summary>Voir le HTML généré</summary><pre>".htmlspecialchars($hookmanager->resPrint)."</pre></details>\n";
} else {
    echo "<p class='error'>❌ Aucun HTML généré pour les checkboxes</p>\n";
}

// Test 5: Tester le hook getCalendarEvents
echo "<h2>5. Test du hook getCalendarEvents (Récupération événements)</h2>\n";

// Préparer les paramètres pour l'agenda
$start = new DateTime();
$start->modify('-1 week');
$end = new DateTime();
$end->modify('+1 month');

$parameters = array(
    'start' => $start,
    'end' => $end,
    'type' => ''
);

$eventarray = array();
$hookmanager->resArray = array();

$reshook = $hookmanager->executeHooks('getCalendarEvents', $parameters, $eventarray, $action);

if ($reshook < 0) {
    echo "<p class='error'>❌ Erreur lors de l'exécution du hook</p>\n";
} else {
    echo "<p class='success'>✅ Hook exécuté sans erreur</p>\n";
}

if (!empty($hookmanager->resArray['eventarray'])) {
    $retrieved_events = $hookmanager->resArray['eventarray'];
    echo "<p class='success'>✅ Événements récupérés: <strong>".count($retrieved_events)." jour(s)</strong></p>\n";
    
    $total_events = 0;
    foreach ($retrieved_events as $day => $day_events) {
        $total_events += count($day_events);
    }
    echo "<p>Total d'événements: <strong>".$total_events."</strong></p>\n";
    
    // Afficher un échantillon des événements
    echo "<h3>Échantillon des événements récupérés</h3>\n";
    echo "<table>\n";
    echo "<tr><th>Clé Date</th><th>Date</th><th>Titre</th><th>Début</th><th>Fin</th><th>Type</th><th>userassigned</th><th>icalname</th></tr>\n";
    
    $count = 0;
    foreach ($retrieved_events as $date_key => $day_events) {
        foreach ($day_events as $event) {
            if ($count >= 10) break 2; // Limiter à 10 événements
            
            echo "<tr>";
            echo "<td>".$date_key."</td>";
            echo "<td>".dol_print_date($date_key, 'day')."</td>";
            echo "<td>".htmlspecialchars($event->label)."</td>";
            echo "<td>".dol_print_date($event->datep, 'dayhour')."</td>";
            echo "<td>".dol_print_date($event->datep2, 'dayhour')."</td>";
            echo "<td>".$event->type_label."</td>";
            echo "<td>".(isset($event->userassigned) ? 'OUI ('.count($event->userassigned).')' : '<span class="error">NON</span>')."</td>";
            echo "<td>".(isset($event->icalname) ? htmlspecialchars($event->icalname) : '<span class="error">NON</span>')."</td>";
            echo "</tr>\n";
            $count++;
        }
    }
    echo "</table>\n";
    
    if ($total_events > 10) {
        echo "<p><em>... et ".($total_events - 10)." autre(s) événement(s)</em></p>\n";
    }
} else {
    echo "<p class='error'>❌ Aucun événement récupéré via le hook</p>\n";
}

// Test 6: Vérifier les hooks enregistrés
echo "<h2>6. Hooks enregistrés dans le module</h2>\n";
$sql = "SELECT name, value FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_MODULE_CALDAVCLIENT%'";
$resql = $db->query($sql);
if ($resql) {
    echo "<table>\n";
    echo "<tr><th>Constante</th><th>Valeur</th></tr>\n";
    while ($obj = $db->fetch_object($resql)) {
        echo "<tr>";
        echo "<td>".$obj->name."</td>";
        echo "<td>".htmlspecialchars($obj->value)."</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

// Test 7: Recommandations
echo "<h2>7. Diagnostic et recommandations</h2>\n";

if (empty($calendars)) {
    echo "<p class='error'>⚠️ Aucun calendrier configuré. Configurez d'abord des calendriers CalDAV.</p>\n";
} elseif ($total_events == 0) {
    echo "<p class='warning'>⚠️ Aucun événement trouvé. Vérifiez que :</p>\n";
    echo "<ul>\n";
    echo "<li>Vos calendriers CalDAV contiennent des événements dans la période testée (-1 semaine à +1 mois)</li>\n";
    echo "<li>Les connexions CalDAV fonctionnent correctement</li>\n";
    echo "<li>Les URLs et identifiants sont corrects</li>\n";
    echo "</ul>\n";
} else {
    echo "<p class='success'>✅ Les événements sont bien récupérés par le hook !</p>\n";
    echo "<p>Si les événements ne s'affichent toujours pas dans l'agenda natif, vérifiez :</p>\n";
    echo "<ul>\n";
    echo "<li>Que les checkboxes CalDAV sont bien visibles et cochées dans l'agenda</li>\n";
    echo "<li>Que le JavaScript est bien chargé (hook addCalendarJS)</li>\n";
    echo "<li>Que les classes CSS 'family_ext...' sont présentes dans le code HTML de la page</li>\n";
    echo "<li>Les logs Dolibarr pour plus de détails</li>\n";
    echo "</ul>\n";
}

echo "<hr>\n";
echo "<p><a href='setup.php'>← Retour à la configuration</a></p>\n";
echo "</body></html>\n";
