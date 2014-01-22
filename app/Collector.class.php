<?php namespace OpenFuego\app;

class Collector extends \OauthPhirehose {
	/**
	* Subclass specific constants
	*/
	const QUEUE_FILE_PREFIX = 'CollectorQueue';
	const QUEUE_FILE_ACTIVE = '.CollectorQueue.current';
	
	/**
	* Member attributes specific to this subclass
	*/
	protected $queueDir;
	protected $rotateInterval;
	protected $streamFile;
	protected $statusStream;
	protected $lastRotated;
	protected $_pcntlEnabled;

	/**
	* Overidden constructor to take class-specific parameters
	* 
	* @param string $token
	* @param string $secret
	* @param string $queueDir
	* @param integer $rotateInterval
	*/

	public function __construct($token, $secret, $queueDir = \OpenFuego\TMP_DIR, $rotateInterval = 10) {
	
		// Set subclass parameters
		$this->queueDir = $queueDir;
		$this->rotateInterval = $rotateInterval;
		$this->_pcntlEnabled = function_exists('pcntl_signal_dispatch') ? TRUE : FALSE;
		
		// Call parent constructor
		return parent::__construct($token, $secret, \Phirehose::METHOD_FILTER);
	}
	
	/**
	* Enqueue each status
	*
	* @param string $status
	*/
	public function enqueueStatus($status) {
		
		// Write the status to the stream (must be via getStream())
		fputs($this->getStream(), $status . PHP_EOL);
		
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

		// Check for SIGTERM to shut down gracefully
		if ($this->_pcntlEnabled == TRUE) {
			$this->handleSignals();
		}

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
	
	protected function log($message, $level = 'notice') {

	}


	public function handleSignals() {

		pcntl_signal_dispatch();
		
		global $_should_stop;

		if (isset($_should_stop) && $_should_stop == TRUE) {
			exit();
		}
	}
}
