<?php namespace OpenFuego;

/** This script connects to the Twitter stream
  * and captures raw data into a queue for processing.
**/

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300) {
	die(__NAMESPACE__ . ' requires PHP 5.3.0 or higher.');
}

if (php_sapi_name() != 'cli') {
	die('This script must be invoked from the command line.');
}

if (!defined('OPENFUEGO') && function_exists('pcntl_fork')) {
	$error_message = "\n"
		. 'Do not run this script directly. Run fetch.php to start.'
		. "\n\n";
	die($error_message);
}

require_once(__DIR__ . '/init.php');

register_shutdown_function(function() {
	\OpenFuego\lib\Logger::fatal(__NAMESPACE__ . " collector was terminated.");
});

$authorities = unserialize(AUTHORITIES);

$universe = new app\Universe;

/** The next line is commented out by default.
  * Uncomment it to repopulate the universe on each fetch. */

// $universe->populate($authorities, 1);

$citizens = $universe->getCitizens(1);

if (!$citizens) {
	$universe->populate($authorities, 1);
	$citizens = $universe->getCitizens(1);
}

$citizens = array_slice($citizens, 0, TWITTER_PREDICATE_LIMIT);
	
// Start streaming/collecting
$collector = new app\Collector(TWITTER_OAUTH_TOKEN, TWITTER_OAUTH_SECRET);

$collector->setFollow($citizens);

$collector->consume();

exit;
