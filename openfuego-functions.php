<?php // This is the salsa right here.

include(OPENFUEGO_DIR . '/openfuego-helper-functions.php');
include(OPENFUEGO_DIR . '/openfuego-metadata-functions.php');

function openfuego_get_universe($min_influence = 1) {
	
	try {
		$dbh = openfuego_get_dbh();
		$sth = $dbh->prepare("SELECT user_id FROM openfuego_citizens WHERE influence >= :min_influence;");
		$sth->bindParam('min_influence', $min_influence);
		$sth->execute();
		
		$user_ids = $sth->fetchAll(PDO::FETCH_COLUMN);

		return $user_ids;

	} catch (PDOException $e) {
		openfuego_notify('OpenFuego exception in ' . __FUNCTION__, $e);
		return FALSE;
	}
}


function openfuego_clean_up() {

	$expiration_int = OPENFUEGO_EXPIRATION_INT;
	$now = time();
	$date = date('Y-m-d H:i:s', $now);
	
	$dbh = openfuego_get_dbh();
	
	$sql = "DELETE FROM openfuego_links WHERE first_seen < DATE_SUB(:date, INTERVAL :expiration_int DAY);
			DELETE FROM openfuego_short_links WHERE last_seen < DATE_SUB(:date, INTERVAL :expiration_int DAY);";
	$sth = $dbh->prepare($sql);
	$sth->bindParam('date', $date, PDO::PARAM_INT);
	$sth->bindParam('expiration_int', $expiration_int, PDO::PARAM_INT);
	$sth->execute();

  return TRUE;
 }


