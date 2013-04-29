<?php
function openfuego_bitly_expand($short_url) {

	$short_url = urldecode($short_url);
	$short_url_encoded = urlencode($short_url);
	
	$bitly_expanded = openfuego_curl(OPENFUEGO_BITLY_API_ROOT . 'expand?shortUrl=' . $short_url_encoded . '&login=' . OPENFUEGO_BITLY_USERNAME . '&apiKey=' . OPENFUEGO_BITLY_API_KEY . '&format=json', 'GET', FALSE );

	if ($bitly_expanded)
		$bitly_expanded = json_decode($bitly_expanded, TRUE);
	else
		return $short_url;


	if (!empty($bitly_expanded) && array_key_exists('long_url', $bitly_expanded['data']['expand'][0]))
		return $bitly_expanded['data']['expand'][0]['long_url'];
	else
		return $short_url;
}


function openfuego_isgd_expand($short_url) {

	$short_url = urldecode($short_url);
	$short_url_encoded = urlencode($short_url);
	
	$isgd_expanded = openfuego_curl(OPENFUEGO_ISGD_API_ROOT  . '?shorturl=' . $short_url_encoded . '&format=json', 'GET', FALSE);
	$isgd_expanded = json_decode($isgd_expanded, TRUE);
	
	if (!$isgd_expanded || array_key_exists('errorcode', $isgd_expanded)) // is.gd only returns errorcode if there is error
		return FALSE;
	
	$long_url = $isgd_expanded['url'];
	return $long_url;
}


function openfuego_googl_expand($short_url) {
	
	$short_url = urldecode($short_url);
	$short_url_encoded = urlencode($short_url);

	$googl_expanded = openfuego_curl(OPENFUEGO_GOOGL_API_ROOT . '?shortUrl=' . $short_url_encoded . '&key=' . OPENFUEGO_GOOGL_API_KEY, 'GET', FALSE );
	$googl_expanded = json_decode($googl_expanded, TRUE);
		
	if ($googl_expanded['status'] != 'OK') { // if there's an error
		return FALSE;
	}

	$long_url = $googl_expanded['longUrl'];
	return $long_url;
}


function openfuego_supr_expand($short_url) {
	
	$short_url = urldecode($short_url);
	$short_url_encoded = urlencode($short_url);

	$supr_expanded = openfuego_curl(OPENFUEGO_SUPR_API_ROOT . '&shortUrl=' . $short_url_encoded, 'GET', FALSE );
	$supr_expanded = json_decode($supr_expanded, TRUE);
	
	if ($supr_expanded['errorCode'] || !$supr_expanded)
		return FALSE;
						
	$supr_expanded = array_values($supr_expanded);
	$supr_expanded = array_values($supr_expanded[2]);

	$long_url = $supr_expanded[0]['longUrl'];
	return $long_url;
}
?>