<?php namespace OpenFuego\lib;

class UrlExpander {

	const BITLY_API_ROOT = 'https://api-ssl.bitly.com/v3/';
	const GOOGL_API_ROOT = 'https://www.googleapis.com/urlshortener/v1/url';
	const ISGD_API_ROOT = 'http://is.gd/forward.php';
	const SUPR_API_ROOT = 'http://su.pr/api/expand?version=1.0';

	protected $_curl;
	protected $_dbh;
	protected $_bitly_pro_domains;
	protected $_short_domains;
	protected $_bitly_username;
	protected $_bitly_api_key;
	protected $_googl_api_key;
	

	 public function __construct() {
		$this->_bitly_pro_domains = unserialize(\OpenFuego\BITLY_PRO_DOMAINS);
		$this->_short_domains = unserialize(\OpenFuego\SHORT_DOMAINS);

		if (defined('\OpenFuego\BITLY_USERNAME')) {
			 $this->_bitly_username = \OpenFuego\BITLY_USERNAME;
		}
		
		if (defined('\OpenFuego\BITLY_API_KEY')) {
			$this->_bitly_api_key = \OpenFuego\BITLY_API_KEY;
		}

		if (defined('\OpenFuego\GOOGL_API_KEY')) {
			$this->_googl_api_key = \OpenFuego\GOOGL_API_KEY;
		}
	}


	protected function strpos_arr($haystack, $needles, $before = NULL, $after = NULL) {
		foreach($needles as $needle) {
			if(($pos = strpos($haystack, $before . $needle . $after)) !== FALSE) {
				return $pos;
			}
		}
		return FALSE;
	}


	public function expand($inputUrl) {

		$inputUrl = urldecode($inputUrl);

		if ($this->strpos_arr($inputUrl, $this->_short_domains, '://', '/')) {
			return $inputUrl;
		}

		elseif (strpos($inputUrl, '://' . 'youtu.be' . '/')) {

			$canonicalUrl = 'http://www.youtube.com/watch?v=' . str_replace('http://youtu.be/', '', $inputUrl);
			return $canonicalUrl;
		}

		$dbh = $this->getDbh();
		$sql = "
			SELECT long_url
			FROM openfuego_short_links
			WHERE input_url = :input_url
			LIMIT 1;
		";
		$sth = $dbh->prepare($sql);
		$sth->bindParam('input_url', $inputUrl);
		$sth->execute();
		$cachedUrl = $sth->fetchColumn(0);

		if ($cachedUrl) {  // if it exists in cache...
			return $cachedUrl;
		}

		if (strlen($inputUrl) > 36):  // if the URL is unshortened

			$longUrl = $inputUrl;

		elseif (strpos($inputUrl, '://' . 'is.gd' . '/')):

			$longUrl = $this->isgd($inputUrl);

		elseif (strpos($inputUrl, '://' . 'goo.gl' . '/') && $this->_googl_api_key):

			$longUrl = $this->googl($inputUrl);

		elseif (strpos($inputUrl, '://' . 'su.pr' . '/')):

			$longUrl = $this->supr($inputUrl);

		elseif ($this->strpos_arr($inputUrl, $this->_bitly_pro_domains, '://', '/') && $this->_bitly_api_key && $this->_bitly_username):

			$longUrl = $this->bitly($inputUrl);

		else:
			$curl = $this->getCurl();
			$longUrl = $curl->getLocation($inputUrl);
			$curl = NULL;

		endif;

		// done looping through expansion options. now, do we have a canonical URL?
		if ($longUrl) {
			$canonicalUrl = $this->getCanonical($longUrl);
			
			$outputUrl = $canonicalUrl ? $canonicalUrl : $longUrl;

			try {
				$sql = "
					INSERT INTO openfuego_short_links (input_url, long_url)
					VALUES (:input_url, :output_url);
				";
				$sth = $dbh->prepare($sql);
				$sth->bindParam(':input_url', $inputUrl);
				$sth->bindParam(':output_url', $outputUrl);
				$sth->execute();

				return $outputUrl;

			} catch (\PDOException $e) {
				Logger::error($e);
				return FALSE;
			}

		} else {
			return FALSE;
		}
	}


