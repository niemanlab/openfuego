<?php namespace OpenFuego\lib;

class Metadata {

	private $_apiRoot;
	private $_endpoint;
	private $_apiKey;
	
	private $_dbh;

	public function __construct() {
		$this->_apiRoot = 'http://api.embed.ly/1';
		$this->_endpoint = defined('\OpenFuego\EMBEDLY_API_ENDPOINT') ? \OpenFuego\EMBEDLY_API_ENDPOINT : 'oembed';
		if (defined('\OpenFuego\EMBEDLY_API_KEY')) {
			$this->_apiKey = \OpenFuego\EMBEDLY_API_KEY;
		}
	}
	
	
	static public function instantiate() {  // This ain't too pretty.
		return new self();
	}
	
	
	protected function curl($url) {
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_HEADER => FALSE,
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_BINARYTRANSFER => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_MAXREDIRS => 1,
			CURLOPT_AUTOREFERER => TRUE,
			CURLOPT_SSL_VERIFYPEER => FALSE,
			CURLOPT_HTTPHEADER => array('Expect:')
		);		
		$ch = curl_init();	
		curl_setopt_array($ch, $options);
		$response = curl_exec($ch);
	   	curl_close($ch);
		return $response;
	}
	
	
	public function get($input_urls, $params = NULL, $format = 'json') {
		
		if (is_array($input_urls)) {
			
			$urls = '';
			foreach ($input_urls as $input_url) {
				$url = urldecode($input_url);
				$url = urlencode($url);
				$urls = $urls . $url . ',';
			}	
			$urls = substr_replace($urls, '', -1);
	
		} else {
			
			$urls = $input_urls;
		}
			
		if ($params) {
			$params = implode('&', $params);
		}
			
		$query = $this->_apiRoot . '/' . $this->_endpoint . '?key=' . $this->_apiKey . '&urls=' . $urls . '&format=' . $format . '&' . $params;
	
		$metadata = $this->curl($query);
	
		if ($metadata) {
			return $metadata;
		}
		
		else {
			return FALSE;
		}
	}
	
	
	public function getTweet($link_id) {
	
		$dbh = $this->getDbh();
			
		try {
			$sql = "
				SELECT id_str, screen_name, text, profile_image_url
				FROM openfuego_tweets_cache
				WHERE link_id = :link_id
				LIMIT 1;
			";
			$sth = $dbh->prepare($sql);
			$sth->bindParam('link_id', $link_id);
			$sth->execute();
	
		} catch (\PDOException $e) {
			Logger::error($e);
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
				$sql = "
					SELECT first_tweet
					FROM openfuego_links
					WHERE link_id = :link_id
					LIMIT 1;
				";
				$sth = $dbh->prepare($sql);
				$sth->bindParam('link_id', $link_id);
				$sth->execute();
	
				$id_str = $sth->fetchColumn(0);
	
			} catch (\PDOException $e) {
				Logger::error($e);
				return FALSE;
			}
			
			if (empty($id_str) || $id_str == NULL) {
				$status = $this->updateTweet($link_id);
				$id_str = $status['id_str'];
				$screen_name = $status['screen_name'];
				$text = $status['text'];
				$profile_image_url = $status['profile_image_url'];
	
			} else {
	
				$twitter = new TwitterHandle();
				$status = $twitter->get("statuses/show/$id_str", array('include_entities' => false));
	
				if (preg_match("/2../", $twitter->http_code)) {
					$id_str = $status['id_str'];
					$screen_name = $status['user']['screen_name'];
					$text = $status['text'];
					$profile_image_url = $status['user']['profile_image_url'];
	
					try {
						$sql = "
							INSERT IGNORE INTO openfuego_tweets_cache (link_id, id_str, screen_name, text, profile_image_url)
							VALUES (:link_id, :id_str, :screen_name, :text, :profile_image_url);
						";
						$sth = $dbh->prepare($sql);
						$sth->bindParam('link_id', $link_id);
						$sth->bindParam('id_str', $id_str);
						$sth->bindParam('screen_name', $screen_name);
						$sth->bindParam('text', $text);
						$sth->bindParam('profile_image_url', $profile_image_url);
						$sth->execute();
			
					} catch (\PDOException $e) {
						Logger::error($e);
						return FALSE;
					}
				}
	
				elseif (preg_match("/4../", $twitter->http_code)) {
					$status = $this->updateTweet($link_id);
					$id_str = $status['id_str'];
					$screen_name = $status['screen_name'];
					$text = $status['text'];
					$profile_image_url = $status['profile_image_url'];
				}
							
				else {
					Logger::error("Twitter error {$twitter->http_code}");
					return FALSE;
				}
			}
		}
	
		$tweet = array('id_str' => $id_str, 'screen_name' => $screen_name, 'text' => $text, 'profile_image_url' => $profile_image_url);
			
		return $tweet;
	}
	
	
	public function updateTweet($link_id) {
		
		$dbh = $this->getDbh();
	
		$sql = "
			SELECT sl.input_url, l.url, l.first_user_id
			FROM openfuego_links AS l
			LEFT JOIN (openfuego_short_links AS sl) ON (sl.long_url = l.url)
			WHERE l.link_id = $link_id;
		";
		$sth = $dbh->query($sql);
		$row = $sth->fetch(\PDO::FETCH_ASSOC);
	
		$short_url = $row['input_url'];	
		$long_url = $row['url'];	
		$first_user_id = $row['first_user_id'];
		
		$query = $short_url ? $short_url . ' OR ' . $long_url : $long_url;
	
		$twitter = new TwitterHandle();
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
			Logger::error("No Twitter search results. Query: {$twitter->url}");
			return FALSE; // not sure what else to do, really.
		}
		
		$id_str = $search_result['user']['id_str'];
		$screen_name = $search_result['user']['screen_name'];
		$profile_image_url = $search_result['user']['profile_image_url'];
		$text = $search_result['text'];
	
		try {
			$sql = "
				INSERT INTO openfuego_tweets_cache
					(link_id, id_str, screen_name, text, profile_image_url)
				VALUES
					(:link_id, :id_str, :screen_name, :text, :profile_image_url)
				ON DUPLICATE KEY UPDATE
					id_str=VALUES(id_str),
					screen_name=VALUES(screen_name),
					text=VALUES(text),
					profile_image_url=VALUES(profile_image_url);
			";
			$sth = $dbh->prepare($sql);
			$sth->bindParam('link_id', $link_id);
			$sth->bindParam('id_str', $id_str);
			$sth->bindParam('screen_name', $screen_name);
			$sth->bindParam('text', $text);
			$sth->bindParam('profile_image_url', $profile_image_url);
			$sth->execute();
	
		} catch (\PDOException $e) {
			Logger::error($e);
			return FALSE;
		}
	
		$tweet = array('id_str' => $id_str, 'screen_name' => $screen_name, 'text' => $text, 'profile_image_url' => $profile_image_url);
		
	 return $tweet;
	}
	
	
	public function getDbh() {
		if (!$this->_dbh) {
			$this->_dbh = new DbHandle();
		}
		
		return $this->_dbh;
	}
}