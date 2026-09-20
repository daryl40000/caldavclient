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
 * \file       htdocs/custom/caldavclient/class/actions_caldavclient.class.php
 * \ingroup    caldavclient
 * \brief      Actions hook class for CalDAV Client module
 * 
 * Cette classe implémente les hooks pour intégrer les calendriers CalDAV
 * dans l'agenda Dolibarr.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavcalendar.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/lib/caldav.lib.php';
require_once __DIR__.'/agenda/caldavclient_agenda_list_sql.class.php';
require_once __DIR__.'/agenda/caldavclient_agenda_tile_style.class.php';
require_once __DIR__.'/agenda/caldavclient_agenda_footer_script.class.php';
require_once __DIR__.'/agenda/caldavclient_agenda_calendar_js_hook.class.php';
require_once __DIR__.'/agenda/caldavclient_agenda_native_events.class.php';

/**
 * Class ActionsCaldavclient
 * 
 * Cette classe implémente les hooks pour le module CalDAV Client.
 * Elle permet d'ajouter des calendriers CalDAV externes dans l'agenda Dolibarr.
 */
class ActionsCaldavclient
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * Sortie des hooks « addreplace » : Dolibarr lit cette propriété dans HookManager::executeHooks
	 * et recopie le tout dans $hookmanager->resPrint à la fin. Ne pas seulement assigner $hookmanager->resPrint
	 * dans le hook, car cette valeur serait écrasée.
	 *
	 * @var string|null
	 */
	public $resprints;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Le module est-il activé ? (Dolibarr 20+ : isModEnabled)
	 *
	 * @return bool
	 */
	private function isModuleEnabled()
	{
		return CaldavclientAgendaListSql::isCalDavClientEnabled();
	}

	/**
	 * Hook printFieldListFrom — filtre SQL sur le calendrier (comm/action/index.php).
	 * Sur la liste (contexte agendalist), on laisse printFieldListWhere faire le travail.
	 *
	 * @param  array        $parameters  Paramètres du hook
	 * @param  mixed        $object      Objet contexte
	 * @param  string       $action      Action en cours
	 * @param  HookManager  $hookmanager Gestionnaire de hooks
	 * @return int                      0
	 */
	public function printFieldListFrom($parameters, &$object, &$action, $hookmanager)
	{
		if (!CaldavclientAgendaListSql::isHideSystemAutoEnabled()) {
			return 0;
		}

		$contexte = isset($parameters['currentcontext']) ? (string) $parameters['currentcontext'] : '';
		if ($contexte === 'agendalist') {
			return 0;
		}

		// Exclure les types « automatiques système » (llx_c_actioncomm.type = 'systemauto') :
		// « Autre (auto) » et traces auto (mail devis, facture, etc.).
		// Obligatoire : HookManager agrège ->resprints puis copie dans resPrint.
		$this->resprints = CaldavclientAgendaListSql::getExcludeSystemAutoJoin($this->db);
		dol_syslog("CalDAV: printFieldListFrom — filtre systemauto appliqué sur l'agenda", LOG_DEBUG);

		return 0;
	}

	/**
	 * Hook printFieldListWhere — filtre SQL sur la liste (comm/action/list.php, Dolibarr 24).
	 * AND NOT EXISTS sur a.fk_action : ne dépend pas de l'alias ca (ancien) vs c (v24).
	 *
	 * @param  array        $parameters  Paramètres du hook
	 * @param  mixed        $object      Objet contexte
	 * @param  string       $action      Action en cours
	 * @param  HookManager  $hookmanager Gestionnaire de hooks
	 * @return int                      0
	 */
	public function printFieldListWhere($parameters, &$object, &$action, $hookmanager)
	{
		if (!CaldavclientAgendaListSql::isHideSystemAutoEnabled()) {
			return 0;
		}

		$this->resprints = CaldavclientAgendaListSql::getExcludeSystemAutoWhere($this->db);
		dol_syslog("CalDAV: printFieldListWhere — filtre systemauto appliqué sur la liste agenda", LOG_DEBUG);

		return 0;
	}

	/**
	 * Hook addCalendarChoice - Ajouter des cases à cocher pour activer/désactiver l'affichage des calendriers CalDAV
	 *
	 * @param  array        $parameters Paramètres du hook
	 * @param  Object       $object     Objet concerné
	 * @param  string       $action     Action en cours
	 * @param  HookManager  $hookmanager Instance du gestionnaire de hooks
	 * @return int                     <0 si KO, 0 si aucune action, >0 si OK
	 */
	public function addCalendarChoice($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		dol_syslog("CalDAV: Hook addCalendarChoice appelé", LOG_DEBUG);

		// Vérifier que le module est activé
		if (!$this->isModuleEnabled()) {
			dol_syslog("CalDAV: Module non activé dans addCalendarChoice", LOG_DEBUG);
			return 0;
		}

		// Sur le calendrier principal (index.php), la légende à cases est supprimée du DOM (hook llxFooter) :
		// ne pas ajouter de cases CalDAV ici (évite HTML inutile et un éventuel « flash » avant le script).
		if (!empty($_SERVER['PHP_SELF']) && preg_match('#/comm/action/index\.php$#', (string) $_SERVER['PHP_SELF'])) {
			return 0;
		}

		// Charger les traductions
		$langs->load("caldavclient@caldavclient");

		// Récupérer tous les calendriers CalDAV actifs accessibles par l'utilisateur
		$calendars = CalDAVCalendar::getAllActive($conf->entity, $user->id);

		dol_syslog("CalDAV: ".count($calendars)." calendrier(s) actif(s) trouvé(s) pour addCalendarChoice", LOG_DEBUG);

		if (empty($calendars)) {
			return 0;
		}

		$output = '';

		// Ajouter les cases à cocher pour chaque calendrier
		foreach ($calendars as $calendar) {
			// Charger la connexion pour obtenir le nom
			$connection = new CalDAVConnection($this->db);
			$connection->fetch($calendar->fk_connection);
			
			if (!$connection->enabled) {
				continue;
			}

			// Générer un identifiant unique pour la case à cocher basé sur l'URL du calendrier
			// L'URL est l'identifiant unique en CalDAV, donc on l'utilise pour créer un hash stable
			$htmlname = md5('caldav_' . $calendar->url);
			
			// Vérifier si la case est cochée par défaut (active_by_default)
			// Par défaut, utiliser active_by_default, sauf si une valeur a été explicitement envoyée
			$checked = $calendar->active_by_default ? 'checked' : '';
			
			// Couleur du calendrier
			$color = $calendar->color ? $calendar->color : ($connection->color ? $connection->color : 'BECEDD');
			if (strpos($color, '#') !== 0) {
				$color = '#'.$color;
			}
			
			// Afficher la case à cocher avec la couleur du calendrier
			// Utiliser le même format que les autres calendriers externes pour un affichage cohérent
			$output .= '<div class="nowrap inline-block minheight30">';
			$output .= '<input type="checkbox" id="check_ext'.$htmlname.'" name="check_ext'.$htmlname.'" value="1" '.$checked.' class="caldav-calendar-checkbox" data-calendar-id="'.$calendar->id.'">';
			$output .= '<label for="check_ext'.$htmlname.'" style="color: '.$color.';" class="labelcalendar">';
			$output .= '<span style="display: inline-block; width: 12px; height: 12px; background-color: '.$color.'; margin-right: 5px; vertical-align: middle;"></span>';
			$output .= dol_escape_htmltag($calendar->displayname ? $calendar->displayname : $calendar->name);
			$output .= '</label> &nbsp; </div>';
			
			dol_syslog("CalDAV: Checkbox ajoutée pour le calendrier '".$calendar->name."' (id: ".$calendar->id.", url: ".$calendar->url.", htmlname: ".$htmlname.", checked: ".($checked ? 'oui' : 'non').")", LOG_DEBUG);
		}

		dol_syslog("CalDAV: Total de ".count($calendars)." checkbox(s) ajoutée(s) dans addCalendarChoice", LOG_DEBUG);
		
		// Log du contenu HTML généré pour débogage
		if (!empty($output)) {
			dol_syslog("CalDAV: Contenu HTML généré (premiers 500 caractères): ".substr($output, 0, 500), LOG_DEBUG);
		} else {
			dol_syslog("CalDAV: ATTENTION - output est vide après avoir ajouté les checkboxes !", LOG_WARNING);
		}

		$this->resprints = $output;

		return 0;
	}

	/**
	 * Hook updateFullcalendarEvents — événements CalDAV pour la vue agenda JavaScript Dolibarr ($TEvent).
	 *
	 * @param  array        $parameters Paramètres du hook
	 * @param  Object       $object     Objet concerné (tableau $TEvent de FullCalendar)
	 * @param  string       $action     Action en cours
	 * @param  HookManager  $hookmanager Instance du gestionnaire de hooks
	 * @return int                     <0 si KO, 0 si aucune action, >0 si OK
	 */
	public function updateFullcalendarEvents($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		dol_syslog("CalDAV: Hook updateFullcalendarEvents appelé", LOG_DEBUG);

		if (!$this->isModuleEnabled()) {
			dol_syslog("CalDAV: Module non activé dans updateFullcalendarEvents", LOG_DEBUG);
			return 0;
		}

		$calendarJs = new CaldavclientAgendaCalendarJsHook($this->db);
		return $calendarJs->appendCalDavEventsToTEvent($object);
	}

	/**
	 * Hook formatEvent - Modifier l'affichage d'un événement dans l'agenda
	 * Permet d'ajouter la classe CSS pour le masquage/affichage des événements CalDAV
	 *
	 * @param  array        $parameters Paramètres du hook
	 * @param  Object       $object     Objet événement
	 * @param  string       $action     Action en cours
	 * @param  HookManager  $hookmanager Instance du gestionnaire de hooks
	 * @return int                     <0 si KO, 0 si aucune action, >0 si OK
	 */
	public function formatEvent($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		// Vérifier que le module est activé
		if (!$this->isModuleEnabled()) {
			return 0;
		}

		// Vérifier si c'est un événement CalDAV
		if (isset($object->type_code) && $object->type_code == 'CALDAV') {
			// Ajouter la classe CSS si elle est définie
			if (isset($object->event_family_css_class)) {
				// Si la propriété existe déjà, on l'ajoute à la liste des classes
				if (!isset($object->extraparams)) {
					$object->extraparams = array();
				}
				if (!isset($object->extraparams['css_class'])) {
					$object->extraparams['css_class'] = '';
				}
				$object->extraparams['css_class'] .= ' '.$object->event_family_css_class;
			}
		}

		return 0;
	}

	/**
	 * Hook pour ajouter le JavaScript de gestion des checkboxes CalDAV
	 *
	 * @param  array        $parameters Paramètres du hook
	 * @param  Object       $object     Objet concerné
	 * @param  string       $action     Action en cours
	 * @param  HookManager  $hookmanager Instance du gestionnaire de hooks
	 * @return int                     <0 si KO, 0 si aucune action, >0 si OK
	 */
	public function addCalendarJS($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		// Vérifier que le module est activé
		if (!$this->isModuleEnabled()) {
			return 0;
		}

		// Ajouter le JavaScript pour gérer l'affichage/masquage des événements CalDAV
		$js = '<script type="text/javascript">
jQuery(document).ready(function () {
	// Gérer les checkboxes CalDAV au chargement de la page
	jQuery(".caldav-calendar-checkbox").each(function() {
		var checkbox = jQuery(this);
		var htmlname = checkbox.attr("id").replace("check_ext", "");
		
		// Afficher ou masquer les événements selon l\'état de la checkbox
		if (checkbox.is(":checked")) {
			jQuery(".family_ext" + htmlname).show();
		} else {
			jQuery(".family_ext" + htmlname).hide();
		}
	});
	
	// Gérer les clics sur les checkboxes CalDAV
	jQuery(".caldav-calendar-checkbox").on("change", function() {
		var checkbox = jQuery(this);
		var htmlname = checkbox.attr("id").replace("check_ext", "");
		
		// Afficher ou masquer les événements
		if (checkbox.is(":checked")) {
			jQuery(".family_ext" + htmlname).show();
		} else {
			jQuery(".family_ext" + htmlname).hide();
		}
	});
});
</script>';

		$this->resprints = $js;
		return 0;
	}

	/**
	 * Hook eventOptions — uniquement sur comm/action/index.php (show_day_events) : marqueur pour recolorer la bordure gauche des tuiles.
	 *
	 * @param  array        $parameters  Paramètres du hook
	 * @param  ActionComm   $object      Événement agenda
	 * @param  string       $action      Action en cours
	 * @param  HookManager  $hookmanager Gestionnaire de hooks
	 * @return int                      0
	 */
	public function eventOptions($parameters, &$object, &$action, $hookmanager)
	{
		if (!$this->isModuleEnabled()) {
			return 0;
		}

		if (!is_object($object) || !($object instanceof ActionComm)) {
			return 0;
		}

		// Filet de sécurité calendrier (Dolibarr 24) : si le SQL n'a pas exclu l'auto,
		// on pose un marqueur. Le CSS + le script de fin de page retirent la tuile.
		if (CaldavclientAgendaListSql::isHideSystemAutoEnabled()
			&& CaldavclientAgendaListSql::isSystemAutoEvent($object)
		) {
			print '<span class="caldavclient-hide-systemauto-flag" style="display:none!important" aria-hidden="true"></span>';
		}

		if (!getDolGlobalString('CALDAVCLIENT_AGENDA_TILE_BY_USER')) {
			return 0;
		}

		$hex = CaldavclientAgendaTileStyle::resolveBorderHexForEvent($this->db, $object);
		if ($hex === '') {
			return 0;
		}

		// Dolibarr n'affiche $hookmanager->resPrint pour eventOptions que si un hook retourne > 0
		// (remplacement complet du bloc) ; avec return 0, resprints est ignoré. Il faut écrire directement.
		print '<span class="caldavclient-agenda-border-flag" data-border="#'.dol_escape_htmltag($hex).'" style="display:none!important" aria-hidden="true"></span>';

		return 0;
	}

	/**
	 * Hook llxFooter — supprime la légende à cases à cocher sur comm/action/index.php (vues calendrier).
	 * Retire du DOM tout ce qui se trouve entre la barre de titre et le bloc filtres (scripts + cases Dolibarr + externes).
	 *
	 * @param  array        $parameters  Paramètres du hook
	 * @param  mixed        $object      Objet contexte
	 * @param  string       $action      Action en cours
	 * @param  HookManager  $hookmanager Gestionnaire de hooks
	 * @return int                      0
	 */
	public function llxFooter($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs;

		if (!$this->isModuleEnabled()) {
			return 0;
		}
		if (empty($_SERVER['PHP_SELF']) || !preg_match('#/comm/action/index\.php$#', (string) $_SERVER['PHP_SELF'])) {
			return 0;
		}

		$langs->load('caldavclient@caldavclient');
		$label_allday_js = dol_escape_js($langs->transnoentitiesnoconv('CalDAVDayGridAllDay'));
		$agenda_start_week_js = (int) getDolGlobalInt('MAIN_START_WEEK', 1);
		$hide_systemauto = CaldavclientAgendaListSql::isHideSystemAutoEnabled();

		$nonce = function_exists('getNonce') ? getNonce() : '';
		$nonceattr = $nonce !== '' ? ' nonce="'.dol_escape_htmltag($nonce).'"' : '';

		$this->resprints = CaldavclientAgendaFooterScript::buildLlxFooterScript(
			$nonceattr,
			$agenda_start_week_js,
			$label_allday_js,
			$hide_systemauto
		);

		return 0;
	}

	/**
	 * Hook getCalendarEvents - Récupérer les événements CalDAV pour l'agenda classique (comm/action)
	 * Format attendu : tableau associatif avec clé = date 'YYYY-MM-DD' et valeur = tableau d'événements
	 *
	 * @param  array        $parameters Paramètres du hook
	 * @param  Object       $object     Objet concerné
	 * @param  string       $action     Action en cours
	 * @param  HookManager  $hookmanager Instance du gestionnaire de hooks
	 * @return int                     <0 si KO, 0 si aucune action, >0 si OK
	 */
	public function getCalendarEvents($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $db;

		if (!$this->isModuleEnabled()) {
			dol_syslog("CalDAV: Module non activé dans getCalendarEvents", LOG_DEBUG);
			return 0;
		}

		dol_syslog("CalDAV: Hook getCalendarEvents appelé pour l'agenda classique", LOG_DEBUG);

		$native = new CaldavclientAgendaNativeEvents($db);
		return $native->mergeIntoHookEventArray($hookmanager);
	}
}