	public function bitly($shortUrl) {
		$shortUrl = urldecode($shortUrl);
		$shortUrlEncoded = urlencode($shortUrl);
		$query = self::BITLY_API_ROOT . 'expand?shortUrl=' . $shortUrlEncoded . '&login=' . $this->_bitly_username . '&apiKey=' . $this->_bitly_api_key . '&format=json';
		$curl = $this->getCurl();

		$bitlyExpanded = $curl->get($query);

		$curl = NULL;
		
		if ($bitlyExpanded) {
			$bitlyExpanded = json_decode($bitlyExpanded, TRUE);
		}

		else {
			return $shortUrl;
		}

		if (!empty($bitlyExpanded) && array_key_exists('long_url', $bitlyExpanded['data']['expand'][0])) {
			$longUrl = $bitlyExpanded['data']['expand'][0]['long_url'];
			return $longUrl;
		}

		else {
			return $shortUrl;
		}
	}


	public function isgd($shortUrl) {
		$shortUrl = urldecode($shortUrl);
		$shortUrlEncoded = urlencode($shortUrl);
		$query = self::ISGD_API_ROOT . '?shorturl=' . $shortUrlEncoded . '&format=json';
		$curl = $this->getCurl();

		$isgdExpanded = $curl->get($query);
		$isgdExpanded = json_decode($isgdExpanded, TRUE);

		$curl = NULL;

		// is.gd only returns errorcode if there is an error
		if (!$isgdExpanded || !is_array($isgdExpanded) || array_key_exists('errorcode', $isgdExpanded)) {
			Logger::error("is.gd error while expanding {$shortUrl}: {$isgdExpanded['errorcode']}");
			return $shortUrl;
		}

		$longUrl = $isgdExpanded['url'];
		return $longUrl;
	}


	public function googl($shortUrl) {

		$shortUrl = urldecode($shortUrl);
		$shortUrlEncded = urlencode($shortUrl);
		$query = self::GOOGL_API_ROOT . '?shortUrl=' . $shortUrlEncded . '&key=' . $this->_googl_api_key;
		$curl = $this->getCurl();

		$googlExpanded = $curl->get($query);
		$googlExpanded = json_decode($googlExpanded, TRUE);
		
		$curl = NULL;

		if ($googlExpanded['status'] != 'OK') { // if there's an error
			Logger::error("goo.gl error while expanding {$shortUrl}: {$googlExpanded['status']}");
			return $shortUrl;
		}

		$longUrl = $googlExpanded['longUrl'];
		return $longUrl;
	}


	public function supr($shortUrl) {

		$shortUrl = urldecode($shortUrl);
		$shortUrlEncoded = urlencode($shortUrl);
		$query = self::SUPR_API_ROOT . '&shortUrl=' . $shortUrlEncoded;
		$curl = $this->getCurl();

		$suprExpanded = $curl->get($query);
		$suprExpanded = json_decode($suprExpanded, TRUE);

		$curl = NULL;

		if ($suprExpanded['errorCode'] || !$suprExpanded) {
			Logger::error("su.pr error while expanding {$shortUrl}: {$suprExpanded['errorCode']}");
			return $shortUrl;
		}

		$suprExpanded = array_values($suprExpanded);
		$suprExpanded = array_values($suprExpanded[2]);

		$longUrl = $suprExpanded[0]['longUrl'];
		return $longUrl;
	}


	public function getCanonical($url) {

		$curl = $this->getCurl();

		if (preg_match('/(\?|\&|#)utm_/', $url, $matches)) {
			$url = strstr($url, $matches[1], TRUE);
		}

		$source = $curl->getChunk($url);

		$curl = NULL;

		$doc = new \DOMDocument;
		@$doc->loadHTML($source);
		unset($source);
		$xpath = new \DOMXpath($doc);
		unset($doc);
		$elms = $xpath->query("//link[@rel='canonical']");
		unset($xpath);
		// if canonical is specified AND if canonical href is not blank
		// AND if canonical is not relative (no leading slash), good to go
		if ($elms->length > 0 && strlen($elms->item(0)->getAttribute('href')) > 0 && substr(trim($elms->item(0)->getAttribute('href')), 0, 1) != '/') {
			$canonicalUrl = trim($elms->item(0)->getAttribute('href'));
			return $canonicalUrl;

		} else {
			return $url;
		}
	}


	public function getDbh() {
		if (!$this->_dbh) {
			$this->_dbh = new DbHandle();
		}
		
		return $this->_dbh;
	}


	public function getCurl() {
		if (!$this->_curl) {
			$this->_curl = new Curl();
		}
		
		return $this->_curl;
	}
	
	
	public function __destruct() {
		$this->_curl = NULL;
		$this->_dbh = NULL;
	}
}
