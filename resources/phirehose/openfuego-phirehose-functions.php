<?php

require_once(OPENFUEGO_PHIREHOSE_DIR . '/lib/Phirehose.php');
require_once(OPENFUEGO_PHIREHOSE_DIR . '/lib/OauthPhirehose.php');

define('TWITTER_CONSUMER_KEY', OPENFUEGO_TW_CONSUMER_KEY);
define('TWITTER_CONSUMER_SECRET', OPENFUEGO_TW_CONSUMER_SECRET);
define('OAUTH_TOKEN', OPENFUEGO_TW_ACCESS_TOKEN);
define('OAUTH_SECRET', OPENFUEGO_TW_ACCESS_TOKEN_SECRET);

class OpenFuegoQueueCollector extends OauthPhirehose {
	/**
	* Subclass specific constants
	*/
	const QUEUE_FILE_PREFIX = 'OpenFuegoQueue';
	const QUEUE_FILE_ACTIVE = '.OpenFuegoQueue.current';
	
	/**
	* Member attributes specific to this subclass
	*/
	protected $queueDir;
	protected $rotateInterval;
	protected $streamFile;
	protected $statusStream;
	protected $lastRotated;

	/**
	* Overidden constructor to take class-specific parameters
	* 
	* @param string $token
	* @param string $secret
	* @param string $queueDir
	* @param integer $rotateInterval
	*/

	public function __construct($token, $secret, $queueDir = OPENFUEGO_CACHE_DIR, $rotateInterval = 10) {
	
		// Set subclass parameters
		$this->queueDir = $queueDir;
		$this->rotateInterval = $rotateInterval;
		
		// Call parent constructor
		return parent::__construct($token, $secret, Phirehose::METHOD_FILTER);
	}
	
	/**
	* Enqueue each status
	*
	* @param string $status
	*/
	public function enqueueStatus($status) {
	
		// Write the status to the stream (must be via getStream())
		fputs($this->getStream(), $status);
		
		/* Are we due for a file rotate? Note this won't be called if there are no statuses coming through.
		 */
		$now = time();
		if (($now - $this->lastRotated) > $this->rotateInterval) {
			// Mark last rotation time as now
			$this->lastRotated = $now;
		
			// Rotate it
			$this->rotateStreamFile();
		}	
	}
	
	/**
	* Returns a stream resource for the current file being written/enqueued to
	* 
	* @return resource
	*/
	private function getStream() {

		// If we have a valid stream, return it
		if (is_resource($this->statusStream)) {
			return $this->statusStream;
		}
	
		// If it's not a valid resource, we need to create one
		if (!is_dir($this->queueDir) || !is_writable($this->queueDir)) {
			throw new Exception('Unable to write to queueDir: ' . $this->queueDir);
		}
	
		// Construct stream file name, log and open
		$this->streamFile = $this->queueDir . '/' . self::QUEUE_FILE_ACTIVE;
		// $this->log('Opening new active status stream: ' . $this->streamFile);
		$this->statusStream = fopen($this->streamFile, 'a'); // Append if present (crash recovery)
		
		// Okay?
		if (!is_resource($this->statusStream)) {
			throw new Exception('Unable to open stream file for writing: ' . $this->streamFile);
		}
	
		// If we don't have a last rotated time, it's effectively now
		if ($this->lastRotated == NULL) {
			$this->lastRotated = time();
		}
	
		// Looking good, return the resource
		return $this->statusStream;
	}
	
	/**
	* Rotates the stream file if due
	*/
	private function rotateStreamFile() {
		// Close the stream
		fclose($this->statusStream);
		
		// Create queue file with timestamp so they're both unique and naturally ordered
		$queueFile = $this->queueDir . '/' . self::QUEUE_FILE_PREFIX . '.' . date('Ymd-His') . '.queue';
		
		// Do the rotate
		rename($this->streamFile, $queueFile);
		
		// Did it work?
		if (!file_exists($queueFile)) {
			throw new Exception('Failed to rotate queue file to: ' . $queueFile);
		}
		
		// At this point, all looking good - the next call to getStream() will create a new active file
		// $this->log('Successfully rotated active stream to queue file: ' . $queueFile) . "\n";
	}
	
	protected function log($message,$level='notice')
	{
		// @error_log('Phirehose: ' . $message, 0);
	}

	
} // End of OpenFuegoQueueCollector class


class OpenFuegoQueueConsumer {
	
	/**
	 * Member attribs
	 */
	protected $queueDir;
	protected $filePattern;
	protected $checkInterval;
	
