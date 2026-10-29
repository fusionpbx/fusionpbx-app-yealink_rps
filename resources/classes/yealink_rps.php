<?php

/*
	Yealink RPS Management Platform - JSON REST API client
	for FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2026
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

/**
 * Client for the Yealink RPS Management Platform JSON REST API.
 *
 * Supports two authentication schemes:
 *
 * 1. "x-ca" - the classic RPS open API signing scheme
 *    (reference: "Yealink Json API for RPS Management Platform V3.4.0.15"),
 *    used by the global/China RPS platform (e.g. https://dm.yealink.com).
 *    Every request is signed using HMAC-SHA256 over a "StringToSign" value
 *    built from the HTTP method, the auth headers (X-Ca-Key, X-Ca-Nonce,
 *    X-Ca-Timestamp), the API URI and the body (Content-MD5) or the sorted
 *    query/form string.
 *
 * 2. "developer" - the YMCS Open API V4X scheme (OAuth 2.0), used by the
 *    regional YMCS developer gateways (e.g. https://us-api.ymcs.yealink.com,
 *    https://eu-api.ymcs.yealink.com).
 *    Before any business call the client exchanges the AccessKey Id /
 *    AccessKey Secret (the OAuth2 client credentials) for a temporary
 *    access token:
 *        POST {base_url}/v2/token
 *        Authorization: Basic base64_encode(client_id:client_secret)
 *        timestamp: <millisecond epoch>
 *        nonce: <random, max length 32 bits>
 *        body: {"grant_type": "client_credentials"}
 *        response: {"access_token": "...", "token_type": "bearer", "expires_in": 86400}
 *    Every business request then carries only three auth headers:
 *        Authorization: Bearer <access_token>
 *        timestamp: <millisecond epoch>
 *        nonce: <random, max length 32 bits>
 *    The client refreshes the token automatically when it expires (or when
 *    the gateway answers "Invalid access token"). A token can also be
 *    pinned manually via the yealink_rps.access_token setting.
 *    The classic RPS API URIs (api/open/v1/*) are automatically translated
 *    to the equivalent V4X endpoints (v2/rps/*) by map_developer_uri().
 *
 * The auth mode is auto-detected from the base_url host (hosts containing
 * "ymcs" use the developer scheme) and can be forced with the
 * yealink_rps.auth_mode setting ("auto", "x-ca" or "developer").
 *
 * The settings category used for the credentials is "yealink_rps":
 *   - yealink_rps.enabled            true/false
 *   - yealink_rps.base_url           e.g. https://us-api.ymcs.yealink.com
 *   - yealink_rps.access_key_id      AccessKey Id (OAuth2 client id)
 *   - yealink_rps.access_key_secret  AccessKey Secret (OAuth2 client secret)
 *   - yealink_rps.access_token       optional pinned token (developer mode)
 *   - yealink_rps.auth_mode          auto | x-ca | developer
 */
class yealink_rps {

	/**
	 * Base URL of the Yealink RPS API (no trailing slash).
	 * @var string
	 */
	private $base_url;

	/**
	 * AccessKey Id.
	 * @var string
	 */
	private $access_key_id;

	/**
	 * AccessKey Secret.
	 * @var string
	 */
	private $access_key_secret;

	/**
	 * OAuth2 access token (developer auth mode). Obtained automatically
	 * from the /v2/token endpoint or pinned via the settings.
	 * @var string
	 */
	private $access_token;

	/**
	 * Epoch time (seconds) at which the current access token expires.
	 * 0 when the expiry is unknown (manually pinned token).
	 * @var int
	 */
	private $token_expires_at = 0;

	/**
	 * Auth mode: "x-ca", "developer" or "auto".
	 * @var string
	 */
	private $auth_mode = 'auto';

	/**
	 * Settings object used to load the credentials.
	 * @var settings
	 */
	private $settings;

	/**
	 * Constructor. Loads the RPS credentials from the settings object.
	 *
	 * @param settings $settings A settings object bound to the current domain.
	 * @param array    $override Optional overrides: base_url, access_key_id,
	 *                           access_key_secret, access_token, auth_mode.
	 *                           Used by the "Test Connection" feature to validate
	 *                           credentials before persisting them.
	 */
	public function __construct(settings $settings, array $override = []) {
		$this->settings = $settings;
		$this->base_url = rtrim(trim((string)($override['base_url'] ?? $settings->get('yealink_rps', 'base_url', 'https://dm.yealink.com'))), '/');
		$this->access_key_id = trim((string)($override['access_key_id'] ?? $settings->get('yealink_rps', 'access_key_id', '')));
		$this->access_key_secret = trim((string)($override['access_key_secret'] ?? $settings->get('yealink_rps', 'access_key_secret', '')));
		$this->access_token = trim((string)($override['access_token'] ?? $settings->get('yealink_rps', 'access_token', '')));
		$this->auth_mode = trim((string)($override['auth_mode'] ?? $settings->get('yealink_rps', 'auth_mode', 'auto')));
	}

