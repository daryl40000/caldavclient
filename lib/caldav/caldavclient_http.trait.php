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
 * \file       htdocs/custom/caldavclient/lib/caldav/caldavclient_http.trait.php
 * \ingroup    caldavclient
 * \brief      Transport HTTP (cURL / file_get_contents) pour CalDAVClient.
 */

trait CalDAVClientHttpTrait
{
	/**
	 * Effectuer une requête HTTP vers le serveur CalDAV
	 *
	 * @param  string $method   Méthode HTTP (GET, POST, PUT, DELETE, PROPFIND, REPORT)
	 * @param  string $path     Chemin de la ressource
	 * @param  string $body     Corps de la requête (optionnel)
	 * @param  array  $headers  En-têtes supplémentaires (optionnel)
	 * @return array            Tableau avec 'code' (code HTTP) et 'body' (corps de la réponse)
	 */
	private function request($method, $path, $body = null, $headers = array())
	{
		// Si le chemin est déjà une URL complète (commence par http:// ou https://), l'utiliser directement
		if (preg_match('/^https?:\/\//', $path)) {
			$url = $path;
		} else {
			// Sinon, construire l'URL en combinant l'URL de base et le chemin
			$url = rtrim($this->url, '/').'/'.ltrim($path, '/');
		}

		// Le mot de passe CalDAV voyage dans la requête : HTTP (sans chiffrement) est refusé.
		$https_error = $this->rejectIfNotHttps($url);
		if ($https_error !== '') {
			$res = array('code' => 0, 'body' => '', 'error' => $https_error);
			$this->last_response = array_merge(array('method' => $method, 'url' => $url), $res);
			return $res;
		}

		if (!$this->ssl_verify) {
			dol_syslog("CalDAV: vérification SSL désactivée pour cette connexion (réservé aux tests)", LOG_WARNING);
		}
		
		dol_syslog("CalDAV request: Méthode=".$method.", URL=".$url, LOG_DEBUG);
		
		// Utiliser cURL si disponible (plus fiable pour CalDAV)
		if (function_exists('curl_init')) {
			$res = $this->requestCurl($method, $url, $body, $headers);
			$this->last_response = array_merge(array('method' => $method, 'url' => $url), is_array($res) ? $res : array());
			return $res;
		}
		
		// Fallback vers file_get_contents
		$res = $this->requestFileGetContents($method, $url, $body, $headers);
		$this->last_response = array_merge(array('method' => $method, 'url' => $url), is_array($res) ? $res : array());
		return $res;
	}
	
