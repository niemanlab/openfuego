<?php
// Sanity check
if (false) { ?>
	OpenFuego requires PHP 5.3.0 or higher.
<?php }

if (!defined('OPENFUEGO') && function_exists('pcntl_fork')) {
	$error_message = "\n"
		. 'Do not run this script directly. Run openfuego-fetch.php to start.'
		. "\n\n";
	die($error_message);
}

require_once(dirname(__FILE__) . '/openfuego-config.php');
require_once(OPENFUEGO_PHIREHOSE_DIR . '/openfuego-phirehose-functions.php');

global $openfuego_authorities;
// Comment out the next line if you do not want to repopulate the universe on each fetch
// openfuego_populate_universe($openfuego_authorities, 1);

if (!$universe = openfuego_get_universe(1)) {
	openfuego_populate_universe($openfuego_authorities, 1);
	$universe = openfuego_get_universe(1);
}

$universe = array_slice($universe, 0, OPENFUEGO_TW_PREDICATE_LIMIT);
	
// Start streaming/collecting
$sc = new OpenFuegoQueueCollector(OPENFUEGO_TW_ACCESS_TOKEN, OPENFUEGO_TW_ACCESS_TOKEN_SECRET);

$sc->setFollow($universe);
unset($universe);

$sc->consume();

exit;
