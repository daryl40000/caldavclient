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
 * \file       htdocs/custom/caldavclient/lib/caldav/caldavclient_discovery.trait.php
 * \ingroup    caldavclient
 * \brief      Découverte des calendriers (PROPFIND, parse XML).
 */

trait CalDAVClientDiscoveryTrait
{
	/**
	 * Découvrir les calendriers disponibles sur le serveur CalDAV
	 *
	 * @param  string $base_path   Chemin de base pour la découverte (optionnel)
	 * @return array               Tableau de calendriers avec 'name', 'displayname', 'url'
	 */
	public function discoverCalendars($base_path = null)
	{
		$calendars = array();
		
		// Chemins standards pour la découverte des calendriers (Nextcloud, OwnCloud, etc.)
		$discovery_paths = array(
			'/remote.php/dav/calendars/'.$this->username.'/',
			'/remote.php/dav/calendars/'.$this->username,
			'/caldav/calendars/'.$this->username.'/',
			'/caldav/',
			'/dav/calendars/'.$this->username.'/',
			'/dav/calendars/',
			'/'
		);
		
		if ($base_path) {
			// Si un chemin est fourni, l'utiliser en premier
			$discovery_paths = array_merge(array($base_path), $discovery_paths);
		}
		
		foreach ($discovery_paths as $path) {
			// Essayer d'abord avec Depth: 1 pour obtenir les calendriers directement
			$xml = '<?xml version="1.0" encoding="utf-8" ?>';
			$xml .= '<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:cs="http://calendarserver.org/ns/">';
			$xml .= '<d:prop>';
			$xml .= '<d:resourcetype/>';
			$xml .= '<d:displayname/>';
			$xml .= '<c:calendar-description/>';
			$xml .= '<cs:getctag/>';
			$xml .= '</d:prop>';
			$xml .= '</d:propfind>';
			
			$headers = array(
				'Content-Type: application/xml; charset=utf-8',
				'Depth: 1'
			);
			
			$response = $this->request('PROPFIND', $path, $xml, $headers);
			
			dol_syslog("CalDAV Discovery: Tentative sur le chemin '".$path."' (Depth: 1) - Code HTTP: ".$response['code'], LOG_DEBUG);
			
			if ($response['code'] >= 200 && $response['code'] < 300) {
				if (!empty($response['body'])) {
					dol_syslog("CalDAV Discovery: Réponse reçue (".strlen($response['body'])." octets)", LOG_DEBUG);
					$found_calendars = $this->parseCalendarDiscovery($response['body'], $path);
					dol_syslog("CalDAV Discovery: ".count($found_calendars)." calendrier(s) trouvé(s) sur le chemin '".$path."'", LOG_DEBUG);
					$calendars = array_merge($calendars, $found_calendars);
					
					// Si on a trouvé des calendriers, on peut arrêter de chercher
					if (count($found_calendars) > 0) {
						break;
					}
				} else {
					dol_syslog("CalDAV Discovery: Réponse vide pour le chemin '".$path."'", LOG_WARNING);
				}
			} elseif ($response['code'] == 404) {
				// Chemin non trouvé, continuer avec le suivant
				dol_syslog("CalDAV Discovery: Chemin '".$path."' non trouvé (404)", LOG_DEBUG);
			} else {
				dol_syslog("CalDAV Discovery: Erreur HTTP ".$response['code']." pour le chemin '".$path."' - Réponse: ".substr($response['body'], 0, 200), LOG_WARNING);
			}
		}
		
		// Supprimer les doublons basés sur l'URL
		$unique_calendars = array();
		$seen_urls = array();
		foreach ($calendars as $calendar) {
			if (!in_array($calendar['url'], $seen_urls)) {
				$unique_calendars[] = $calendar;
				$seen_urls[] = $calendar['url'];
			}
		}
		
		dol_syslog("CalDAV Discovery: Total de ".count($unique_calendars)." calendrier(s) unique(s) trouvé(s)", LOG_DEBUG);
		
		return $unique_calendars;
	}

