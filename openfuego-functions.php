<?php

include(OPENFUEGO_DIR . '/openfuego-metadata-functions.php');

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


function openfuego_strpos_arr($haystack, $needles, $before = NULL, $after = NULL) { 
    foreach($needles as $needle) { 
        if(($pos = strpos($haystack, $before . $needle . $after)) !== FALSE) return $pos; 
    }
 return FALSE;
} 


function openfuego_notify($subject, $body = NULL) {
	
	if (OPENFUEGO_DEBUG) echo $subject . "\n" . $body;
	mail(OPENFUEGO_WEBMASTER, $subject, print_r($body, TRUE), 'From: ' . OPENFUEGO_POSTMASTER);
	return TRUE;
}


function array_most_common($input) {
	$counted = array_count_values($input);
	arsort($counted);
	return($counted);
}


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


function read_body($ch, $chunk) {
  static $range = 5000;
  static $data = '';
/* 		  static $limit = 10000; */

  $len = strlen($data) + strlen($chunk);
  if ($len >= $range) {
    $data .= substr($chunk, 0, $range-strlen($data));
//    print_r($data);
//   echo strlen($data) . ' ' . $data;
//    return -1;
			return $data;
  }

  $data .= $chunk;
  
  return strlen($chunk);
}


function openfuego_curl($url, $method = 'GET', $headers = FALSE, $limit = FALSE) {

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
			static $limit = 5000;
	
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
	
	// This is an exceptionally dumb workaround. HTTP 1.1 servers allow OpenFuego's curl function to request
	// only the first X bytes of a document. But HTTP 1.0 servers ignore this request and return the whole
	// file. I can't figure out how to abort the download after X bytes on HTTP 1.0 servers, so if the
	// resource is a 30 MB PDF file, the app runs out of memory. Therefore don't try to curl files of these
	// types. Of course, there are infinite numbers of file types not to curl, so this isn't a good solution!
	if (preg_match('/^.*\.(jpg|jpeg|png|gif|pdf|mpeg|mp3|mp4|mov|tiff|ogg|zip|tar|gz|flv)$/i', $url)) {
		return $url;
	}
		
	$url = str_replace('www10.', 'www.', $url); // NYT paywall handling	
					
	$source = openfuego_curl($url, 'GET', FALSE, 10000);

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
/* 		$url_encoded = urlencode($url); */
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
		
//		$multiplier = round($multiplier, 3);

		$score = round($weighted_count * $multiplier);
		
		$openfuego_items_filtered[] = array('link_id' => $link_id, 'url' => $url, 'weighted_count' => $weighted_count, 'first_seen' => $first_seen, 'first_user' => $first_user, 'age' => $age, 'multiplier' => $multiplier, 'score' => $score);
		
/* 		$openfuego_items_urls[] = $url; // for metadata, might be a better way */

		unset($link_id, $url, $weighted_count, $first_seen, $age, $multiplier, $score);
	}
	
	unset($openfuego_items);

	foreach ($openfuego_items_filtered as $key => $row) {
		$score[$key] = $scoring ? $row['score'] : $row['weighted_count'];
		$age[$key] = $row['age'];
	}
	
	array_multisort($score, SORT_DESC, $age, SORT_ASC, $openfuego_items_filtered);	// sort by score, then by age

	unset($age, $score);
