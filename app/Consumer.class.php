<?php namespace OpenFuego\app;

use OpenFuego\lib\UrlExpander as UrlExpander;
use OpenFuego\lib\DbHandle as DbHandle;
use OpenFuego\app\Universe as Universe;
use OpenFuego\lib\Logger as Logger;

class Consumer {
	
	/**
	 * Member attribs
	 */
	protected $_queueDir;
	protected $_filePattern;
	protected $_checkInterval;
	protected $_dbh;
	protected $_urlExpander;
	
	/**
	 * Construct the consumer and start processing
	 */
	public function __construct($queueDir = \OpenFuego\TMP_DIR, $filePattern = 'CollectorQueue*.queue', $checkInterval = 10) {
		$this->_queueDir = $queueDir;
		$this->_filePattern = $filePattern;
		$this->_checkInterval = $checkInterval;
		$this->_pcntlEnabled = function_exists('pcntl_signal_dispatch') ? TRUE : FALSE;
		
		// Sanity checks
		if (!is_dir($queueDir)) {
			throw new ErrorException('Invalid directory: ' . $queueDir);
		}
	}
	
	/**
	 * Method that actually starts the processing task (never returns)
	 */
	public function process() {
	
		// Init some things
		$lastCheck = 0;
		
		// Infinite loop
		while (TRUE) {
			
			// Keep the DB tidy. Remove entries older than EXPIRATION_DAYS days.
			$this->cleanUp();
	
			// Get a list of queue files
			$queueFiles = glob($this->_queueDir . '/' . $this->_filePattern);
			$lastCheck = time();
			
			Logger::debug('Found ' . count($queueFiles) . ' queue files to process...');
			
			// Iterate over each file (if any)
			foreach ($queueFiles as $queueFile) {
				$this->processQueueFile($queueFile);		
			}

			// Check for SIGTERM to shut down gracefully
			if ($this->_pcntlEnabled == TRUE) {
				$this->handleSignals();
			}
	
			// Wait until ready for next check
			Logger::debug('Sleeping...');
			while (time() - $lastCheck < $this->_checkInterval) {
				sleep(1);
			}
		} // Infinite loop
	
	} // End process()
	