	/**
	 * Parser la réponse PROPFIND pour découvrir les calendriers
	 *
	 * @param  string $xml_body   Corps XML de la réponse
	 * @param  string $base_path  Chemin de base
	 * @return array              Tableau de calendriers
	 */
	private function parseCalendarDiscovery($xml_body, $base_path)
	{
		$calendars = array();
		
		// Parser le XML
		$xml = @simplexml_load_string($xml_body);
		if ($xml === false) {
			dol_syslog("CalDAV Discovery: Erreur lors du parsing XML - ".libxml_get_last_error(), LOG_WARNING);
			return $calendars;
		}
		
		// Enregistrer les namespaces
		$xml->registerXPathNamespace('d', 'DAV:');
		$xml->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');
		$xml->registerXPathNamespace('cs', 'http://calendarserver.org/ns/');
		
		// Chercher tous les éléments response
		$responses = $xml->xpath('//d:response');
		
		if (empty($responses) || count($responses) == 0) {
			dol_syslog("CalDAV Discovery: Aucun élément 'response' trouvé dans le XML", LOG_DEBUG);
			// Sauvegarder le XML pour déboguer
			dol_syslog("CalDAV Discovery: XML reçu: ".substr($xml_body, 0, 500), LOG_DEBUG);
		}
		
		foreach ($responses as $response) {
			$response->registerXPathNamespace('d', 'DAV:');
			$response->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');
			$response->registerXPathNamespace('cs', 'http://calendarserver.org/ns/');
			
			// Récupérer l'URL de la ressource
			$href_nodes = $response->xpath('.//d:href');
			if (count($href_nodes) == 0) {
				continue;
			}
			$href = (string) $href_nodes[0];
			
			// Vérifier si c'est un calendrier (contient calendar dans le resourcetype)
			$resourcetype_nodes = $response->xpath('.//d:resourcetype');
			$is_calendar = false;
			if (count($resourcetype_nodes) > 0) {
				$resourcetype = $resourcetype_nodes[0];
				$resourcetype->registerXPathNamespace('d', 'DAV:');
				$resourcetype->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');
				$calendar_nodes = $resourcetype->xpath('.//c:calendar');
				$is_calendar = count($calendar_nodes) > 0;
				
				// Si ce n'est pas un calendrier mais une collection, on peut l'ignorer ou la suivre
				if (!$is_calendar) {
					// Vérifier si c'est une collection (peut contenir des calendriers)
					$collection_nodes = $resourcetype->xpath('.//d:collection');
					// Pour l'instant, on ignore les collections qui ne sont pas des calendriers
				}
			}
			
			if ($is_calendar) {
				// Récupérer le nom d'affichage
				$displayname = '';
				$displayname_nodes = $response->xpath('.//d:displayname');
				if (count($displayname_nodes) > 0) {
					$displayname = (string) $displayname_nodes[0];
				}
				
				// Extraire le nom du calendrier depuis l'URL (href est relatif)
				$name = basename(rtrim($href, '/'));
				if (empty($name) || $name == basename(rtrim($base_path, '/'))) {
					$name = $displayname ? $displayname : 'Calendrier';
				}
				
				// Construire l'URL complète du calendrier
				// L'URL dans href est généralement relative au chemin de base
				if (strpos($href, 'http') === 0) {
					// URL absolue
					$calendar_url = $href;
				} elseif (strpos($href, '/') === 0) {
					// URL absolue depuis la racine du serveur
					$calendar_url = $this->url.$href;
				} else {
					// URL relative, la combiner avec le chemin de base
					$calendar_url = $this->url.rtrim($base_path, '/').'/'.ltrim($href, '/');
				}
				
				// S'assurer que l'URL se termine par /
				if (substr($calendar_url, -1) != '/') {
					$calendar_url .= '/';
				}
				
				$calendars[] = array(
					'name' => $name,
					'displayname' => $displayname ? $displayname : $name,
					'url' => $calendar_url
				);
				
				dol_syslog("CalDAV Discovery: Calendrier trouvé - Nom: '".$name."', DisplayName: '".$displayname."', URL: '".$calendar_url."'", LOG_DEBUG);
			}
		}
		
		return $calendars;
	}
}
