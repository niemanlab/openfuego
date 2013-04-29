<?php // This is the magic salsa.

function openfuego_expand($input_url) {
	
	global $openfuego_bitly_pro_domains;
	global $openfuego_short_domains;

	$url = urldecode($input_url);
	
	openfuego_echo("\nurl: $input_url");
	
	$url = urlencode($input_url);
		
	if (openfuego_strpos_arr($input_url, $openfuego_short_domains, '://', '/')) {
		openfuego_echo(" (pre-defined short url) "); // instagram, vimeo, mlkshk...
		return $input_url;
	}
	
	elseif (strpos($input_url, '://' . 'youtu.be' . '/')) {

		$canonical_url = 'http://www.youtube.com/watch?v=' . str_replace('http://youtu.be/', '', $input_url);
		openfuego_echo(" (youtube)");
		return $canonical_url;
	}

	$dbh = openfuego_get_dbh();

	$input_url_encoded = urlencode($input_url);

	$sth = $dbh->prepare("SELECT long_url FROM openfuego_short_links WHERE input_url = :input_url LIMIT 1");
	$sth->bindParam('input_url', $input_url);
	$sth->execute();
	$cached_url = $sth->fetchColumn(0);

	if ($cached_url) {	// if it exists in cache...
		openfuego_echo(" (cached)");
		return $cached_url;
	}

	$start = microtime(TRUE);

	if (strlen($input_url) > 36): // if the URL is unshortened

		$long_url = $input_url;
//		$canonical_url = openfuego_get_canonical($input_url);
		openfuego_echo(" (already expanded)");
	
	elseif (strpos($input_url, '://' . 'is.gd' . '/')):
	
		$long_url = openfuego_isgd_expand($input_url);
		openfuego_echo(" (isgd)");
		
	elseif (strpos($input_url, '://' . 'goo.gl' . '/') && defined('OPENFUEGO_GOOGL_API_KEY') && OPENFUEGO_GOOGL_API_KEY):
	
		$long_url = openfuego_googl_expand($input_url);
		openfuego_echo(" (googl)");

	elseif (strpos($input_url, '://' . 'su.pr' . '/')):

		$long_url = openfuego_supr_expand($input_url);
		openfuego_echo(" (supr)");
		
	// list of known Bitly Pro domains specified in openfuego-config
	elseif (openfuego_strpos_arr($input_url, $openfuego_bitly_pro_domains, '://', '/') && defined('OPENFUEGO_BITLY_API_KEY') && OPENFUEGO_BITLY_API_KEY):
	
		$long_url = openfuego_bitly_expand($input_url);
	//	$long_url = openfuego_get_http_location($long_url); // to deal with trib.al urls
		openfuego_echo(" (bitly)");
		
	else:
		$long_url = openfuego_get_http_location($input_url);
		openfuego_echo(" (headers)");
	
	endif;
	
	// done looping through expansion options. now, do we have a canonical_url?

	if ($long_url) {
		$canonical_url = openfuego_get_canonical($long_url);
		$canonical_url = rtrim($canonical_url, '/');
		$canonical_url = str_replace('www10.', 'www.', $canonical_url);

		$output_url = $canonical_url ? $canonical_url : $long_url;

	//	$dbh = openfuego_get_dbh();
		try {
			$sth = $dbh->prepare("INSERT INTO openfuego_short_links (input_url, long_url) VALUES (:input_url, :output_url)");
			$sth->bindParam(':input_url', $input_url);
			$sth->bindParam(':output_url', $output_url);
			$sth->execute();
			
			return $output_url;

		} catch (PDOException $e) {
			openfuego_notify('PDO exception in ' . __FUNCTION__ . ', ' . date('Y-m-d H:i:s'), $e);
			return FALSE;
		}

	} else {
		return FALSE;
	}
}

function openfuego_process_urls($status) {
		
	$urls = $status['entities']['urls'];

	foreach($urls as $url) {

		$expanded_url = $url['expanded_url'];

		$output_url = openfuego_expand($expanded_url); // sometimes "expanded url" returned by t.co service is a bitly link, etc.

		$first_seen = $status['created_at'];
		$first_seen = strtotime($first_seen);
		$first_seen = date('Y-m-d H:i:s', $first_seen);

		$first_tweet = $status['id_str'];

		$first_user = $status['user']['screen_name'];

		$first_user_id = $status['user']['id_str'];

		$weighted_count = openfuego_get_influence($first_user_id);
		
		try { // should reconfigure this to open only on db connection
			$dbh = openfuego_get_dbh();
			$sth = $dbh->prepare("INSERT INTO openfuego_links (url, first_seen, first_tweet, first_user, first_user_id, weighted_count, count, last_seen) VALUES (:url, :first_seen, :first_tweet, :first_user, :first_user_id, :weighted_count, 1, :first_seen) ON DUPLICATE KEY UPDATE weighted_count = weighted_count + VALUES(weighted_count), count = count + 1, last_seen = VALUES(last_seen);");
			$sth->bindParam('url', $output_url);
			$sth->bindParam('first_seen', $first_seen);
			$sth->bindParam('first_tweet', $first_tweet);
			$sth->bindParam('first_user', $first_user);
			$sth->bindParam('first_user_id', $first_user_id);
			$sth->bindParam('weighted_count', $weighted_count);
			$sth->execute();
		} catch (PDOException $e) {
			openfuego_notify('PDO exception in ' . __FUNCTION__ . ', ' . date('Y-m-d H:i:s'), $e);
			continue; // on to the next url
		}

		unset($expanded_url, $output_url, $first_seen, $first_tweet, $first_user, $first_user_id, $weighted_count);
	}		
}

function openfuego_get_influence($user_id_str) {

	try {
		$dbh = openfuego_get_dbh();
		$sql = "SELECT influence FROM openfuego_citizens WHERE user_id = :user_id LIMIT 1;";
		$sth = $dbh->prepare($sql);
		$sth->bindParam('user_id', $user_id_str);
		$sth->execute();
		
		$influence = $sth->fetchColumn(0);
		
		return $influence;

	} catch (PDOException $e) {
		openfuego_notify('PDO exception retrieving influence', $e);
		return FALSE;
	}
}


function openfuego_is_citizen($user_id_str) {

	try {
		$dbh = openfuego_get_dbh();
		$sth = $dbh->prepare("SELECT user_id FROM openfuego_citizens WHERE user_id = :user_id LIMIT 1;");
		$sth->bindParam('user_id', $user_id_str);
		$sth->execute();
		
		if ($sth->fetchColumn(0))
			return TRUE;
		else
			return FALSE;

	} catch (PDOException $e) {
		openfuego_notify('PDO exception checking for citizenry', $e);
		return FALSE;
	}
}
?>