	/**
	 * Processes a queue file and does something with it (example only)
	 * @param string $queueFile The queue file
	 */
	protected function processQueueFile($queueFile) {
		Logger::debug('Processing file: ' . $queueFile);
		
		// Open file
		$fp = fopen($queueFile, 'r');
		
		// Check if something has gone wrong, or perhaps the file is just locked by another process
		if (!is_resource($fp)) {
			Logger::error('WARN: Unable to open file or file already open: ' . $queueFile . ' - Skipping.');
			return FALSE;
		}
		
		// Lock file
		flock($fp, LOCK_EX);
		
		// Loop over each line (1 line per status)
		$statusCounter = 0;
		while ($rawStatus = fgets($fp, 8192)) {
	
			$statusCounter++;
			
			$status = json_decode($rawStatus, TRUE);	// convert JSON data into PHP array
			
			// if data is invalid (e.g., if a user has deleted a tweet; surprisingly frequent)
			if (is_array($status) == FALSE || !isset($status['user']['id_str'])) {
	 			Logger::debug('Status is invalid, continuing.');
				continue; // skip it
			}

			if (array_key_exists(0, $status['entities']['urls']) == FALSE) { // if tweet does not contain link
				continue;	// skip it
			}
		
			/* Weed out statuses created by undesired user. (The streaming API also returns _retweets of_
			** statuses by desired user, which we don't want.) */
			if (!\OpenFuego\app\Universe::isCitizen($status['user']['id_str'])) {	// if the tweeter is not a citizen
				continue; // skip it
			}			

			$this->processUrls($status);
			
			Logger::debug('Decoded tweet: ' . $status['user']['screen_name'] . ': ' . urldecode($status['text']));
	
			set_time_limit(60);
		
			unset($status, $entities);
		} // End while
		
		// Release lock and close
		flock($fp, LOCK_UN);
		fclose($fp);
		
		// All done with this file
		Logger::debug('Successfully processed ' . $statusCounter . ' tweets from ' . $queueFile . ' - deleting.');
		unset($rawStatus);
		unlink($queueFile);		
	}
	
	
	protected function processUrls($status) {

		$dbh = $this->getDbh();
		
		if (!$this->_urlExpander) {
			$this->_urlExpander = new UrlExpander();
		}
		
		$urlExpander = $this->_urlExpander;
		
		$urls = $status['entities']['urls'];

		foreach($urls as $url) {
	
			$expanded_url = $url['expanded_url'];
	
			$output_url = $urlExpander->expand($expanded_url);  // sometimes "expanded url" returned by t.co is a bitly link, etc.
			$output_url = rtrim($output_url, '/');
		
			$first_seen = $status['created_at'];
			$first_seen = strtotime($first_seen);
			$first_seen = date('Y-m-d H:i:s', $first_seen);
	
			$first_tweet = $status['id_str'];
	
			$first_user = $status['user']['screen_name'];
	
			$first_user_id = $status['user']['id_str'];
	
			$weighted_count = Universe::getInfluence($first_user_id);
			
			try {
				$sql = "INSERT INTO openfuego_links (
					url,
					first_seen,
					first_tweet,
					first_user,
					first_user_id,
					weighted_count,
					count,
					last_seen
				)
				VALUES (
					:url,
					:first_seen,
					:first_tweet,
					:first_user,
					:first_user_id,
					:weighted_count,
					1,
					:first_seen
				)
				ON DUPLICATE KEY UPDATE
				weighted_count = CASE WHEN
					first_tweet != VALUES(first_tweet) 
					AND first_user = VALUES(first_user)
				THEN
					weighted_count
				ELSE
					weighted_count + VALUES(weighted_count)
				END,
				count = CASE WHEN
					first_tweet != VALUES(first_tweet) 
					AND first_user = VALUES(first_user)
				THEN
					count
				ELSE
					count + 1
				END,
				last_seen = CASE WHEN
					first_tweet != VALUES(first_tweet)
					AND first_user = VALUES(first_user)
				THEN
					last_seen
				ELSE
					VALUES(last_seen)
				END;";
				$sth = $dbh->prepare($sql);
				$sth->bindParam('url', $output_url);
				$sth->bindParam('first_seen', $first_seen);
				$sth->bindParam('first_tweet', $first_tweet);
				$sth->bindParam('first_user', $first_user);
				$sth->bindParam('first_user_id', $first_user_id);
				$sth->bindParam('weighted_count', $weighted_count);
				$sth->execute();
			} catch (\PDOException $e) {
				echo 'PDO exception in ' . __FUNCTION__ . ', ' . date('Y-m-d H:i:s'), $e;
				continue; // on to the next url
			}
		}
	}
	

	public function cleanUp() {	
		$expiration_days = \OpenFuego\EXPIRATION_DAYS;
		$now = time();
		$date = date('Y-m-d H:i:s', $now);
		
		$dbh = $this->getDbh();
		
		$sql = "
			DELETE FROM openfuego_links
			WHERE first_seen < DATE_SUB(:date, INTERVAL :expiration_int DAY);
			
			DELETE FROM openfuego_short_links
			WHERE last_seen < DATE_SUB(:date, INTERVAL :expiration_int DAY);
		";
		$sth = $dbh->prepare($sql);
		$sth->bindParam('date', $date, \PDO::PARAM_INT);
		$sth->bindParam('expiration_int', $expiration_days, \PDO::PARAM_INT);
		$sth->execute();
	
	  return TRUE;
	}
	
	
	protected function getDbh() {
		if (!$this->_dbh) {
			$this->_dbh = new DbHandle();
		}
		
		return $this->_dbh;
	}


	public function handleSignals() {

		pcntl_signal_dispatch();
		
		global $_should_stop;

		if (isset($_should_stop) && $_should_stop == TRUE) {
			exit();
		}
	}	
}