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
 */

/**
 * \file    admin/cleanup_widget.php
 * \ingroup caldavclient
 * \brief   Script pour nettoyer les doublons de widgets dans la base de données
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

// Restrict to admin users
if (!$user->admin) {
    accessforbidden();
}

echo "<!DOCTYPE html>\n";
echo "<html>\n";
echo "<head>\n";
echo "<title>Nettoyage Widget CalDAV Client</title>\n";
echo "<meta charset='utf-8'>\n";
echo "</head>\n";
echo "<body style='font-family: Arial, sans-serif; padding: 20px;'>\n";
echo "<h1>Nettoyage des doublons de widgets CalDAV Client</h1>\n";

// Liste les widgets CalDAV actuels
$sql = "SELECT rowid, file, note FROM ".MAIN_DB_PREFIX."boxes_def WHERE file LIKE '%caldav%'";
$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    echo "<h2>Widgets CalDAV trouvés : ".$num."</h2>\n";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
    echo "<tr><th>ID</th><th>Fichier</th><th>Note</th><th>Action</th></tr>\n";
    
    $widgets = array();
    while ($obj = $db->fetch_object($resql)) {
        $widgets[] = $obj;
        echo "<tr>\n";
        echo "<td>".$obj->rowid."</td>\n";
        echo "<td>".$obj->file."</td>\n";
        echo "<td>".$obj->note."</td>\n";
        echo "<td>";
        
        // Marquer l'ancien format comme à supprimer
        if ($obj->file == 'caldaveventsbox@caldavclient') {
            echo "<strong style='color: red;'>À SUPPRIMER (ancien format)</strong>";
        } else {
            echo "<span style='color: green;'>OK (format correct)</span>";
        }
        echo "</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Supprimer l'ancien format
    echo "<h2>Suppression des doublons...</h2>\n";
    
    $db->begin();
    
    // Supprimer d'abord les instances de widgets utilisateurs
    $sql_del_boxes = "DELETE FROM ".MAIN_DB_PREFIX."boxes WHERE box_id IN (SELECT rowid FROM ".MAIN_DB_PREFIX."boxes_def WHERE file = 'caldaveventsbox@caldavclient')";
    $resql_del_boxes = $db->query($sql_del_boxes);
    
    if ($resql_del_boxes) {
        echo "<p style='color: green;'>✓ Instances de widgets utilisateurs supprimées</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Erreur lors de la suppression des instances : ".$db->lasterror()."</p>\n";
        $db->rollback();
        echo "</body></html>";
        exit;
    }
    
    // Supprimer la définition de widget
    $sql_del_def = "DELETE FROM ".MAIN_DB_PREFIX."boxes_def WHERE file = 'caldaveventsbox@caldavclient'";
    $resql_del_def = $db->query($sql_del_def);
    
    if ($resql_del_def) {
        echo "<p style='color: green;'>✓ Définition de widget supprimée</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Erreur lors de la suppression de la définition : ".$db->lasterror()."</p>\n";
        $db->rollback();
        echo "</body></html>";
        exit;
    }
    
    $db->commit();
    
    echo "<h2 style='color: green;'>✓ Nettoyage terminé avec succès !</h2>\n";
    echo "<p>Vous pouvez maintenant :</p>\n";
    echo "<ul>\n";
    echo "<li>Rafraîchir la page de gestion des widgets</li>\n";
    echo "<li>Le widget ne devrait plus apparaître en double</li>\n";
    echo "</ul>\n";
    
} else {
    echo "<p style='color: red;'>Erreur lors de la recherche des widgets : ".$db->lasterror()."</p>\n";
}

echo "<p><a href='setup.php'>← Retour à la configuration du module</a></p>\n";
echo "</body>\n";
echo "</html>\n";
