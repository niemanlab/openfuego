<?php namespace OpenFuego\app;

use OpenFuego\lib\DbHandle as DbHandle;
use OpenFuego\lib\Metadata as Metadata;

class Getter {

	protected $_dbh;

	protected function getDbh() {
		if (!$this->_dbh) {
			$this->_dbh = new DbHandle();
		}
		
		return $this->_dbh;
	}

	public function getItems($quantity = 10, $hours = 24, $scoring = TRUE, $metadata = FALSE) {
	
		$now = time();
		
		$quantity = (int)$quantity;
		$hours = (int)$hours;
	
		$date = date('Y-m-d H:i:s', $now);	
	
		if ($scoring) {
			$min_weighted_count = floor($hours/2.5+8);
			$limit = 100;	
		} else {
			$min_weighted_count = 1;
			$limit = $quantity;
		}
	
		try {
			$dbh = $this->getDbh();
			$sql = "
				SELECT link_id, url, first_seen, first_user, weighted_count, count
				FROM openfuego_links
				WHERE weighted_count >= :min_weighted_count
					AND count > 1
					AND first_seen BETWEEN DATE_SUB(:date, INTERVAL :hours HOUR) AND :date
				ORDER BY weighted_count DESC
				LIMIT :limit;
			";
			$sth = $this->_dbh->prepare($sql);
			$sth->bindParam('date', $date, \PDO::PARAM_STR);
			$sth->bindParam('hours', $hours, \PDO::PARAM_INT);
			$sth->bindParam('min_weighted_count', $min_weighted_count, \PDO::PARAM_INT);
			$sth->bindParam('limit', $limit, \PDO::PARAM_INT);
			$sth->execute();
	
		} catch (\PDOException $e) {
			Logger::error($e);
			return FALSE;
		}
	
		$items = $sth->fetchAll(\PDO::FETCH_ASSOC);
	
		if (!$items) {
			return FALSE;
		}
	
		foreach ($items as $item) {
	
			$link_id = (int)$item['link_id'];
	
			$url = $item['url'];
			$weighted_count = $item['weighted_count'];
			$multiplier = NULL;
			$score = NULL;
	
			$first_seen = $item['first_seen'];
			$first_seen = strtotime($first_seen);
			$age = $now - $first_seen;
			$age = $age / 3600; // to get hours
			$age = round($age, 1);
	
			$first_user = $item['first_user'];
	
			    if ($age <  ($hours/6))							{ $multiplier = 1.20-$age/$hours; }  // freshness boost!
			elseif ($age >= ($hours/6) && $age < ($hours/2))	{ $multiplier = 1.05-$age/$hours; }
			elseif ($age  > ($hours/2))							{ $multiplier = 1.01-$age/$hours; }
	
			$score = round($weighted_count * $multiplier);
	
			$items_filtered[] = array(
				'link_id' => $link_id,
				'url' => $url,
				'weighted_count' => $weighted_count,
				'first_seen' => $first_seen,
				'first_user' => $first_user,
				'age' => $age,
				'multiplier' => $multiplier,
				'score' => $score
			);
		}
		
		$scores = array();
		$ages = array();
		foreach ($items_filtered as $key => $item) {
			$scores[$key] = $scoring ? $item['score'] : $item['weighted_count'];
			$ages[$key] = $item['age'];
		}

/*
		foreach ($fuego_links_popular_filtered_rows as $key => $fuego_links_popular_filtered_row) {
			if ($scoring) {	$a[$key] = $fuego_links_popular_filtered_row[6]; } // sort by score
			else { $a[$key] = $fuego_links_popular_filtered_row[2]; } // sort by count
			$b[$key] = $fuego_links_popular_filtered_row[4]; // then by age
		}
		
		array_multisort($a, SORT_DESC, $b, SORT_ASC, $fuego_links_popular_filtered_rows);
*/
	
		array_multisort($scores, SORT_DESC, $ages, SORT_ASC, $items_filtered);  // sort by score, then by age
		
		$items_filtered = array_slice($items_filtered, 0, $quantity);
	
		if ($metadata && defined('\OpenFuego\EMBEDLY_API_KEY') && \OpenFuego\EMBEDLY_API_KEY) {
	
			$metadata_params = is_array($metadata) ? $metadata : NULL;
			
			foreach ($items_filtered as $item_filtered) {
				$urls[] = $item_filtered['url'];
			}
			
			$link_meta = array();
			$urls_chunked = array_chunk($urls, 20);  // Embedly handles maximum 20 URLs per request
			foreach ($urls_chunked as $urls_chunk) {
				$link_meta_chunk = Metadata::instantiate()->get($urls_chunk, $metadata_params);
				$link_meta_chunk = json_decode($link_meta_chunk, TRUE);
				$link_meta = array_merge($link_meta, $link_meta_chunk);
			}
			unset($urls, $urls_chunked, $urls_chunk, $link_meta_chunk);
		}
		
		$row_count = count($items_filtered);

		foreach ($items_filtered as $key => &$item_filtered) {
			$link_id = $item_filtered['link_id'];
			$url = $item_filtered['url'];
	
			preg_match('@^(?:https?://)?([^/]+)@i', $url, $matches);	
			$domain = $matches[1];
	
			if (strlen($domain) > 24) {
				preg_match('/[^.]+\.[^.]+$/', $domain, $matches);
				$domain = $matches[0];
			}
			
			$item_filtered['domain'] = $domain;
	
			$item_filtered['rank'] = $key + 1;
			
			$metadata = new Metadata();
			$status = $metadata->getTweet($link_id);
	
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
	
			$item_filtered['tw_id_str'] = $tw_id_str;
	 		$item_filtered['tw_screen_name'] = $tw_screen_name;
			$item_filtered['tw_text'] = $tw_text;
			$item_filtered['tw_profile_image_url'] = $tw_profile_image_url;
			$item_filtered['tw_profile_image_url_bigger'] = $tw_profile_image_url_bigger;
			$item_filtered['tw_tweet_url'] = $tw_tweet_url;
	
			if (isset($link_meta)) {
				$item_filtered['metadata'] = $link_meta[$key];
			}
			
		}

	 return $items_filtered;
	}
}