/*
	$openfuego_items = $openfuego_items_filtered;
	unset($openfuego_items_filtered);
*/
	
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
		$tw_profile_image_url_bigger = str_replace('_normal.', '_bigger.', $tw_profile_image_url);
		$tw_tweet_url = 'https://twitter.com/' . $tw_screen_name . '/status/' . $tw_id_str;

		$openfuego_items_filtered[$i]['tw_id_str'] = $tw_id_str;
 		$openfuego_items_filtered[$i]['tw_screen_name'] = $tw_screen_name;
		$openfuego_items_filtered[$i]['tw_text'] = $tw_text;
		$openfuego_items_filtered[$i]['tw_profile_image_url'] = $tw_profile_image_url;
		$openfuego_items_filtered[$i]['tw_profile_image_url_bigger'] = $tw_profile_image_url_bigger;
		$openfuego_items_filtered[$i]['tw_tweet_url'] = $tw_tweet_url;

		if ($link_meta) {
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

			if ($twitter->http_code == 200) {
				$id_str = $status['id_str'];
				$screen_name = $status['user']['screen_name'];
				$text = $status['text'];
				$profile_image_url = $status['user']['profile_image_url'];
			}

			elseif ($twitter->http_code == 403 || $twitter->http_code == 404) {
				$status = openfuego_update_tweet($link_id);
				$id_str = $status['id_str'];
				$screen_name = $status['screen_name'];
				$text = $status['text'];
				$profile_image_url = $status['profile_image_url'];
			}
			
			elseif ($twitter->http_code == 503) {
				$try = 1;
				while ($try <= 2) {
					if ($status = $twitter->get("statuses/show/$id_str", array('include_entities' => false)) && $twitter->http_code == 200) {
						break; // all set
					}
					
					else {
					//	sleep($try);
						$try++;
						continue; // try again
					}
				}
			}
			
			else {
				openfuego_notify('OpenFuego exception in ' . __FUNCTION__, $twitter->http_code . "\n\n" . $twitter->url . "\n\n" . openfuego_curl($twitter->url));
				return FALSE;
			}
		}

		// $tweet_to_cache = array($link_id, $id_str, $screen_name, $text, $profile_image_url);
	
		try {
			$sth = $dbh->prepare("INSERT IGNORE INTO openfuego_tweets_cache (link_id, id_str, screen_name, text, profile_image_url) VALUES (:link_id, :id_str, :screen_name, :text, :profile_image_url);");
/* 		$sth = $dbh->prepare("INSERT INTO openfuego_tweets_cache (link_id, id_str, screen_name, text, profile_image_url) VALUES (:link_id, :id_str, :screen_name, :text, :profile_image_url); UNLOCK TABLES"); */
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

	$tweet = array('id_str' => $id_str, 'screen_name' => $screen_name, 'text' => $text, 'profile_image_url' => $profile_image_url);
	$tweet['tweet_url'] = 'http://twitter.com/' . $screen_name . '/statuses/' . $id_str . '/';
	$tweet['profile_image_url_bigger'] = str_replace('_normal.', '_bigger.', $profile_image_url);
		
	return $tweet;
}


function openfuego_update_tweet($link_id) {
	
	$dbh = openfuego_get_dbh();

	$query = $dbh->query("SELECT url, first_user_id FROM openfuego_links WHERE link_id = $link_id;");
	
	$rows = $query->fetchAll();

	$twitter = openfuego_twitter_connect();
		
	foreach ($rows as $row) {

		$url = $row[0];	
		$first_user_id = $row[1];

		$search = $twitter->get("search/tweets", array('q' => $url, 'count' => 100));
		// print_r($search); die;
		// $search = json_decode($search, TRUE);
		
		if ($search['statuses']) {
			$search_results = $search['statuses'];

			foreach ($search_results as $search_result) {
				if ($search_result['user']['id_str'] == $first_user_id) {
					break 2;
				}
			}
		}
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
	$tweet['tweet_url'] = 'http://twitter.com/' . $screen_name . '/status/' . $id_str . '/';
	$tweet['profile_image_url_bigger'] = str_replace('_normal.', '_bigger.', $profile_image_url);
	
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


function openfuego_twitter_connect() {

	$twitter_handle = new TwitterOAuth(OPENFUEGO_TW_CONSUMER_KEY, OPENFUEGO_TW_CONSUMER_SECRET, OPENFUEGO_TW_ACCESS_TOKEN, OPENFUEGO_TW_ACCESS_TOKEN_SECRET);

 return $twitter_handle;
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

	$universe_ids_sorted = array_most_common($universe_ids);

	unset($authority_friends_ids, $owner_screen_name, $twitter, $universe_ids);
	
/* 	print '<pre>'; print_r($universe_ids_sorted); print '</pre>'; */
	
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
?>