	/**
	 * Effectuer une requête HTTP avec cURL
	 *
	 * @param  string $method   Méthode HTTP
	 * @param  string $url      URL complète
	 * @param  string $body     Corps de la requête
	 * @param  array  $headers  En-têtes supplémentaires
	 * @return array            Tableau avec 'code' et 'body'
	 */
	private function requestCurl($method, $url, $body = null, $headers = array())
	{
		$ch = curl_init();
		
		// Préparer les en-têtes
		$default_headers = array(
			'Authorization: Basic '.base64_encode($this->username.':'.$this->password),
			'Content-Type: text/calendar; charset=utf-8',
			'Depth: 1'
		);
		
		// Ajouter les en-têtes spécifiques selon la méthode
		if ($method == 'PROPFIND' || $method == 'REPORT') {
			$default_headers[1] = 'Content-Type: application/xml; charset=utf-8';
		}
		
		$all_headers = array_merge($default_headers, $headers);
		
		// Configuration cURL
		curl_setopt_array($ch, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => $all_headers,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_CONNECTTIMEOUT => 10,
			// Vérification du certificat par défaut (true / 2).
			// Si ssl_verify est faux : certificat auto-signé accepté, moins sûr.
			CURLOPT_SSL_VERIFYPEER => $this->ssl_verify ? true : false,
			CURLOPT_SSL_VERIFYHOST => $this->ssl_verify ? 2 : 0,
			CURLOPT_USERPWD => $this->username.':'.$this->password,
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
			CURLOPT_VERBOSE => false,
			CURLOPT_HEADER => true // Inclure les en-têtes dans la réponse pour le débogage
		));
		
		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}
		
		$response = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		$curl_info = curl_getinfo($ch);
		
		curl_close($ch);
		
		if ($error) {
			dol_syslog("CalDAV cURL Error: ".$error, LOG_WARNING);
			if (stripos($error, 'SSL') !== false || stripos($error, 'certificate') !== false) {
				$error = "Certificat SSL invalide ou non vérifiable. Utilisez une URL https:// avec un certificat valide, ou désactivez temporairement la vérification SSL sur cette connexion (serveur de test uniquement). Détail : ".$error;
			}
			return array('code' => 500, 'body' => '', 'error' => $error, 'info' => $curl_info);
		}
		
		// Séparer les en-têtes du corps si CURLOPT_HEADER est activé
		$header_size = isset($curl_info['header_size']) ? $curl_info['header_size'] : 0;
		$response_headers = $header_size > 0 ? substr($response, 0, $header_size) : '';
		$response_body = $header_size > 0 ? substr($response, $header_size) : $response;
		
		return array(
			'code' => $code ? $code : 500,
			'body' => $response_body !== false ? $response_body : '',
			'headers' => $response_headers,
			'info' => $curl_info
		);
	}
	
	/**
	 * Effectuer une requête HTTP avec file_get_contents (fallback)
	 *
	 * @param  string $method   Méthode HTTP
	 * @param  string $url      URL complète
	 * @param  string $body     Corps de la requête
	 * @param  array  $headers  En-têtes supplémentaires
	 * @return array            Tableau avec 'code' et 'body'
	 */
	private function requestFileGetContents($method, $url, $body = null, $headers = array())
	{
		// Préparer les en-têtes
		$default_headers = array(
			'Authorization: Basic '.base64_encode($this->username.':'.$this->password),
			'Content-Type: text/calendar; charset=utf-8',
			'Depth: 1'
		);
		
		// Ajouter les en-têtes spécifiques selon la méthode
		if ($method == 'PROPFIND' || $method == 'REPORT') {
			$default_headers[1] = 'Content-Type: application/xml; charset=utf-8';
		}
		
		$all_headers = array_merge($default_headers, $headers);
		
		// Configuration du contexte
		$opts = array(
			'http' => array(
				'method' => $method,
				'header' => implode("\r\n", $all_headers),
				'timeout' => 30,
				'ignore_errors' => true
			),
			'ssl' => array(
				'verify_peer' => !empty($this->ssl_verify),
				'verify_peer_name' => !empty($this->ssl_verify)
			)
		);
		
		if ($body !== null) {
			$opts['http']['content'] = $body;
		}
		
		$context = stream_context_create($opts);
		
		// Effectuer la requête
		$response = @file_get_contents($url, false, $context);
		
		// Récupérer le code de statut HTTP depuis $http_response_header
		$code = 200;
		if (isset($http_response_header) && is_array($http_response_header) && count($http_response_header) > 0) {
			preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
			if (isset($matches[1])) {
				$code = (int) $matches[1];
			}
		}
		
		// Si la réponse est vide et le code est 200, vérifier s'il y a eu une erreur
		if ($response === false && $code == 200) {
			$code = 500; // Erreur serveur par défaut
		}
		
		return array(
			'code' => $code,
			'body' => $response !== false ? $response : ''
		);
	}

	/**
	 * Refuse une URL qui n'est pas en HTTPS.
	 *
	 * @param  string $url URL complète de la requête
	 * @return string      Message d'erreur, ou chaîne vide si l'URL est acceptable
	 */
	private function rejectIfNotHttps($url)
	{
		if (preg_match('#^https://#i', (string) $url)) {
			return '';
		}
		$msg = "URL CalDAV refusée : seul HTTPS est autorisé (protection du mot de passe).";
		dol_syslog("CalDAV: ".$msg." URL=".$url, LOG_ERR);
		return $msg;
	}
}