function openfuego_get_items($quantity = 10, $hours = 24, $scoring = TRUE, $metadata = FALSE) {

	$now = time();
	
	$quantity = (int)$quantity;
	$hours = (int)$hours;
//	$scoring = (int)$scoring;

	$date = date('Y-m-d H:i:s', $now);	

	if ($scoring) {
		$min_weighted_count = floor($hours/2.5+8);
		$limit = 100;	
	} else {
		$min_weighted_count = 1;
		$limit = $quantity;
	}
		
	try {
		$dbh = openfuego_get_dbh();
		$sql = "SELECT link_id, url, first_seen, first_user, weighted_count, count FROM openfuego_links WHERE weighted_count >= :min_weighted_count AND count > 1 AND first_seen BETWEEN DATE_SUB(:date, INTERVAL :hours HOUR) AND :date ORDER BY weighted_count DESC LIMIT :limit;";
		$sth = $dbh->prepare($sql);
		$sth->bindParam('date', $date, PDO::PARAM_STR);
		$sth->bindParam('hours', $hours, PDO::PARAM_INT);
		$sth->bindParam('min_weighted_count', $min_weighted_count, PDO::PARAM_INT);
		$sth->bindParam('limit', $limit, PDO::PARAM_INT);
			$sth->execute();
		
	} catch (PDOException $e) {
		openfuego_notify('OpenFuego exception in ' . __FUNCTION__, $e);
		return FALSE;
	}

	$openfuego_items = $sth->fetchAll(PDO::FETCH_ASSOC);
			
	if (!$openfuego_items) {
		openfuego_notify('OpenFuego exception in ' . __FUNCTION__, "The following query returned 0 results: $sql");
		return FALSE;
	}

	foreach ($openfuego_items as $openfuego_item) {
	
		$link_id = (int)$openfuego_item['link_id'];
		
		$url = $openfuego_item['url'];
// 	$url_encoded = urlencode($url);
		$weighted_count = $openfuego_item['weighted_count'];
		$multiplier = NULL;
		$score = NULL;

		$first_seen = $openfuego_item['first_seen'];
		$first_seen = strtotime($first_seen);
		$age = $now - $first_seen;
		$age = $age / 3600; // to get hours
		$age = round($age, 1);
		
		$first_user = $openfuego_item['first_user'];
		
		    if ($age <  ($hours/6))								{ $multiplier = 1.20-$age/$hours; } // freshness boost!
		elseif ($age >= ($hours/6) && $age < ($hours/2))		{ $multiplier = 1.05-$age/$hours; }
		elseif ($age  > ($hours/2))								{ $multiplier = 1.01-$age/$hours; }
		
		$score = round($weighted_count * $multiplier);
		
		$openfuego_items_filtered[] = array('link_id' => $link_id, 'url' => $url, 'weighted_count' => $weighted_count, 'first_seen' => $first_seen, 'first_user' => $first_user, 'age' => $age, 'multiplier' => $multiplier, 'score' => $score);
		
		unset($link_id, $url, $weighted_count, $first_seen, $age, $multiplier, $score);
	}
	
	unset($openfuego_items);

	foreach ($openfuego_items_filtered as $key => $row) {
		$score[$key] = $scoring ? $row['score'] : $row['weighted_count'];
		$age[$key] = $row['age'];
	}
	
	array_multisort($score, SORT_DESC, $age, SORT_ASC, $openfuego_items_filtered);	// sort by score, then by age

	unset($age, $score);
	
	$openfuego_items_filtered = array_slice($openfuego_items_filtered, 0, $quantity);

	if ($metadata && defined('OPENFUEGO_EMBEDLY_API_KEY') && OPENFUEGO_EMBEDLY_API_KEY) {
		foreach ($openfuego_items_filtered as $openfuego_item_filtered) {
			$urls[] = $openfuego_item_filtered['url'];
		}
		
		$link_meta = array();
		$urls_chunked = array_chunk($urls, 20); // Embedly handles maximum 20 URLs per request
		foreach ($urls_chunked as $urls_chunk) {
			$link_meta_chunk = openfuego_get_metadata($urls_chunk);
			$link_meta_chunk = json_decode($link_meta_chunk, TRUE);
			$link_meta = array_merge($link_meta, $link_meta_chunk);
		}
		unset($urls, $urls_chunked, $urls_chunk, $link_meta_chunk);
	}
	
	$row_count = count($openfuego_items_filtered);

	for ($i = 0; $i <= ($row_count - 1); $i++) {
	
		$link_id = $openfuego_items_filtered[$i]['link_id'];
		$url = $openfuego_items_filtered[$i]['url'];

		preg_match('@^(?:https?://)?([^/]+)@i', $url, $matches);	
		$domain = $matches[1];
		$domain = str_replace(array('www.', 'www10.'), '', $domain);

		if (strlen($domain) > 24) {
			preg_match('/[^.]+\.[^.]+$/', $domain, $matches);
			$domain = $matches[0];
		}
		
		$openfuego_items_filtered[$i]['domain'] = $domain;

		$openfuego_items_filtered[$i]['rank'] = $i+1;
			
		$status = openfuego_get_tweet($link_id);

		$tw_id_str = $status['id_str'];
		$tw_screen_name = $status['screen_name'];
		$tw_text = $status['text'];
		$tw_profile_image_url = $status['profile_image_url'];
		$tw_profile_image_url_bigger = null;
		$tw_tweet_url = null;
		
		if ($tw_profile_image_url && $tw_screen_name && $tw_id_str) {
			$tw_profile_image_url_bigger = str_replace('_normal.', '_bigger.', $tw_profile_image_url);
			$tw_tweet_url = 'https://twitter.com/' . $tw_screen_name . '/status/' . $tw_id_str;
		}

		$openfuego_items_filtered[$i]['tw_id_str'] = $tw_id_str;
 		$openfuego_items_filtered[$i]['tw_screen_name'] = $tw_screen_name;
		$openfuego_items_filtered[$i]['tw_text'] = $tw_text;
		$openfuego_items_filtered[$i]['tw_profile_image_url'] = $tw_profile_image_url;
		$openfuego_items_filtered[$i]['tw_profile_image_url_bigger'] = $tw_profile_image_url_bigger;
		$openfuego_items_filtered[$i]['tw_tweet_url'] = $tw_tweet_url;

		if (isset($link_meta)) {
			$openfuego_items_filtered[$i]['metadata'] = $link_meta[$i];
		}
		
		unset($openfuego_items_filtered[$i]['html']);
	}

 return $openfuego_items_filtered;
}


