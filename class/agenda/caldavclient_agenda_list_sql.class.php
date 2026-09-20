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
	 * Codes de type « automatique système » du dictionnaire Dolibarr.
	 *
	 * Liste volontairement explicite : un code maison finissant par « _AUTO »
	 * (ex. AC_RELANCE_AUTO créé par l'utilisateur) ne doit pas être masqué par
	 * erreur. La référence reste llx_c_actioncomm.type ; ceci n'est qu'un repli,
	 * aligné sur ce que fait le cœur Dolibarr avec AGENDA_ALWAYS_HIDE_AUTO.
	 */
	const TYPE_CODES_SYSTEMAUTO = array('AC_OTH_AUTO');

	/**
	 * Le module CalDAV Client est-il activé ?
	 *
	 * @return bool
	 */
	public static function isCalDavClientEnabled()
	{
		return isModEnabled('caldavclient');
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
	 * Condition « ce n'est pas un automatique système », partagée par le JOIN et le WHERE.
	 *
	 * On ne référence que l'alias `a` (table actioncomm), commun au calendrier et
	 * à la liste. On évite `ca` / `c` : Dolibarr 24 les a divergés.
	 *
	 * @param  DoliDB $db Handler base
	 * @return string     Fragment SQL commençant par un espace (NOT EXISTS …)
	 */
	private static function buildNotExistsSystemAuto(DoliDB $db)
	{
		$sql = " NOT EXISTS (";
		$sql .= " SELECT 1 FROM ".MAIN_DB_PREFIX."c_actioncomm AS caldav_sysauto_ex";
		$sql .= " WHERE caldav_sysauto_ex.id = a.fk_action";
		$sql .= " AND caldav_sysauto_ex.type = '".$db->escape('systemauto')."'";
		$sql .= " )";
		return $sql;
	}

	/**
	 * JOIN à coller dans FROM (calendrier). Une table factice d'une seule ligne
	 * sert de « portail » : la vraie condition est dans ON.
	 *
	 * Attention : ce fragment n'est utilisable que si aucune table n'a été ajoutée
	 * par une virgule juste avant (voir ActionsCaldavclient::printFieldListFrom).
	 *
	 * @param  DoliDB $db Handler base
	 * @return string     Fragment SQL commençant par un espace
	 */
	public static function getExcludeSystemAutoJoin(DoliDB $db)
	{
		$sql = " INNER JOIN (SELECT 1 AS caldav_hide_sysauto_ok) AS caldav_hide_sysauto";
		$sql .= " ON".self::buildNotExistsSystemAuto($db);
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
		return " AND".self::buildNotExistsSystemAuto($db);
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

		// Référence : llx_c_actioncomm.type, que Dolibarr recopie dans $event->type
		// (calendrier index.php). C'est la même définition que le filtre SQL.
		if (isset($event->type) && (string) $event->type === 'systemauto') {
			return true;
		}

		// Repli si le type n'a pas été chargé : uniquement les codes connus de Dolibarr.
		$code = '';
		if (!empty($event->type_code)) {
			$code = (string) $event->type_code;
		} elseif (!empty($event->code)) {
			$code = (string) $event->code;
		}

		return $code !== '' && in_array($code, self::TYPE_CODES_SYSTEMAUTO, true);
	}
}
