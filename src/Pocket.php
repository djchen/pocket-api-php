<?php
/**
 * php-Pocket
 *
 * A PHP library for interfacing with Pocket (getpocket.com)
 *
 * @package	pocket-api-php
 * @author	Dan Chen
 * @license	MIT License
 */

class Pocket {

	/**
	* The maximum number of seconds to wait for the Pocket API to respond
	*
	* @var int
	*/
	const CURL_TIMEOUT = 15;

	/**
	* The number of seconds to wait while trying to connect to the Pocket API
	*
	* @var int
	*/
	const CURL_CONNECTTIMEOUT = 5;

	/**
	* The User Agent string for the HTTP request
	*
	* @var string
	*/
	const CURL_USERAGENT = 'php-pocket 0.2';

	private $_config = array(
		'apiUrl' => 'https://getpocket.com/v3',
		'consumerKey' => null,
		'accessToken' => null,
		'debug' => false
	);

	private static $_statusCodes = array(
		400 => 'Invalid request, please make sure you follow the documentation for proper syntax',
		401 => 'Problem authenticating the user',
		403 => 'User was authenticated, but access denied due to lack of permission or rate limiting',
		503 => 'Pocket\'s sync server is down for scheduled maintenance'
	);

	/**
	* Constructor
	*
	* @param array $settings	Array of settings with consumerKey being required
	* - consumerKey		: required
	* - accessToken		: optional
	* - apiUrl		: optional
	* - debug		: optional
	*
	* @return void
	*/
	public function __construct($settings) {
		foreach ($settings as $setting => $value) {
			if (!array_key_exists($setting, $this->_config)) {
				throw new PocketException('Error unknown configuration setting: ' . $setting);
			}
			$this->_config[$setting] = $value;
		}
		if ($this->_config['consumerKey'] == null) {
			throw new PocketException('Error: Application Consumer Key not provided');
		}
	}

	public function setAccessToken($accessToken) {
		$this->_config['accessToken'] = $accessToken;
	}

	public function requestToken($redirectUri, $state = false) {
		$params = array();
		$params['redirect_uri'] = $redirectUri;
		if ($state != false) {
			$params['state'] = $state;
		}
		$result = $this->_request('/oauth/request', $params);
		$query = array(
			'request_token' => $result['code'],
			'redirect_uri' => $redirectUri
		);

		$query['redirect_uri'] = 'https://getpocket.com/auth/authorize?' . http_build_query($query);
		return $query;
	}

	public function convertToken($token) {
		$params = array();
		$params['code'] = $token;
		$result = $this->_request('/oauth/authorize', $params);
		return $result;
	}

	/**
	* Retrieve a user’s list of items with optional filters
	*
	* @param array	$params		List of parameters (optional)
	* @param bool	$accessToken	The user's access token (optional)
	*
	* @return array	Response from Pocket
	* @throws PocketException
	*/
	public function retrieve($params = array(), $accessToken = true) {
		return $this->_request('/get', $params, $accessToken);
	}

	/**
	* Sets the persistent storage handler
	*
	* @param array	$params		List of parameters
	* @param bool	$accessToken	The user's access token (optional)
	*
	* @return array	Response from Pocket
	* @throws PocketException
	*/
	public function add($params = array(), $accessToken = true) {
		return $this->_request('/add', $params, $accessToken);
	}

	/**
	* Private method that makes the HTTP call to Pocket using cURL
	*
	* @return array Response from Pocket
	* @throws PocketException
	*/
	private function _request($method, $params = null, $accessToken = false) {
		$url = $this->_config['apiUrl'] . $method;

		if (!$params) {
			$params = array();
		}
		$params['consumer_key'] = $this->_config['consumerKey'];
		if ($accessToken === true) {
			$params['access_token'] = $this->_config['accessToken'];
		} else if ($accessToken !== false) {
			$params['access_token'] = $accessToken;
		}
		$params = json_encode($params);


		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_POST, true);
		curl_setopt($c, CURLOPT_POSTFIELDS, $params);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($c, CURLOPT_HEADER, $this->_config['debug']);
		curl_setopt($c, CURLINFO_HEADER_OUT, true);
		curl_setopt($c, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'X-Accept: application/json'));
		curl_setopt($c, CURLOPT_USERAGENT, self::CURL_USERAGENT);
		curl_setopt($c, CURLOPT_CONNECTTIMEOUT, self::CURL_CONNECTTIMEOUT);
		curl_setopt($c, CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
		if ($this->_config['debug'] === true) {
			curl_setopt($c, CURLINFO_HEADER_OUT, true);
		}

		$response = curl_exec($c);

		$status = curl_getinfo($c, CURLINFO_HTTP_CODE);
		if ($status != 200) {
			if (isset(self::$_statusCodes[$status])) {
				throw new PocketException('Error: ' . self::$_statusCodes[$status], $status);
			}
		}

		if ($this->_config['debug'] === true) {
			$headerSize = curl_getinfo($c, CURLINFO_HEADER_SIZE);
			$header = substr($response, 0, $headerSize);
			$response = substr($response, $headerSize);

			echo "cURL Header:\n";
			print_r(curl_getinfo($c, CURLINFO_HEADER_OUT));
			echo "\n\nPOST Body:\n";
			print_r($params);
			echo "\n\nResponse Header:\n";
			print_r($header);
			echo "\n\nResponse Body:\n";
			print_r($response);
		}
		curl_close($c);

		$result = json_decode($response, true);
		if (!$result) {
			throw new PocketException('Error could not parse response: ' . var_export($response));
		}

		return $result;
	}

}

class PocketException extends Exception {
	// TODO
}
