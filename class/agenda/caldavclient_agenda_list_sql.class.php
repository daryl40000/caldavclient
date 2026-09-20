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
 * \brief      Fragments SQL pour les hooks liste / filtre agenda (sans toucher au core).
 */

/**
 * Filtre agenda : exclure les types d'action systemauto via INNER JOIN (hook printFieldListFrom).
 */
class CaldavclientAgendaListSql
{
	/**
	 * INNER JOIN à concaténer à la requête liste agenda pour exclure systemauto.
	 *
	 * @param  DoliDB $db Handler base
	 * @return string     Fragment SQL (vide si KO théorique — ici toujours une chaîne fixe échappée)
	 */
	public static function getExcludeSystemAutoJoin(DoliDB $db)
	{
		$sql = " INNER JOIN ".MAIN_DB_PREFIX."c_actioncomm AS caldav_agenda_sysfilter";
		$sql .= " ON caldav_agenda_sysfilter.id = a.fk_action";
		$sql .= " AND (caldav_agenda_sysfilter.type IS NULL OR caldav_agenda_sysfilter.type <> '".$db->escape('systemauto')."')";
		return $sql;
	}
}