	/**
	 * Resolve the effective auth mode.
	 *
	 * "auto" detects the scheme from the base_url host: regional YMCS
	 * developer gateways (hosts containing "ymcs") use the "developer"
	 * scheme, everything else uses the classic "x-ca" scheme.
	 *
	 * @return string "x-ca" or "developer"
	 */
	public function get_auth_mode() {
		if ($this->auth_mode === 'x-ca' || $this->auth_mode === 'developer') {
			return $this->auth_mode;
		}
		$host = parse_url($this->base_url, PHP_URL_HOST) ?: '';
		return (stripos($host, 'ymcs') !== false) ? 'developer' : 'x-ca';
	}

	/**
	 * Whether the client has the minimum credentials required to make a call.
	 *
	 * @return bool
	 */
	public function is_configured() {
		//both schemes only require the access key pair; the developer
		//gateway token is obtained automatically via OAuth2
		return !empty($this->access_key_id) && !empty($this->access_key_secret);
	}

	/**
	 * Which required credential is missing (for UI messaging).
	 *
	 * @return string 'keys' when the AccessKey Id/Secret are missing,
	 *                '' when the client is fully configured.
	 */
	public function missing_reason() {
		if (empty($this->access_key_id) || empty($this->access_key_secret)) {
			return 'keys';
		}
		return '';
	}

	/**
	 * AccessKey Id (exposed for the settings UI / test connection).
	 *
	 * @return string
	 */
	public function get_access_key_id() {
		return $this->access_key_id;
	}

	/**
	 * The configured base URL.
	 *
	 * @return string
	 */
	public function get_base_url() {
		return $this->base_url;
	}

	/**
	 * The current OAuth2 access token (developer auth mode).
	 *
	 * @return string
	 */
	public function get_access_token() {
		return $this->access_token;
	}

	/**
	 * The epoch time (seconds) at which the current access token expires,
	 * 0 when the expiry is unknown (manually pinned token).
	 *
	 * @return int
	 */
	public function get_token_expires_at() {
		return $this->token_expires_at;
	}

