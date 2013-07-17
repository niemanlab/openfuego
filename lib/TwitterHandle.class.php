<?php namespace OpenFuego\lib;

class TwitterHandle extends \TwitterOAuth {

	private $consumerKey;
	private $consumerSecret;
	private $accessToken;
	private $accessTokenSecret;

	public function __construct() {

		$this->consumerKey = \OpenFuego\TWITTER_CONSUMER_KEY;
		$this->consumerSecret = \OpenFuego\TWITTER_CONSUMER_SECRET;
		$this->accessToken = \OpenFuego\TWITTER_OAUTH_TOKEN;
		$this->accessTokenSecret = \OpenFuego\TWITTER_OAUTH_SECRET;
		
		try {
			parent::__construct(
				$this->consumerKey,
				$this->consumerSecret,
				$this->accessToken,
				$this->accessTokenSecret
			);
		}

		catch (\PDOException $e) {
			Logger::error($e);
		}
	}
	
	// Overloading TwitterOAuth's get(), post(), delete() methods to decode JSON as array
	public function get($url, $parameters = array()) {
		$response = $this->oAuthRequest($url, 'GET', $parameters);
		if ($this->format === 'json' && $this->decode_json) {
			return json_decode($response, TRUE);
		}
		return $response;
	}
	
	function post($url, $parameters = array()) {
		$response = $this->oAuthRequest($url, 'POST', $parameters);
		if ($this->format === 'json' && $this->decode_json) {
			return json_decode($response, TRUE);
		}
		return $response;
	}
	
	function delete($url, $parameters = array()) {
		$response = $this->oAuthRequest($url, 'DELETE', $parameters);
		if ($this->format === 'json' && $this->decode_json) {
			return json_decode($response, TRUE);
		}
		return $response;
	}
}
