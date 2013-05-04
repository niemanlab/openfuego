<?php

function openfuego_echo($string) {
	if (OPENFUEGO_DEBUG) {
    	echo $string;
		return TRUE;
    } else {
    	return FALSE;
	}
}


function openfuego_get_dbh($db_host = OPENFUEGO_DB_HOST, $db_port = OPENFUEGO_DB_PORT, $db_name = OPENFUEGO_DB_NAME, $db_user = OPENFUEGO_DB_USER, $db_pass = OPENFUEGO_DB_PASS, $driver = OPENFUEGO_DB_DRIVER) {

	$dsn = "$driver:host=$db_host;port=$db_port;dbname=$db_name";

	$try = 1;
	
	while ($try <= 3) {	
		try {
			$dbh = new PDO($dsn, $db_user, $db_pass, array(
				PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8' COLLATE 'utf8_unicode_ci';",
				PDO::ATTR_PERSISTENT => true
			));
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			return $dbh;
		}
		
		catch (PDOException $e) {
			openfuego_notify('OpenFuego exception in ' . __FUNCTION__, $e);
			sleep($try*30);
			$try++;
			continue;
		}
	}
	return FALSE;
}


function openfuego_twitter_connect() {

	$twitter_handle = new TwitterOAuth(OPENFUEGO_TW_CONSUMER_KEY, OPENFUEGO_TW_CONSUMER_SECRET, OPENFUEGO_TW_ACCESS_TOKEN, OPENFUEGO_TW_ACCESS_TOKEN_SECRET);

 return $twitter_handle;
}


function openfuego_strpos_arr($haystack, $needles, $before = NULL, $after = NULL) { 
    foreach($needles as $needle) { 
        if(($pos = strpos($haystack, $before . $needle . $after)) !== FALSE) return $pos; 
    }
 return FALSE;
} 


function openfuego_array_most_common($input) {
	$counted = array_count_values($input);
	arsort($counted);
	return($counted);
}


function openfuego_notify($subject, $body = NULL) {
	
	if (OPENFUEGO_DEBUG) echo $subject . "\n" . $body;
	mail(OPENFUEGO_WEBMASTER, $subject, print_r($body, TRUE), 'From: ' . OPENFUEGO_POSTMASTER);
	return TRUE;
}


function openfuego_curl($url, $method = 'GET', $headers = FALSE, $limit = FALSE) {

	$data = null;

	$ch = curl_init($url);

	$options = array(
		CURLOPT_USERAGENT => OPENFUEGO_USER_AGENT,
		CURLOPT_REFERER => OPENFUEGO_REFERER,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_TIMEOUT => 15,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_BINARYTRANSFER => TRUE,
		CURLOPT_FOLLOWLOCATION => TRUE,
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_AUTOREFERER => TRUE,
		CURLOPT_SSL_VERIFYPEER => FALSE,
		CURLOPT_ENCODING => '', // blank supports all encodings
		CURLOPT_HTTPHEADER => array('Expect:'),
		CURLOPT_HEADER => $headers,
		CURLOPT_NOBODY => $headers,
	);
		
	curl_setopt_array($ch, $options);

	if ($limit) {
		$writefn = function($ch, $chunk) {
			static $limit = 10000;
	
			static $data = '';
			global $data; // There is probably a better way to do this.
		
			$len = strlen($data) + strlen($chunk);
			if ($len >= $limit ) {
				$data .= substr($chunk, 0, $limit-strlen($data));
				return -1;
			}
		
			$data .= $chunk;
			return strlen($chunk);
		};

		curl_setopt($ch, CURLOPT_WRITEFUNCTION, $writefn);
		
		curl_exec($ch);
		
		global $data; // There is probably a better way to do this.

		if (mb_detect_encoding($data, NULL, TRUE) == 'ASCII') {
			$data = utf8_encode($data);
		}

		return $data;
	}

	$data = curl_exec($ch);
	
	if (mb_detect_encoding($data, NULL, TRUE) == 'ASCII') {
		$data = utf8_encode($data);
	}

	return $data;
}


function openfuego_get_http_location($url, $max_redirects = 10) {

	$useragent = OPENFUEGO_USER_AGENT;
	$method = 'GET';
	$headers = TRUE;

	$ci = curl_init();

	curl_setopt($ci, CURLOPT_USERAGENT, $useragent);
	curl_setopt($ci, CURLOPT_REFERER, 'http://www.google.com/');
	curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ci, CURLOPT_TIMEOUT, 10);
	curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ci, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ci, CURLOPT_MAXREDIRS, $max_redirects);
	curl_setopt($ci, CURLOPT_AUTOREFERER, FALSE);
	curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ci, CURLOPT_HTTPHEADER, array('Expect:'));
	curl_setopt($ci, CURLOPT_HEADER, $headers);
	curl_setopt($ci, CURLOPT_NOBODY, $headers);
	curl_setopt($ci, CURLOPT_URL, $url);

	$response = curl_exec($ci);
		
	$info = curl_getinfo($ci);
	curl_close ($ci);

	$long_url = $info['url'];
	
	if (mb_detect_encoding($long_url, NULL, TRUE) == 'ASCII') {
		$long_url = utf8_encode($long_url);
	}

	return $long_url;
}


function openfuego_get_canonical($url) {
		
	if (strpos($url, '?utm_')) {
		$url = strstr($url, '?utm_', TRUE); 
	}
	
	if (strpos($url, '&utm_')) {
		$url = strstr($url, '&utm_', TRUE); 
	}
	
	if (strpos($url, '#utm_')) {
		$url = strstr($url, '#utm_', TRUE);
	}
	
	if (strpos($url, '#utm_')) {
		$url = strstr($url, '#utm_', TRUE);
	}
	
	$url = str_replace('www10.', 'www.', $url); // NYT paywall handling	
					
	$source = openfuego_curl($url, 'GET', FALSE, TRUE);

	$doc = new DOMDocument();
	@$doc->loadHTML($source);
		unset($source);
	$xpath = new DOMXpath($doc);
		unset($doc);
	$elms = $xpath->query("//link[@rel='canonical']");
		unset($xpath);
	if ($elms->length > 0 && strlen($elms->item(0)->getAttribute('href')) > 0 && substr(trim($elms->item(0)->getAttribute('href')), 0, 1) != '/') {
// 	if canonical is specified AND if canonical href is not blank AND if canonical is not relative (no leading slash), we are good to go
		$canonical_url = trim($elms->item(0)->getAttribute('href'));
		return $canonical_url;

	} else {
		return $url;
	}
}

?>