	/**
	 * Construct the consumer and start processing
	 */
	public function __construct($queueDir = OPENFUEGO_CACHE_DIR, $filePattern = 'OpenFuegoQueue*.queue', $checkInterval = 10) {
		$this->queueDir = $queueDir;
		$this->filePattern = $filePattern;
		$this->checkInterval = $checkInterval;
		
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
		
		// Loop infinitely
		while (TRUE) {
			
			// Keep the DB tidy. Remove entries older than OPENFUEGO_EXPIRATION_INT days.
			openfuego_clean_up();
	
			// Get a list of queue files
			$queueFiles = glob($this->queueDir . '/' . $this->filePattern);
			$lastCheck = time();
			
			// $this->log('Found ' . count($queueFiles) . ' queue files to process...');
			
			// Iterate over each file (if any)
			foreach ($queueFiles as $queueFile) {
				$this->processQueueFile($queueFile);		
	/*
				pcntl_signal_dispatch();
				if ($this->shouldStop()) exit;
	*/
				set_time_limit(60);
			}
	
			// Wait until ready for next check
			// $this->log('Sleeping...');
			while (time() - $lastCheck < $this->checkInterval) {
				sleep(1);
			}

		} // Infinite loop
	
	} // End process()
	
	/**
	 * Processes a queue file and does something with it (example only)
	 * @param string $queueFile The queue file
	 */
	protected function processQueueFile($queueFile) {
		// $this->log('Processing file: ' . $queueFile);
		
		// Open file
		$fp = fopen($queueFile, 'r');
		
		// Check if something has gone wrong, or perhaps the file is just locked by another process
		if (!is_resource($fp)) {
			$this->log('WARN: Unable to open file or file already open: ' . $queueFile . ' - Skipping.');
			return FALSE;
		}
		
		// Lock file
		flock($fp, LOCK_EX);
		
		// Loop over each line (1 line per status)
		$statusCounter = 0;
		while ($rawStatus = fgets($fp, 8192)) {
	
			$statusCounter ++;
			
			$status = json_decode($rawStatus, TRUE);	// convert JSON data into PHP array
			
			// if data is invalid (e.g., if a user has deleted a tweet; surprisingly frequent)
			if (is_array($status) == FALSE || isset($status['user']['id_str']) == FALSE) {
	 			// $this->log('Status ' . $status['id_str'] . ' is invalid, continuing.');
				continue; // skip it
			}
	/*
			if ((strtotime($status['created_at']) - time()) > (OPENFUEGO_EXPIRATION_INT * 60 * 60)) { // Can't remember why this is here, but it makes no sense
				continue; // skip it
			}
	*/			
			if (array_key_exists(0, $status['entities']['urls']) == FALSE) { // if tweet does not contain link
				continue;	// skip it
			}
		
			/* Weed out statuses created by undesired user. (The streaming API also returns _retweets of_
			** statuses by desired user, which we don't want.) */
			if (!openfuego_is_citizen($status['user']['id_str'])) {	// if the tweeter is not a citizen w/ influence > x
				continue; // skip it
			}			
	/*
			if (array_key_exists('retweeted_status', $status)) {
				$entities = $status['retweeted_status']['entities'];
			} else {
				$entities = $status['entities'];
			}
	*/
	
	/*
			if (array_key_exists('retweeted_status', $status)) {
				$status = $status['retweeted_status'];
			}
	*/
			openfuego_process_urls($status);
			
			// $this->log('Decoded tweet: ' . $status['user']['screen_name'] . ': ' . urldecode($status['text']));
	
			set_time_limit(60);
		
			unset($status, $entities);
		} // End while
		
		// Release lock and close
		flock($fp, LOCK_UN);
		fclose($fp);
		
		// All done with this file
		// $this->log('Successfully processed ' . $statusCounter . ' tweets from ' . $queueFile . ' - deleting.');
		unset($rawStatus);
		unlink($queueFile);
		
		if (function_exists('pcntl_signal_dispatch')) {
			pcntl_signal_dispatch();
			if ($this->shouldStop()) {
				exit;
			}
		}
	}
	
	/**
	 * Basic log function.
	 *
	 * @see error_log()
	 * @param string $messages
	 *
	 *
	 */
	protected function log($message, $level = 'notice') {
		// @error_log('Phirehose: ' . $message, 0);
	}
	
	// NEED LOG ROTATION SCRIPT

	public function shouldStop() {
		global $_openfuego_should_stop;
		if (isset($_openfuego_should_stop) && $_openfuego_should_stop) {
			return TRUE;
		}
		
		return FALSE;
	}
}