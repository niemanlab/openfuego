<?php

function openfuego_metadata_curl($url) {
	$useragent = OPENFUEGO_USER_AGENT;

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, FALSE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 1);
	curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
//	curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
//	curl_setopt($ch, CURLOPT_HTTPGET, !$headers);

    curl_setopt($ch, CURLOPT_URL, $url);

   	$response = curl_exec($ch);
        
   	curl_close($ch);
   	
	return $response;
}


function openfuego_get_metadata($input_urls, $params = NULL, $format = 'json') {

	$json_decode = FALSE;

	if (is_array($input_urls)) {
		
		$urls = '';
		foreach ($input_urls as $input_url) {
			$url = urldecode($input_url);
			$url = urlencode($url);
			$urls .= $url . ',';
		}	
		$urls = substr_replace($urls, '', -1);

	} else {
		
		$urls = $input_urls;
	}
		
	if ($params) {
		$params = implode('&', $params);
	}
	
	$endpoint = defined('OPENFUEGO_EMBEDLY_API_ENDPOINT') ? OPENFUEGO_EMBEDLY_API_ENDPOINT : 'oembed';

	$query = OPENFUEGO_EMBEDLY_API_ROOT . '/' . $endpoint . '?key=' . OPENFUEGO_EMBEDLY_API_KEY . '&urls=' . $urls . '&format=' . $format . '&' . $params;

	$metadata = openfuego_metadata_curl($query);

	if ($metadata) {
		if ($json_decode == TRUE) {
			return json_decode($metadata, TRUE);
		} else {
			return $metadata;		
		}
	
	} else {
		return FALSE;
	}
}
?>