function openfuego_get_tweet($link_id) {

	$dbh = openfuego_get_dbh();

	try {
		$sth = $dbh->prepare("SELECT id_str, screen_name, text, profile_image_url FROM openfuego_tweets_cache WHERE link_id = :link_id LIMIT 1;");
		$sth->bindParam('link_id', $link_id);
		$sth->execute();

	} catch (PDOException $e) {
		openfuego_notify('OpenFuego exception in ' . __FUNCTION__, $e);
		return FALSE;
	}
	
	$tweets_cache_row = $sth->fetch();

	if ($tweets_cache_row) {  // if tweet CACHED

		$id_str = $tweets_cache_row[0];
		$screen_name = $tweets_cache_row[1];
		$text = $tweets_cache_row[2];
		$profile_image_url = $tweets_cache_row[3];

	} else { // if associated tweet is NOT cached
	
		try {
			$query = $dbh->prepare("SELECT first_tweet FROM openfuego_links WHERE link_id = :link_id LIMIT 1");
			$query->bindParam('link_id', $link_id);
			$query->execute();

			$id_str = $query->fetchColumn(0);

		} catch (PDOException $e) {
			openfuego_notify('OpenFuego exception in ' . __FUNCTION__, $e);
			return FALSE;
		}
		
		if (empty($id_str) || $id_str == NULL) {
			$status = openfuego_update_tweet($link_id);
			$id_str = $status['id_str'];
			$screen_name = $status['screen_name'];
			$text = $status['text'];
			$profile_image_url = $status['profile_image_url'];

		} else {

			$twitter = openfuego_twitter_connect();
			$status = $twitter->get("statuses/show/$id_str", array('include_entities' => false));

			if (preg_match("/2../", $twitter->http_code)) {
				$id_str = $status['id_str'];
				$screen_name = $status['user']['screen_name'];
				$text = $status['text'];
				$profile_image_url = $status['user']['profile_image_url'];

				try {
					$sth = $dbh->prepare("INSERT IGNORE INTO openfuego_tweets_cache (link_id, id_str, screen_name, text, profile_image_url) VALUES (:link_id, :id_str, :screen_name, :text, :profile_image_url);");
					$sth->bindParam('link_id', $link_id);
					$sth->bindParam('id_str', $id_str);
					$sth->bindParam('screen_name', $screen_name);
					$sth->bindParam('text', $text);
					$sth->bindParam('profile_image_url', $profile_image_url);
					$sth->execute();
		
				} catch (PDOException $e) {
					openfuego_notify('OpenFuego exception in ' . __FUNCTION__, $e);
					return FALSE;
				}
			}

			elseif (preg_match("/4../", $twitter->http_code)) {
				$status = openfuego_update_tweet($link_id);
				$id_str = $status['id_str'];
				$screen_name = $status['screen_name'];
				$text = $status['text'];
				$profile_image_url = $status['profile_image_url'];
			}
						
			else {
				openfuego_notify('OpenFuego exception in ' . __FUNCTION__, $twitter->http_code . "\n\n" . $twitter->url . "\n\n" . openfuego_curl($twitter->url));
				return FALSE;
			}
		}
	}

	$tweet = array('id_str' => $id_str, 'screen_name' => $screen_name, 'text' => $text, 'profile_image_url' => $profile_image_url);
		
	return $tweet;
}


