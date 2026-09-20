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
 * \file       htdocs/custom/caldavclient/lib/caldav.lib.php
 * \ingroup    caldavclient
 * \brief      Point d’entrée client CalDAV ; implémentation répartie dans lib/caldav/*.trait.php
 */

require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
require_once __DIR__.'/caldav/caldavclient_http.trait.php';
require_once __DIR__.'/caldav/caldavclient_discovery.trait.php';
require_once __DIR__.'/caldav/caldavclient_fetch.trait.php';
require_once __DIR__.'/caldav/caldavclient_ical.trait.php';
require_once __DIR__.'/caldav/caldavclient_crud.trait.php';

class CalDAVClient
{
	use CalDAVClientHttpTrait;
	use CalDAVClientDiscoveryTrait;
	use CalDAVClientFetchTrait;
	use CalDAVClientICalTrait;
	use CalDAVClientCrudTrait;

	/**
	 * @var array|null Dernière réponse HTTP (debug)
	 */
	public $last_response = null;

	/**
	 * @var string URL du serveur CalDAV
	 */
	private $url;

	/**
	 * @var string Nom d'utilisateur
	 */
	private $username;

	/**
	 * @var string Mot de passe
	 */
	private $password;

	/**
	 * @var string Chemin du calendrier
	 */
	private $calendar_path;

	/**
	 * @var bool Vérifier le certificat SSL du serveur (false = serveur de test uniquement)
	 */
	private $ssl_verify = true;

	/**
	 * @var resource Contexte de flux pour les requêtes HTTP
	 */
	private $context;

	/**
	 * Constructeur
	 *
	 * @param CalDAVConnection $connection Connexion CalDAV
	 */
	public function __construct($connection)
	{
		$this->url = rtrim($connection->url, '/');
		$this->username = $connection->username;
		$this->password = $connection->password;
		$this->calendar_path = $connection->calendar_path ? $connection->calendar_path : '/remote.php/dav/calendars/'.$connection->username.'/';
		// Par défaut on vérifie le certificat (protège contre une attaque « homme du milieu »).
		$this->ssl_verify = (!isset($connection->ssl_verify) || (int) $connection->ssl_verify) ? true : false;

		// Configuration du contexte HTTP avec authentification
		$auth = base64_encode($this->username.':'.$this->password);
		$opts = array(
			'http' => array(
				'method' => 'GET',
				'header' => array(
					'Authorization: Basic '.$auth,
					'Content-Type: text/calendar; charset=utf-8',
					'Depth: 1'
				),
				'timeout' => 30
			),
			'ssl' => array(
				'verify_peer' => $this->ssl_verify,
				'verify_peer_name' => $this->ssl_verify
			)
		);
		$this->context = stream_context_create($opts);
	}
}
