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
 * \file       class/agenda/caldavclient_agenda_tile_style.class.php
 * \ingroup    caldavclient
 * \brief      Couleur bordure tuiles agenda (hook eventOptions) — cache utilisateur + hex occupé/libre.
 */

require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

/**
 * Calcule la couleur de liseré gauche des tuiles événement (1 / plusieurs utilisateurs affectés).
 */
class CaldavclientAgendaTileStyle
{
	/**
	 * @var array<int,string> hex 6 sans # par id utilisateur
	 */
	private static $cacheUserAgendaHex = array();

	/**
	 * Retourne la couleur fiche utilisateur en hex 6 sans #, ou chaîne vide.
	 *
	 * @param  DoliDB $db  Handler base
	 * @param  int    $uid Id utilisateur
	 * @return string
	 */
	public static function getUserCardColorHex6(DoliDB $db, $uid)
	{
		$uid = (int) $uid;
		if ($uid <= 0) {
			return '';
		}
		if (isset(self::$cacheUserAgendaHex[$uid])) {
			return self::$cacheUserAgendaHex[$uid];
		}
		$u = new User($db);
		if ($u->fetch($uid) <= 0) {
			self::$cacheUserAgendaHex[$uid] = '';
			return '';
		}
		$c = trim(str_replace('#', '', (string) $u->color));
		if ($c === '') {
			self::$cacheUserAgendaHex[$uid] = '';
			return '';
		}
		if (strlen($c) === 3 && preg_match('/^[0-9A-Fa-f]{3}$/', $c)) {
			$c = $c[0].$c[0].$c[1].$c[1].$c[2].$c[2];
		}
		if (!preg_match('/^[0-9A-Fa-f]{6}$/', $c)) {
			self::$cacheUserAgendaHex[$uid] = '';
			return '';
		}
		self::$cacheUserAgendaHex[$uid] = strtoupper($c);
		return self::$cacheUserAgendaHex[$uid];
	}

	/**
	 * Assombrit légèrement la bordure quand l'événement est « occupé » (transparency).
	 *
	 * @param  string $hex6 Hex 6 sans #
	 * @param  bool   $busy true si transparency non vide
	 * @return string       Hex 6 sans #
	 */
	public static function adjustBorderHexForBusyState($hex6, $busy)
	{
		$hex6 = strtolower((string) $hex6);
		if (!$busy || !preg_match('/^[0-9a-f]{6}$/', $hex6)) {
			return strtoupper($hex6);
		}
		$r = hexdec(substr($hex6, 0, 2));
		$g = hexdec(substr($hex6, 2, 2));
		$b = hexdec(substr($hex6, 4, 2));
		$f = 0.82;
		$r = max(0, min(255, (int) round($r * $f)));
		$g = max(0, min(255, (int) round($g * $f)));
		$b = max(0, min(255, (int) round($b * $f)));
		return sprintf('%02X%02X%02X', $r, $g, $b);
	}

	/**
	 * Hex 6 sans # pour data-border, ou chaîne vide si le hook ne doit rien afficher.
	 *
	 * @param  DoliDB     $db     Handler base
	 * @param  ActionComm $object Événement agenda
	 * @return string             '' ou [A-F0-9]{6}
	 */
	public static function resolveBorderHexForEvent(DoliDB $db, ActionComm $object)
	{
		$tc = (string) $object->type_code;
		if (in_array($tc, array('BIRTHDAY', 'HOLIDAY', 'ICALEVENT'), true)) {
			return '';
		}
		if (isset($object->type) && (string) $object->type === 'bookcal_calendar') {
			return '';
		}

		$assigned = is_array($object->userassigned) ? $object->userassigned : array();
		$n = count($assigned);
		$hex = '';

		if ($n === 1) {
			$uid = (int) key($assigned);
			if ($uid <= 0) {
				return '';
			}
			$hex = self::getUserCardColorHex6($db, $uid);
			if ($hex === '') {
				return '';
			}
		} elseif ($n > 1) {
			$hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) getDolGlobalString('CALDAVCLIENT_AGENDA_MULTIUSER_COLOR', 'B85450')));
			if (strlen($hex) !== 6) {
				$hex = 'B85450';
			}
		} else {
			return '';
		}

		$busy = !empty($object->transparency);
		return self::adjustBorderHexForBusyState($hex, $busy);
	}
}