function openfuego_update_tweet($link_id) {
	
	$dbh = openfuego_get_dbh();

	$sth = $dbh->query("SELECT sl.input_url, l.url, l.first_user_id FROM openfuego_links AS l LEFT JOIN (openfuego_short_links AS sl) ON (sl.long_url = l.url) WHERE l.link_id = $link_id;");
	
	$row = $sth->fetch(PDO::FETCH_ASSOC);

	$short_url = $row['input_url'];	
	$long_url = $row['url'];	
	$first_user_id = $row['first_user_id'];
	
	$query = $short_url ? $short_url . ' OR ' . $long_url : $long_url;

	$twitter = openfuego_twitter_connect();

	$search = $twitter->get("search/tweets", array('q' => $query, 'count' => 100, 'result_type' => 'mixed'));
	
	if ($search['statuses']) {
		$search_results = $search['statuses'];

		foreach ($search_results as $search_result) {
			if ($search_result['user']['id_str'] == $first_user_id) {
				break;
			}
		}
	}
	else {
		openfuego_notify('No Twitter search results on openfuego_update_tweet()', 'Query: ' . $twitter->http_code);
		return false; // no results. not sure what else to do, really.
	}
	
	$id_str = $search_result['user']['id_str'];
	$screen_name = $search_result['user']['screen_name'];
	$profile_image_url = $search_result['user']['profile_image_url'];
	$text = $search_result['text'];

	try {
		$sth = $dbh->prepare("INSERT INTO openfuego_tweets_cache (link_id, id_str, screen_name, text, profile_image_url) VALUES (:link_id, :id_str, :screen_name, :text, :profile_image_url) ON DUPLICATE KEY UPDATE id_str=VALUES(id_str), screen_name=VALUES(screen_name), text=VALUES(text), profile_image_url=VALUES(profile_image_url);");
		$sth->bindParam('link_id', $link_id);
		$sth->bindParam('id_str', $id_str);
		$sth->bindParam('screen_name', $screen_name);
		$sth->bindParam('text', $text);
		$sth->bindParam('profile_image_url', $profile_image_url);
		$sth->execute();

	} catch (PDOException $e) {
		openfuego_notify('PDO exception in ' . __FILE__ . ', ' . __LINE__, $e . "\n\n link_id: $link_id \n\n id_str: $id_str \n\n screen_name: $screen_name \n\n text: $text \n\n profile_image_url: $profile_image_url");

		return FALSE;
	}

	$tweet = array('id_str' => $id_str, 'screen_name' => $screen_name, 'text' => $text, 'profile_image_url' => $profile_image_url);
	
 return $tweet;
}


function openfuego_last_updated($format = NULL) {

	$dbh = openfuego_get_dbh();

	try {
		$sth = $dbh->prepare("SELECT UPDATE_TIME FROM information_schema.tables WHERE TABLE_SCHEMA = 'openfuego' AND TABLE_NAME = 'openfuego_tweets_last';");
		$sth->execute();
		$updated = $sth->fetchColumn(0);	
		$updated = strtotime($updated);
		return $updated;

	} catch (PDOException $e) {
		openfuego_notify('OpenFuego exception in ' . __FUNCTION__, $e);
		return FALSE;
	}
}


function openfuego_populate_universe($authorities, $min_influence = 1) {

	$owner_screen_name = strtolower(OPENFUEGO_TW_SCREEN_NAME);

	$twitter = openfuego_twitter_connect();
	
	$authorities = $twitter->get('users/lookup', array('screen_name' => implode(',', $authorities)));

	foreach ($authorities as $authority) {
		$authorities_ids[] = $authority['id_str'];
	}
		
	$universe_ids = $authorities_ids;
	
	foreach ($authorities as $authority) {
		$authority_friends_ids = $twitter->get('friends/ids', array('screen_name' => $authority['screen_name']));
			if ($twitter->http_code != 200) die('Dying, ' . $twitter->http_code);
		$authority_friends_ids = $authority_friends_ids['ids'];
		$universe_ids = array_merge($universe_ids, $authority_friends_ids); // append more ids to universe
	}

	$universe_ids_sorted = openfuego_array_most_common($universe_ids);

	unset($authority_friends_ids, $owner_screen_name, $twitter, $universe_ids);
	
	$dbh = openfuego_get_dbh();
	$dbh->exec("TRUNCATE TABLE openfuego_citizens;");
	$sql = "INSERT INTO openfuego_citizens (user_id, influence) VALUES (:user_id, :influence);";
	$sth = $dbh->prepare($sql);

	foreach ($universe_ids_sorted as $key=>$value) {
		try {
			$sth->bindParam('user_id', $key, PDO::PARAM_INT);
			$sth->bindParam('influence', $value, PDO::PARAM_INT);
			$sth->execute();	
		}
		
		catch (PDOException $e) {
			openfuego_notify('OpenFuego exception in ' . __FUNCTION__, $e);
			die($e);
			return FALSE;
		}
	}
	
 unset($dbh, $universe_ids_sorted);
 return TRUE;
}


