<?php
/**
 * This contains official plugins for Tikapot
 */

namespace SkylarK\SimpleTwitter;

/**
 * This is a simple interface to Twitter
 */
class Twitter
{
	private $_config;

	/**
	* Setup Twitter
	*
	* @param string $consumer_key Twitter consumer key
	* @param string $consumer_secret Twitter consumer secret
	* @param string $access_token Twitter access token
	* @param string $access_secret Twitter access secret
	*/
	public function __construct($consumer_key, $consumer_secret, $access_token, $access_secret) {
		if (!extension_loaded('curl')) {
			throw new Exception("Twitter: Error, Curl not enabled");
		}

		$this->_config = array(
			'consumer_key' => $consumer_key,
			'consumer_secret' => $consumer_secret,
			'access_token' => $access_token,
			'access_secret'  => $access_secret
		);

		if (!$this->checkConfig()) {
			throw new Exception("Twitter: Invalid credentials supplied");
		}
	}

	/**
	* Wrapper for a simple http request!
	*/
	private function curl($url, $method, $headers, $postData = NULL) {
		$ci = curl_init();
		curl_setopt($ci, CURLOPT_USERAGENT, "SimpleTwitter");
		curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ci, CURLOPT_TIMEOUT, 30);
		curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ci, CURLOPT_HEADER, false);
		curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);

		if ($method == 'POST') {
			curl_setopt($ci, CURLOPT_POST, TRUE);
			if (!empty($postData)) {
				curl_setopt($ci, CURLOPT_POSTFIELDS, $postData);
			}
		}

		curl_setopt($ci, CURLOPT_URL, $url);
		$response = curl_exec($ci);
		curl_close ($ci);
		return $response;
	}

	/**
	* Wrapper for an oAuth request
	*/
	private function oAuthRequest($url, $parameters, $method) {
		$defaults = array (
			"oauth_version"   => '1.0',
			"oauth_nonce"    => md5(microtime() . mt_rand()),
			"oauth_timestamp"   => time(),
			"oauth_consumer_key"  => $this->_config['consumer_key'],
			"oauth_token"    => $this->_config['access_token'],
			"oauth_signature_method"  => "HMAC-SHA1"
		);

		$request_data = ($method == "GET") ? array_merge($defaults, $parameters) : $defaults;

		// Percent encode
		$keys = array_map("rawurlencode", array_keys($request_data));
		$values = array_map("rawurlencode", array_values($request_data));

		// Recombine and sort
		$request_data = array_combine($keys, $values);
		ksort($request_data);

		// Stringup
		$parameterString = array();
		foreach ($request_data as $key => $value) {
			$parameterString[] = $key . "=" . $value;
		}
		$parameterString = implode("&", $parameterString);

		// Now create the base string
		$base = strtoupper($method) . "&" . rawurlencode($url) . "&" . rawurlencode($parameterString);

		// And the signing ket
		$key = rawurlencode($this->_config['consumer_secret']) . "&" . rawurlencode($this->_config['access_secret']);

		// Signature
		$signature = base64_encode(hash_hmac('sha1', $base, $key, true));

		// Build header string
		$header_data = array_merge($defaults, array('oauth_signature' => $signature));
		$header = array();
		foreach ($header_data as $key => $value) {
			$header[] = rawurlencode($key) . "=\"" . rawurlencode($value) . "\"";
		}
		$header = "Authorization: OAuth " . implode(", ", $header);

		// Send off the request
		switch ($method) {
			case "GET":
				// Rebuild URL
				if (count($parameters) > 0) {
					$params = array();
					foreach ($parameters as $k => $v) {
						$params[] = rawurlencode($k) . "=" . rawurlencode($v);
					}
					$url .= "?" . implode("&", $params);
				}

				return $this->curl($url, "GET", array($header));
			case "POST":
				return $this->curl($url, "POST", array($header), $parameters);
		}
	}

	/**
	* Validates the configuration
	* @return Boolean True if config is okay
	*/
	private function checkConfig() {
		if (isset($this->_config['consumer_key'])  && strlen($this->_config['consumer_key']) > 0 &&
			isset($this->_config['consumer_secret']) && strlen($this->_config['consumer_secret']) > 0 &&
			isset($this->_config['access_token'])  && strlen($this->_config['access_token']) > 0  &&
			isset($this->_config['access_secret'])  && strlen($this->_config['access_secret']) > 0) {
			$json = json_decode($this->oAuthRequest("https://api.twitter.com/1.1/account/verify_credentials.json", array(), "GET"));
			return !isset($json->error);
		}
		return true;
	}

	/**
	* Grab the latest tweets
	* @param String $user Optional - Specify a user
	*/
	public function tweets($user = NULL) {
		return json_decode($this->oAuthRequest("https://api.twitter.com/1.1/statuses/user_timeline.json", $user ? array("screen_name" => $user) : array(), "GET"));
	}

	/**
	* Shortcut for tweeting from an app!
	* @param String $data The string to tweet
	*/
	public function tweet($data) {
		return json_decode($this->oAuthRequest("https://api.twitter.com/1.1/statuses/update.json", array("status" => $data), "POST"));
	}
}