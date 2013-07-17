<?php namespace OpenFuego\lib;

class Curl {

	protected $_curlHandle;
	protected $_options;
	protected $_headers;

	public $url;
	public $error;

	public function __construct() {

		if (!function_exists('curl_init')) {
			throw new Exception("cURL must be installed to continue.");
		}
		
		$this->_headers = array(
			'Expect:'
		);

		/* Options common to all requests */
		$this->_options = array(
		 		CURLOPT_USERAGENT => \OpenFuego\USER_AGENT,
				CURLOPT_REFERER => \OpenFuego\REFERRER,
				CURLOPT_CONNECTTIMEOUT => 15,
				CURLOPT_TIMEOUT => 15,
				CURLOPT_RETURNTRANSFER => TRUE,
				CURLOPT_FOLLOWLOCATION => TRUE,
				CURLOPT_AUTOREFERER => FALSE,
				CURLOPT_SSL_VERIFYPEER => FALSE,
				CURLOPT_ENCODING => '', // blank supports all encodings
				CURLOPT_HTTPHEADER => $this->_headers,
				CURLOPT_COOKIESESSION => TRUE
		);
		
	//	curl_setopt_array($this->_curlHandle, $this->_options);
	}
	

	public function getChunk($url) {

		$this->_curlHandle = curl_init($url);
		$ch = $this->_curlHandle;

		$curlData = '';
		$limit = 8000;
		$writefn = function($ch, $chunk) use (&$curlData, $limit) { 
			static $data = '';
			
			$len = strlen($data) + strlen($chunk);
			if ($len >= $limit) {
				$data .= substr($chunk, 0, $limit-strlen($data));
				$curlData = $data;
				return -1;
			}
		
			$data .= $chunk;
			return strlen($chunk);
		};
					
		curl_setopt_array($this->_curlHandle, $this->_options);

		curl_setopt($this->_curlHandle, CURLOPT_RANGE, '0-8000');
		curl_setopt($this->_curlHandle, CURLOPT_WRITEFUNCTION, $writefn);
		curl_setopt($this->_curlHandle, CURLOPT_HEADER, FALSE);
		curl_setopt($this->_curlHandle, CURLOPT_NOBODY, FALSE);

		curl_exec($ch);

		$this->error = curl_errno($this->_curlHandle);

		$curlData = $this->encode($curlData);		
		
		$this->close();
		
		return $curlData;
	}


	public function get($url) {

		$this->_curlHandle = curl_init($url);

		curl_setopt_array($this->_curlHandle, $this->_options);

		curl_setopt($this->_curlHandle, CURLOPT_HEADER, FALSE);
		curl_setopt($this->_curlHandle, CURLOPT_NOBODY, FALSE);

		$curlData = curl_exec($this->_curlHandle);
		
		$this->error = curl_errno($this->_curlHandle);

		if ($this->error > 0) {  // 0 means no error
			Logger::error('cURL error getting ' . $url . ': ' . $this->error, 2);
		}

		$curlData = $this->encode($curlData);

		$this->close();

		return $curlData;
	}

	
	protected function encode($curlData) {
		if (mb_detect_encoding($curlData, NULL, TRUE) == 'ASCII') {
			$curlData = utf8_encode($curlData);
		}
		
		return $curlData;
	}
	
	
	public function getLocation($url) {

		$this->_curlHandle = curl_init($url);
		
		curl_setopt_array($this->_curlHandle, $this->_options);

		curl_setopt($this->_curlHandle, CURLOPT_HEADER, TRUE);
		curl_setopt($this->_curlHandle, CURLOPT_NOBODY, TRUE);

		curl_exec($this->_curlHandle);

		$curlInfo = $this->getInfo();
		$location = $curlInfo['url'];
		
		$location = $this->encode($location);

		$this->close();

		return $location;
	}
	
	
	protected function getInfo() {
		return curl_getinfo($this->_curlHandle);
	}


	protected function close() {
		if (is_resource($this->_curlHandle)) {
			curl_close($this->_curlHandle);
		}
	}


	public function __destruct() {
	//	$this->close();
	}
}