function openfuego_expand($input_url) {
	
	global $openfuego_bitly_pro_domains;
	global $openfuego_short_domains;

	$url = urldecode($input_url);
	
	openfuego_echo("\n$input_url");
	
	$url = urlencode($input_url);
		
	if (openfuego_strpos_arr($input_url, $openfuego_short_domains, '://', '/')) {
		openfuego_echo(" (pre-defined short url)\n"); // instagram, vimeo, mlkshk...
		return $input_url;
	}
	
	elseif (strpos($input_url, '://' . 'youtu.be' . '/')) {

		$canonical_url = 'http://www.youtube.com/watch?v=' . str_replace('http://youtu.be/', '', $input_url);
		openfuego_echo(" (youtube)\n");
		openfuego_echo($canonical_url . "\n");
		return $canonical_url;
	}

	$dbh = openfuego_get_dbh();

	$input_url_encoded = urlencode($input_url);

	$sth = $dbh->prepare("SELECT long_url FROM openfuego_short_links WHERE input_url = :input_url LIMIT 1");
	$sth->bindParam('input_url', $input_url);
	$sth->execute();
	$cached_url = $sth->fetchColumn(0);

	if ($cached_url) {	// if it exists in cache...
		openfuego_echo(" (cached)\n");
		openfuego_echo($cached_url . "\n");
		return $cached_url;
	}

	$start = microtime(TRUE);

	if (strlen($input_url) > 36): // if the URL is unshortened

		$long_url = $input_url;
		openfuego_echo(" (already expanded)\n$long_url\n");
	
	elseif (strpos($input_url, '://' . 'is.gd' . '/')):
	
		$long_url = openfuego_isgd_expand($input_url);
		openfuego_echo(" (isgd)\n$long_url\n");
		
	elseif (strpos($input_url, '://' . 'goo.gl' . '/') && defined('OPENFUEGO_GOOGL_API_KEY') && OPENFUEGO_GOOGL_API_KEY):
	
		$long_url = openfuego_googl_expand($input_url);
		openfuego_echo(" (googl)\n$long_url\n");

	elseif (strpos($input_url, '://' . 'su.pr' . '/')):

		$long_url = openfuego_supr_expand($input_url);
		openfuego_echo(" (supr)\n$long_url\n");
		
	// list of known Bitly Pro domains specified in openfuego-settings
	elseif (openfuego_strpos_arr($input_url, $openfuego_bitly_pro_domains, '://', '/') && defined('OPENFUEGO_BITLY_API_KEY') && OPENFUEGO_BITLY_API_KEY):
	
		$long_url = openfuego_bitly_expand($input_url);
		openfuego_echo(" (bitly)\n$long_url\n");
		
	else:
		$long_url = openfuego_get_http_location($input_url);
		openfuego_echo(" (headers)\n$long_url\n");
	
	endif;
	
	// done looping through expansion options. now, do we have a canonical_url?

	if ($long_url) {
		$canonical_url = openfuego_get_canonical($long_url);
		$canonical_url = rtrim($canonical_url, '/');
		$canonical_url = str_replace('www10.', 'www.', $canonical_url);

		$output_url = $canonical_url ? $canonical_url : $long_url;

		try {
			$sth = $dbh->prepare("INSERT INTO openfuego_short_links (input_url, long_url) VALUES (:input_url, :output_url)");
			$sth->bindParam(':input_url', $input_url);
			$sth->bindParam(':output_url', $output_url);
			$sth->execute();
			
			openfuego_echo("$output_url\n");
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