	/**
	 * Generate a v4 UUID (used for the X-Ca-Nonce header).
	 *
	 * @return string
	 */
	private function generate_uuid() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}

	/**
	 * Build the "FormattedQFStr" for a set of query/form parameters.
	 *
	 * Per the API spec the keys are sorted alphabetically and each pair is
	 * rendered as key=value and joined with "&". When a value is empty only
	 * the key is rendered (without the "=").
	 *
	 * @param array $params
	 * @return string
	 */
	private function format_qf_str(array $params) {
		ksort($params);
		$parts = [];
		foreach ($params as $key => $value) {
			$key = (string)$key;
			$value = (string)$value;
			if ($value === '') {
				$parts[] = $key;
			}
			else {
				$parts[] = $key . '=' . $value;
			}
		}
		return implode('&', $parts);
	}

	/**
	 * Build the StringToSign value exactly as described in the API document.
	 *
	 * Body (JSON) request:
	 *   METHOD \n Content-MD5:x \n X-Ca-Key:x \n X-Ca-Nonce:x \n X-Ca-Timestamp:x \n API_URI
	 *
	 * Query/Form request:
	 *   METHOD \n X-Ca-Key:x \n X-Ca-Nonce:x \n X-Ca-Timestamp:x \n API_URI \n FormattedQFStr
	 *
	 * @param string $method        HTTP method (GET|POST)
	 * @param string $api_uri       API URI without the leading slash, e.g. api/open/v1/server/add
	 * @param string $content_md5   Base64(MD5 body) when a body is used, empty otherwise
	 * @param string $access_key_id
	 * @param string $nonce
	 * @param string $timestamp
	 * @param string $qf_str        FormattedQFStr (empty for body requests)
	 * @return string
	 */
	private function build_string_to_sign($method, $api_uri, $content_md5, $access_key_id, $nonce, $timestamp, $qf_str) {
		if ($content_md5 !== '') {
			//Body parameter
			return $method . "\n"
				. "Content-MD5:" . $content_md5 . "\n"
				. "X-Ca-Key:" . $access_key_id . "\n"
				. "X-Ca-Nonce:" . $nonce . "\n"
				. "X-Ca-Timestamp:" . $timestamp . "\n"
				. $api_uri;
		}

		//Query or form parameter
		return $method . "\n"
			. "X-Ca-Key:" . $access_key_id . "\n"
			. "X-Ca-Nonce:" . $nonce . "\n"
			. "X-Ca-Timestamp:" . $timestamp . "\n"
			. $api_uri . "\n"
			. $qf_str;
	}

	/**
	 * Generate a random nonce for the developer gateway
	 * (32 hex characters, per the Open API V4X examples).
	 *
	 * @return string
	 */
	private function generate_nonce() {
		return bin2hex(random_bytes(16));
		}

	/**
	 * Perform a raw HTTP request with cURL.
	 *
	 * @param string $url
	 * @param string $method          HTTP method (GET|POST)
	 * @param array  $request_headers Full header array ("Name: value" strings)
	 * @param string $body_str        Raw request body ('' when none)
	 * @return array [response, http_code, curl_error]
	 */
	private function perform_request($url, $method, array $request_headers, $body_str = '') {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		if ($body_str !== '') {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body_str);
			}
		$response = curl_exec($ch);
		$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);
		curl_close($ch);
		return [$response, $http_code, $curl_error];
		}

	/**
	 * Obtain a fresh OAuth2 access token from the YMCS /v2/token endpoint
	 * (client credentials grant) and store it in the client.
	 *
	 * Per the Open API V4X documentation:
	 *   POST {base_url}/v2/token
	 *   Authorization: Basic base64_encode(client_id:client_secret)
	 *   timestamp: millisecond epoch
	 *   nonce: random, maximum length 32 bits
	 *   body: {"grant_type": "client_credentials"}
	 *
	 * @return string|null null on success, an error message on failure
	 */
	public function refresh_access_token() {
		if (empty($this->access_key_id) || empty($this->access_key_secret)) {
			return 'AccessKey Id and AccessKey Secret are required to obtain an access token.';
			}
		$headers = [
		'Content-Type: application/json',
		'Authorization: Basic ' . base64_encode($this->access_key_id . ':' . $this->access_key_secret),
		'timestamp: ' . strval(round(microtime(true) * 1000)),
		'nonce: ' . $this->generate_nonce(),
		];
		list($response, $http_code, $curl_error) = $this->perform_request(
		$this->base_url . '/v2/token', 'POST', $headers, '{"grant_type":"client_credentials"}'
		);
		if ($response === false) {
			return 'cURL error: ' . $curl_error;
			}
		$decoded = json_decode($response, true);
		if ($http_code === 200 && is_array($decoded) && !empty($decoded['access_token'])) {
			$this->access_token = (string)$decoded['access_token'];
			$this->token_expires_at = time() + (int)($decoded['expires_in'] ?? 86400);
			return null;
			}
		//build a readable error from the OAuth2 error envelope
		if (is_array($decoded)) {
			$parts = [];
			if (!empty($decoded['error'])) { $parts[] = (string)$decoded['error']; }
			if (!empty($decoded['code'])) { $parts[] = 'code ' . $decoded['code']; }
			if (!empty($decoded['message'])) { $parts[] = (string)$decoded['message']; }
			if ($parts !== []) {
				return 'HTTP ' . $http_code . ' ' . implode(', ', $parts);
				}
			}
		return 'HTTP ' . $http_code . ' ' . substr(trim((string)$response), 0, 200);
		}

	/**
	 * Make sure a usable access token is available (developer mode).
	 *
	 * Uses the current token while it is valid. A manually pinned token
	 * (unknown expiry) is used until the gateway rejects it. Otherwise a
	 * fresh token is fetched from the /v2/token endpoint.
	 *
	 * @param bool $force Force a token refresh even if one is cached.
	 * @return string|null null on success, an error message on failure
	 */
	private function ensure_access_token($force = false) {
		if (!$force && $this->access_token !== '' && ($this->token_expires_at === 0 || $this->token_expires_at > time() + 60)) {
			return null;
			}
		return $this->refresh_access_token();
		}
	/**
	 * Translate a classic RPS open API call into the equivalent YMCS V4X call.
	 *
	 * The regional YMCS gateways expose the RPS platform under /v2/rps/*
	 * with a different method/URI/body layout than the classic
	 * /api/open/v1/* endpoints of dm.yealink.com (see the "Open API for
	 * Yealink Management Cloud Service V4X" documentation, section 5).
	 *
	 * @param string     $method  HTTP method
	 * @param string     $api_uri Legacy API URI (already ltrimmed)
	 * @param array|null $body    (in/out) body parameter array
	 * @param array|null $query   (in/out) query parameter array
	 * @return array|null [method, uri] when a translation applies,
	 *                     ['__unsupported__'] when the V4X gateway has
	 *                     no equivalent, null when the URI is left as-is.
	 */
	private function map_developer_uri($method, $api_uri, ?array &$body, ?array &$query) {
		if (strpos($api_uri, 'api/open/v1/') !== 0) {
			return null; //already a V4X URI (or unknown) - leave as-is
		}
		$is_mac = function ($v) {
			return is_string($v) && preg_match('/^[0-9a-f]{12}$/i', str_replace([':', '-'], '', $v)) === 1;
		};
		switch ($api_uri) {
			case 'api/open/v1/server/add':
				return ['POST', 'v2/rps/servers'];
			case 'api/open/v1/server/list':
				if (is_array($body)) {
					if (isset($body['key'])) {
						$body['searchKey'] = $body['key'];
						unset($body['key']);
					}
					//an empty filter object is rejected by the gateway (code 900444)
					if (empty($body['filter'])) {
						unset($body['filter']);
					}
				}
				return ['POST', 'v2/rps/listServers'];
			case 'api/open/v1/server/detail':
				$id = $query['id'] ?? '';
				$query = null;
				return ['GET', 'v2/rps/servers/' . $id];
			case 'api/open/v1/server/checkServerName':
				$body = ['skip' => 0, 'limit' => 1, 'autoCount' => true, 'filter' => ['name' => $query['serverName'] ?? '']];
				$query = null;
				return ['POST', 'v2/rps/listServers'];
			case 'api/open/v1/server/edit':
				if (is_array($body)) {
					$id = $body['id'] ?? '';
					unset($body['id']);
					return ['PATCH', 'v2/rps/servers/' . $id];
				}
				return ['__unsupported__'];
			case 'api/open/v1/server/delete':
				if (is_array($body)) {
					$body['serverIds'] = $body['ids'] ?? [];
					unset($body['ids']);
				}
				return ['POST', 'v2/rps/delServers'];
			case 'api/open/v1/device/add':
				if (is_array($body)) {
					//the form has no SN field, so use the "add by MAC" batch endpoint
					$items = [];
					foreach ((array)($body['macs'] ?? []) as $mac) {
						$items[] = [
							'mac' => $mac,
							'serverId' => $body['serverId'] ?? '',
							'uniqueServerUrl' => $body['uniqueServerUrl'] ?? '',
							'authName' => $body['authName'] ?? '',
							'password' => $body['password'] ?? '',
							'remark' => $body['remark'] ?? '',
						];
					}
					$body = $items;
				}
				return ['POST', 'v2/rps/addDevicesByMac'];
			case 'api/open/v1/device/list':
				if (is_array($body)) {
					$filter = [];
					if (!empty($body['key']) && $is_mac($body['key'])) {
						$filter['mac'] = $body['key'];
					}
					unset($body['key'], $body['status']); //the V4X list has no status filter
					if ($filter !== []) {
						$body['filter'] = $filter;
					}
				}
				return ['POST', 'v2/rps/listDevices'];
			case 'api/open/v1/device/detail':
				$id = $query['id'] ?? '';
				$query = null;
				return ['GET', 'v2/rps/devices/' . $id];
			case 'api/open/v1/device/edit':
				if (is_array($body)) {
					$id = $body['id'] ?? '';
					unset($body['id']);
					return ['PATCH', 'v2/rps/devices/' . $id];
				}
				return ['__unsupported__'];
			case 'api/open/v1/device/delete':
				if (is_array($body)) {
					$ids = $body['ids'] ?? [];
					$type = 'id';
					foreach ($ids as $v) {
						if ($is_mac($v)) {
							$type = 'mac';
							break;
						}
					}
					$body = ['deviceIdType' => $type, 'deviceIds' => $ids];
				}
				return ['POST', 'v2/rps/delDevices'];
			default:
				//no V4X equivalent (whitelist, batchEnableInit, bound url, ...)
				return ['__unsupported__'];
		}
	}

	/**
	 * Perform a signed request to the Yealink RPS API.
	 *
	 * @param string      $method  HTTP method (GET|POST)
	 * @param string      $api_uri API URI without the leading slash, e.g. api/open/v1/device/list
	 * @param array|null  $body    Body parameter array (JSON encoded) - use for POST interfaces
	 * @param array|null  $query   Query parameter array - use for GET interfaces
	 * @return array An associative array with keys: success, http_code, ret, payload, error, raw
	 */
	public function request($method, $api_uri, $body = null, $query = null) {

		//normalise the API URI
		$api_uri = ltrim($api_uri, '/');
		$method = strtoupper($method);

		//developer mode: translate the classic RPS API to the V4X gateway API
		if ($this->get_auth_mode() === 'developer') {
			$map = $this->map_developer_uri($method, $api_uri, $body, $query);
			if ($map !== null) {
				if ($map === ['__unsupported__']) {
					return [
						'success' => false,
						'http_code' => 0,
						'ret' => null,
						'payload' => null,
						'error' => 'This operation is not available on the YMCS developer gateway.',
						'raw' => '',
					];
				}
				$method = $map[0];
				$api_uri = $map[1];
			}
		}

		//generate the signature inputs (both schemes use the millisecond epoch)
		$timestamp = strval(round(microtime(true) * 1000));
		$nonce = ($this->get_auth_mode() === 'developer') ? $this->generate_nonce() : $this->generate_uuid();

		//developer mode: make sure an OAuth2 access token is available
		if ($this->get_auth_mode() === 'developer') {
			$token_error = $this->ensure_access_token();
			if ($token_error !== null) {
				return [
					'success' => false,
					'http_code' => 0,
					'ret' => null,
					'payload' => null,
					'error' => 'Access token: ' . $token_error,
					'raw' => '',
				];
			}
		}

		//build the body and content-md5 (body interfaces)
		$content_md5 = '';
		$body_str = '';
		if (in_array($method, ['POST', 'PATCH', 'PUT'], true) && $body !== null) {
			$body_str = json_encode($body);
			if (!is_string($body_str) || $body_str === 'null') {
				$body_str = '{}';
			}
			//the body must not be null / content-length cannot be 0
			if ($body_str === '') {
				$body_str = '{}';
			}
			$content_md5 = base64_encode(md5($body_str, true));
		}

		//build the query string for the url / signature
		if ($this->get_auth_mode() === 'developer') {
			//standard URL encoding (must match the query string sent in the url)
			$qf_str = ($query !== null) ? http_build_query($query) : '';
		}
		else {
			//build the FormattedQFStr (x-ca query interfaces)
			$qf_str = '';
			if ($query !== null) {
				$qf_str = $this->format_qf_str($query);
			}
		}

		//build the full URL
		$url = $this->base_url . '/' . $api_uri;
		if ($this->get_auth_mode() === 'developer') {
			//the developer gateway expects a standard URL encoded query string
			if ($query !== null) {
				$sep = (strpos($url, '?') === false) ? '?' : '&';
				$url .= $sep . http_build_query($query);
			}
		}
		elseif ($qf_str !== '') {
			$url .= '?' . $qf_str;
		}

		//compute the auth headers for the resolved auth mode
		if ($this->get_auth_mode() === 'developer') {
			//the V4X gateway expects exactly these three auth headers
			$request_headers = [
				'Authorization: Bearer ' . $this->access_token,
				'timestamp: ' . $timestamp,
				'nonce: ' . $nonce,
			];
			if ($body_str !== '') {
				$request_headers[] = 'Content-Type: application/json;charset=UTF-8';
			}
		}
		else {
			//compute the signature (x-ca scheme)
			$string_to_sign = $this->build_string_to_sign($method, $api_uri, $content_md5, $this->access_key_id, $nonce, $timestamp, $qf_str);
			$signature = base64_encode(hash_hmac('sha256', $string_to_sign, $this->access_key_secret, true));

			//build the request headers that are actually sent
			$request_headers = array(
				'X-Ca-Key: ' . $this->access_key_id,
				'X-Ca-Timestamp: ' . $timestamp,
				'X-Ca-Nonce: ' . $nonce,
				'X-Ca-Signature: ' . $signature,
			);
			if ($content_md5 !== '') {
				$request_headers[] = 'Content-Type: application/json;charset=UTF-8';
				$request_headers[] = 'Content-MD5: ' . $content_md5;
			}
		}

		//make the request
		list($response, $http_code, $curl_error) = $this->perform_request($url, $method, $request_headers, $body_str);

		//developer mode: when the gateway rejects a stale token, refresh it once and retry
		if ($this->get_auth_mode() === 'developer' && $response !== false && $http_code === 401) {
			$decoded_check = json_decode($response, true);
			$check_msg = is_array($decoded_check) ? (($decoded_check['message'] ?? '') . ' ' . ($decoded_check['code'] ?? '')) : '';
			if (stripos($check_msg, 'Invalid access token') !== false || strpos($check_msg, '500401') !== false) {
				$this->access_token = '';
				$this->token_expires_at = 0;
				if ($this->ensure_access_token(true) === null) {
					$timestamp = strval(round(microtime(true) * 1000));
					$nonce = $this->generate_nonce();
					$request_headers = [
						'Authorization: Bearer ' . $this->access_token,
						'timestamp: ' . $timestamp,
						'nonce: ' . $nonce,
					];
					if ($body_str !== '') {
						$request_headers[] = 'Content-Type: application/json;charset=UTF-8';
					}
					list($response, $http_code, $curl_error) = $this->perform_request($url, $method, $request_headers, $body_str);
				}
			}
		}

		//transport level failure
		if ($response === false) {
			return [
				'success' => false,
				'http_code' => 0,
				'ret' => null,
				'payload' => null,
				'error' => 'cURL error: ' . $curl_error,
				'raw' => '',
			];
		}

		//decode the JSON response
		$decoded = json_decode($response, true);

		//determine success. Yealink returns "ret" (0/1) and an "error" field.
		//some endpoints use ret=0 for success so we primarily rely on the HTTP
		//status code and the presence of an error object.
		//the developer gateway (YMCS) uses a spring-style envelope with a
		//"code" field (0 or 200 = success) and a "message" field.
		$success = ($http_code >= 200 && $http_code < 300);
		if (is_array($decoded)) {
			if (isset($decoded['error']) && !empty($decoded['error'])) {
				$success = false;
			}
			if (isset($decoded['errors']) && !empty($decoded['errors'])) {
				$success = false;
			}
			if (isset($decoded['code']) && !in_array($decoded['code'], [0, 200], true)) {
				$success = false;
			}
			//V4X batch endpoints report partial failures with a 200 status
			if (isset($decoded['failureCount']) && (int)$decoded['failureCount'] > 0) {
				$success = false;
			}
		}

		//extract the payload. The classic RPS API wraps the content in a
		//"data" field; the V4X gateway either returns a flat list envelope
		//({"skip":..,"total":..,"data":[..]}) or a bare object, so keep the
		//whole envelope in those cases (the pages read total + data).
		$payload = null;
		if (is_array($decoded)) {
			if (array_key_exists('data', $decoded)) {
				if (isset($decoded['skip']) || isset($decoded['limit']) || isset($decoded['total'])) {
					$payload = $decoded;
				}
				else {
					$payload = $decoded['data'];
				}
			}
			elseif ($success) {
				$payload = $decoded;
			}
		}

		//assemble the result
		$result = [
			'success' => $success,
			'http_code' => $http_code,
			'ret' => is_array($decoded) && isset($decoded['ret']) ? $decoded['ret'] : null,
			'payload' => $payload,
			'error' => null,
			'raw' => $response,
		];

		//extract a readable error message when present
		if (is_array($decoded)) {
			if (isset($decoded['error']) && !empty($decoded['error'])) {
				$result['error'] = is_array($decoded['error']) ? json_encode($decoded['error']) : (string)$decoded['error'];
			}
			if (isset($decoded['errors']) && !empty($decoded['errors'])) {
				$errors = $decoded['errors'];
				if (is_array($errors)) {
					if (isset($errors['msg'])) {
						$result['error'] = (string)$errors['msg'];
					}
					elseif (isset($errors['errorCode'])) {
						$result['error'] = 'Error code ' . $errors['errorCode'];
					}
					else {
						$result['error'] = json_encode($errors);
					}
				}
				else {
					$result['error'] = (string)$errors;
				}
			}
			//fall back to the raw body if the request failed with no message
			if (empty($result['error']) && !$success) {
				$result['error'] = 'HTTP ' . $http_code;
			}
		}

		//extract a readable message from the developer gateway envelope
		//({"code": <int>, "message": "..."}) - messages may be i18n keys
		//such as "@i18n:gateway_error_40117001:Invalid access token"
		if (is_array($decoded)) {
			$has_generic_fallback = (empty($result['error']) || preg_match('/^HTTP \d+$/', (string)$result['error']) === 1);
			if (isset($decoded['message']) && !empty($decoded['message']) && !$success && $has_generic_fallback) {
				$message = (string)$decoded['message'];
				if (strpos($message, '@i18n:') === 0) {
					//keep only the human readable part of the i18n key
					$parts = explode(':', $message);
					$message = end($parts);
				}
				$prefix = (isset($decoded['code']) && $decoded['code'] !== 0) ? 'code ' . $decoded['code'] . ': ' : '';
				$result['error'] = $prefix . $message;
			}
			//gateway parameter errors come back in a "details" array
			//([{"field": "...", "message": "..."}]) with a null message
			if ($has_generic_fallback && !empty($decoded['details']) && is_array($decoded['details'])) {
				$parts = [];
				foreach ($decoded['details'] as $detail) {
					if (is_array($detail) && isset($detail['message'])) {
						$parts[] = ((isset($detail['field']) && $detail['field'] !== '') ? $detail['field'] . ': ' : '') . $detail['message'];
					}
					else {
						$parts[] = (string)$detail;
					}
				}
				if ($parts !== []) {
					$prefix = (isset($decoded['code']) && $decoded['code'] !== 0 && $decoded['code'] !== 200) ? 'code ' . $decoded['code'] . ': ' : '';
					$result['error'] = $prefix . implode('; ', $parts);
				}
			}
		}

		return $result;
	}

	/**
	 * Convenience wrapper for POST body interfaces.
	 *
	 * @param string $api_uri
	 * @param array  $body
	 * @return array
	 */
	public function post($api_uri, array $body = []) {
		return $this->request('POST', $api_uri, $body, null);
	}

	/**
	 * Convenience wrapper for GET query interfaces.
	 *
	 * @param string $api_uri
	 * @param array  $query
	 * @return array
	 */
	public function get($api_uri, array $query = []) {
		return $this->request('GET', $api_uri, null, $query);
	}

	/**
	 * Test the API credentials / connectivity.
	 *
	 * Performs a lightweight server list call (limit = 1) and returns a
	 * result array with success, error and a human readable message.
	 * When the client is not fully configured the message explains which
	 * credentials are missing for the resolved auth mode.
	 *
	 * @return array ['success' => bool, 'error' => ?string, 'message' => string, 'auth_mode' => string]
	 */
	public function test_connection() {
		$auth_mode = $this->get_auth_mode();

		//check that the required credentials are present
		if (empty($this->access_key_id) || empty($this->access_key_secret)) {
			return [
				'success' => false,
				'error' => null,
				'message' => 'AccessKey Id and AccessKey Secret are required.',
				'auth_mode' => $auth_mode,
			];
		}
		//developer mode: verify the OAuth2 token exchange first
		if ($auth_mode === 'developer') {
			$token_error = $this->refresh_access_token();
			if ($token_error !== null) {
				return [
					'success' => false,
					'error' => $token_error,
					'message' => 'The access token could not be obtained: ' . $token_error,
					'auth_mode' => $auth_mode,
				];
			}
		}

		$result = $this->server_list(['limit' => 1]);
		$result['auth_mode'] = $auth_mode;
		if ($result['success']) {
			$result['message'] = 'Connection successful. Authentication mode: ' . $auth_mode . '.';
			if ($auth_mode === 'developer' && $this->token_expires_at > 0) {
				$result['message'] .= ' Access token valid until ' . gmdate('Y-m-d H:i:s', $this->token_expires_at) . ' UTC.';
			}
		}
		else {
			$result['message'] = 'Connection failed: ' . ($result['error'] ?: ('HTTP ' . $result['http_code']));
		}
		unset($result['payload'], $result['raw']);
		return $result;
	}

	/*
	 * -------------------------------------------------------------------
	 *  Server interfaces
	 * -------------------------------------------------------------------
	 */

	/**
	 * 2.1 Adding a server.
	 *
	 * @param array $data serverName, url, authName, password, certificateUrl, serverCertificateUrl, serverCertificateEnable
	 * @return array
	 */
	public function server_add(array $data = []) {
		return $this->post('api/open/v1/server/add', $data);
	}

	/**
	 * 2.2 Viewing the server by paging.
	 *
	 * @param array $filters key, skip, limit, autoCount
	 * @return array
	 */
	public function server_list(array $filters = []) {
		return $this->post('api/open/v1/server/list', $filters);
	}

	/**
	 * 2.3 Viewing the detailed information of the server.
	 *
	 * @param string $id
	 * @return array
	 */
	public function server_detail($id) {
		return $this->get('api/open/v1/server/detail', ['id' => $id]);
	}

	/**
	 * 2.4 Detecting whether or not the server name exists.
	 *
	 * @param string $server_name
	 * @return array
	 */
	public function server_check_server_name($server_name) {
		return $this->get('api/open/v1/server/checkServerName', ['serverName' => $server_name]);
	}

	/**
	 * 2.5 Editing a server.
	 *
	 * @param array $data id, serverName, url, authName, password, certificateUrl, serverCertificateUrl, serverCertificateEnable
	 * @return array
	 */
	public function server_edit(array $data = []) {
		return $this->post('api/open/v1/server/edit', $data);
	}

	/**
	 * 2.6 Deleting a server.
	 *
	 * @param array $ids
	 * @return array
	 */
	public function server_delete(array $ids = []) {
		return $this->post('api/open/v1/server/delete', ['ids' => $ids]);
	}

	/*
	 * -------------------------------------------------------------------
	 *  Device interfaces
	 * -------------------------------------------------------------------
	 */

	/**
	 * 3.1 Adding a batch of devices.
	 *
	 * @param array $data macs[], serverId, uniqueServerUrl, remark, authName, password, email, number
	 * @return array
	 */
	public function device_add(array $data = []) {
		return $this->post('api/open/v1/device/add', $data);
	}

	/**
	 * 3.2 Viewing the device information by paging.
	 *
	 * @param array $filters key, status (bound|unbound), skip, limit, autoCount
	 * @return array
	 */
	public function device_list(array $filters = []) {
		return $this->post('api/open/v1/device/list', $filters);
	}

	/**
	 * 3.3 Viewing the detailed information of the device.
	 *
	 * @param string $id
	 * @return array
	 */
	public function device_detail($id) {
		return $this->get('api/open/v1/device/detail', ['id' => $id]);
	}

	/**
	 * 3.4 Detecting whether or not the device is registered.
	 *
	 * @param string $mac
	 * @return array
	 */
	public function device_check($mac) {
		//the V4X gateway has no checkDevice endpoint - derive the status
		//from the device list instead
		if ($this->get_auth_mode() === 'developer') {
			$result = $this->post('v2/rps/listDevices', ['skip' => 0, 'limit' => 1, 'autoCount' => true, 'filter' => ['mac' => $mac]]);
			if ($result['success'] && is_array($result['payload'])) {
				$data = $result['payload']['data'] ?? [];
				$result['payload'] = (is_array($data) && count($data) > 0) ? 'Registered' : 'Unregistered';
			}
			return $result;
		}
		return $this->get('api/open/v1/device/checkDevice', ['mac' => $mac]);
	}

	/**
	 * 3.5 Detecting whether or not the device exists.
	 *
	 * @param string $mac
	 * @return array
	 */
	public function device_check_mac($mac) {
		if ($this->get_auth_mode() === 'developer') {
			$result = $this->post('v2/rps/listDevices', ['skip' => 0, 'limit' => 1, 'autoCount' => true, 'filter' => ['mac' => $mac]]);
			if ($result['success'] && is_array($result['payload'])) {
				$data = $result['payload']['data'] ?? [];
				$result['payload'] = (is_array($data) && count($data) > 0) ? 'Registered' : 'Unregistered';
			}
			return $result;
		}
		return $this->get('api/open/v1/device/checkMac', ['mac' => $mac]);
	}

	/**
	 * 3.6 Viewing the server list.
	 *
	 * @return array
	 */
	public function device_server_list() {
		if ($this->get_auth_mode() === 'developer') {
			$result = $this->post('v2/rps/listServers', ['skip' => 0, 'limit' => 500, 'autoCount' => true]);
			if ($result['success'] && is_array($result['payload'])) {
				$result['payload'] = $result['payload']['data'] ?? [];
			}
			return $result;
		}
		return $this->get('api/open/v1/device/serverList');
	}

	/**
	 * 3.7 Editing the device.
	 *
	 * @param array $data id, serverId, uniqueServerUrl, remark, authName, password
	 * @return array
	 */
	public function device_edit(array $data = []) {
		return $this->post('api/open/v1/device/edit', $data);
	}

	/**
	 * 3.8 Migrating a batch of devices.
	 *
	 * @param array  $ids
	 * @param string $server_id
	 * @return array
	 */
	public function device_migrate(array $ids = [], $server_id = '') {
		//the V4X gateway has no batch migrate endpoint - reassign each
		//device individually
		if ($this->get_auth_mode() === 'developer') {
			$ok = 0;
			$errors = [];
			foreach ($ids as $id) {
				$result = $this->request('PATCH', 'v2/rps/devices/' . $id, ['serverId' => $server_id], null);
				if ($result['success']) {
					$ok++;
				}
				else {
					$errors[] = $id . ': ' . ($result['error'] ?: ('HTTP ' . $result['http_code']));
				}
			}
			if ($errors === []) {
				return ['success' => true, 'http_code' => 200, 'ret' => null, 'payload' => null, 'error' => null, 'raw' => ''];
			}
			return [
				'success' => false,
				'http_code' => 200,
				'ret' => null,
				'payload' => null,
				'error' => $ok . ' migrated, ' . implode('; ', $errors),
				'raw' => '',
			];
		}
		return $this->post('api/open/v1/device/migrate', ['ids' => $ids, 'serverId' => $server_id]);
	}

	/**
	 * 3.9 Deleting a batch of devices.
	 *
	 * @param array $ids
	 * @return array
	 */
	public function device_delete(array $ids = []) {
		return $this->post('api/open/v1/device/delete', ['ids' => $ids]);
	}

	/**
	 * 3.10 Checking the device status and returning the url of the bound server.
	 *
	 * @param string $mac
	 * @return array
	 */
	public function device_check_bound_url($mac) {
		return $this->get('api/open/v1/device/checkDeviceBoundUrl', ['mac' => $mac]);
	}

	/**
	 * 3.11 Resetting a batch of connections (initialise the bound devices).
	 *
	 * @param array $ids
	 * @return array
	 */
	public function device_batch_enable_init(array $ids = []) {
		return $this->post('api/open/v1/device/batchEnableInit', ['ids' => $ids]);
	}

	/*
	 * -------------------------------------------------------------------
	 *  Enterprise IP whitelist
	 * -------------------------------------------------------------------
	 */

	/**
	 * 4.1 Adding a batch of IPs to the whitelist.
	 *
	 * @param array $ips
	 * @return array
	 */
	public function whitelist_add(array $ips = []) {
		return $this->post('api/open/v1/ipWhiteList/batchAdd', ['ips' => $ips]);
	}

	/**
	 * 4.2 Deleting a batch of IPs.
	 *
	 * @param array $ids
	 * @return array
	 */
	public function whitelist_delete(array $ids = []) {
		return $this->post('api/open/v1/ipWhiteList/batchDelete', ['ids' => $ids]);
	}

	/**
	 * 4.3 Viewing the IP by paging.
	 *
	 * @param array $filters key, skip, limit, autoCount
	 * @return array
	 */
	public function whitelist_list(array $filters = []) {
		return $this->post('api/open/v1/ipWhiteList/getPagedList', $filters);
	}

	/*
	 * -------------------------------------------------------------------
	 *  Intercepted records
	 * -------------------------------------------------------------------
	 */

	/**
	 * 5.1 Viewing the intercepted (forbidden) records.
	 *
	 * @param array $filters searchKey, skip, limit, autoCount, startTime, endTime
	 * @return array
	 */
	public function redirect_forbidden_list(array $filters = []) {
		return $this->post('api/open/v1/redirect/getForbiddenList', $filters);
	}
}

