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
 * \file       class/agenda/caldavclient_agenda_list_sql.class.php
 * \ingroup    caldavclient
 * \brief      Fragments SQL et tests pour masquer les événements systemauto (agenda natif).
 */

/**
 * Filtre agenda : exclure les types d'action « automatiques système »
 * (llx_c_actioncomm.type = 'systemauto').
 *
 * Pourquoi deux fragments SQL ?
 * - Calendrier (comm/action/index.php, Dolibarr 24) : seul le crochet printFieldListFrom
 *   existe. On injecte un JOIN qui ne dépend que de l'alias `a` (toujours présent).
 * - Liste (comm/action/list.php, Dolibarr 24) : l'alias du dictionnaire est `c`
 *   (plus `ca`). On injecte un AND NOT EXISTS via printFieldListWhere, toujours
 *   basé sur `a.fk_action`, donc valable avant et après la 24.
 */
class CaldavclientAgendaListSql
{
	/**
	 * Le module CalDAV Client est-il activé ?
	 * Dolibarr 20+ recommande isModEnabled() ; $conf->xxx->enabled reste un repli.
	 *
	 * @return bool
	 */
	public static function isCalDavClientEnabled()
	{
		if (function_exists('isModEnabled')) {
			return isModEnabled('caldavclient');
		}
		global $conf;
		return !empty($conf->caldavclient->enabled);
	}

	/**
	 * L'option « masquer les événements automatiques système » est-elle cochée ?
	 *
	 * @return bool
	 */
	public static function isHideSystemAutoEnabled()
	{
		if (!self::isCalDavClientEnabled()) {
			return false;
		}
		return (bool) getDolGlobalString('CALDAVCLIENT_HIDE_AGENDA_SYSTEMAUTO');
	}

	/**
	 * JOIN à coller dans FROM (calendrier). Une table factice d'une seule ligne
	 * sert de « portail » : la vraie condition est dans ON (NOT EXISTS …).
	 *
	 * On n'utilise que l'alias `a` (table actioncomm), commun au calendrier et
	 * à la liste. On évite `ca` / `c` : Dolibarr 24 les a divergés.
	 *
	 * @param  DoliDB $db Handler base
	 * @return string     Fragment SQL commençant par un espace
	 */
	public static function getExcludeSystemAutoJoin(DoliDB $db)
	{
		$table_types = MAIN_DB_PREFIX.'c_actioncomm';
		$valeur_systemauto = $db->escape('systemauto');

		$sql = " INNER JOIN (SELECT 1 AS caldav_hide_sysauto_ok) AS caldav_hide_sysauto";
		$sql .= " ON NOT EXISTS (";
		$sql .= " SELECT 1 FROM ".$table_types." AS caldav_sysauto_ex";
		$sql .= " WHERE caldav_sysauto_ex.id = a.fk_action";
		$sql .= " AND caldav_sysauto_ex.type = '".$valeur_systemauto."'";
		$sql .= " )";
		return $sql;
	}

	/**
	 * Condition WHERE à coller sur la liste (et toute page qui a printFieldListWhere).
	 * Même logique que le JOIN, indépendante de l'alias du dictionnaire.
	 *
	 * @param  DoliDB $db Handler base
	 * @return string     Fragment SQL commençant par AND
	 */
	public static function getExcludeSystemAutoWhere(DoliDB $db)
	{
		$table_types = MAIN_DB_PREFIX.'c_actioncomm';
		$valeur_systemauto = $db->escape('systemauto');

		$sql = " AND NOT EXISTS (";
		$sql .= " SELECT 1 FROM ".$table_types." AS caldav_sysauto_ex";
		$sql .= " WHERE caldav_sysauto_ex.id = a.fk_action";
		$sql .= " AND caldav_sysauto_ex.type = '".$valeur_systemauto."'";
		$sql .= " )";
		return $sql;
	}

	/**
	 * L'événement agenda est-il un automatique système ?
	 * Utilisé comme filet de sécurité à l'affichage (tuiles du calendrier).
	 *
	 * @param  object $event ActionComm ou objet similaire (type, type_code, code)
	 * @return bool
	 */
	public static function isSystemAutoEvent($event)
	{
		if (!is_object($event)) {
			return false;
		}

		// Cas principal : Dolibarr renseigne ca.type dans $event->type (calendrier index.php).
		if (isset($event->type) && (string) $event->type === 'systemauto') {
			return true;
		}

		$code = '';
		if (!empty($event->type_code)) {
			$code = (string) $event->type_code;
		} elseif (!empty($event->code)) {
			$code = (string) $event->code;
		}

		// Sécurité : codes du dictionnaire du type AC_OTH_AUTO, AC_PROPAL_AUTO, etc.
		if ($code !== '' && preg_match('/_AUTO$/', $code)) {
			return true;
		}

		return false;
	}
}
