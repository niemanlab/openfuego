<?php
// Sanity check
if (false) { ?>
	OpenFuego requires PHP 5.3.0 or higher.
<?php }

ini_set('display_errors', 1);

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300)
	die('OpenFuego requires PHP 5.3.0 or higher.');

if (!file_exists(dirname(__FILE__) . '/openfuego-config.php'))
	die("Cannot start, openfuego-config.php is missing.\n");

require_once(dirname(__FILE__) . '/openfuego-config.php');
require_once(OPENFUEGO_PHIREHOSE_DIR . '/openfuego-phirehose-functions.php');

if (!function_exists('pcntl_fork')) {
	$error_message = "\n"
		. 'To start OpenFuego, run these commands:'
		. "\n\n"
		. "\tnohup " . OPENFUEGO_PHP_DIR . '/php ' . OPENFUEGO_DIR . '/openfuego-collect.php > /dev/null 2> /dev/null & echo $!'
		. "\n"
		. "\tnohup " . OPENFUEGO_PHP_DIR . '/php ' . OPENFUEGO_DIR . '/openfuego-consume.php > /dev/null 2> /dev/null & echo $!'
		. "\n\n";

	die($error_message);
}

pcntl_signal(SIGHUP, SIG_IGN);

$pids = array();

$pids[0] = pcntl_fork();

if (!$pids[0]) {	
	include(OPENFUEGO_DIR . '/openfuego-collect.php');
}

$pids[1] = pcntl_fork();

if (!$pids[1]) {

	function openfuego_sig_handler($signo) {
		switch ($signo) {
			case SIGTERM:
				// handle shutdown tasks
				global $_openfuego_should_stop;
				$_openfuego_should_stop = TRUE;
				break;
			case SIGINT:
				// handle ^C
				global $_openfuego_should_stop;
				$_openfuego_should_stop = TRUE;
				break;
			default:
				// handle all other signals
				global $_openfuego_should_stop;
				$_openfuego_should_stop = TRUE;
				break;
		}
	}

	pcntl_signal(SIGTERM, 'openfuego_sig_handler');
	pcntl_signal(SIGINT,  'openfuego_sig_handler');

	include(OPENFUEGO_DIR . '/openfuego-consume.php');
}

echo 'OpenFuego collect running as PID ' . $pids[0] . "\n";
echo 'OpenFuego consume running as PID ' . $pids[1] . "\n";
@file_put_contents(OPENFUEGO_CACHE_DIR . '/OpenFuego-collect.pid', $pids[0]);
@file_put_contents(OPENFUEGO_CACHE_DIR . '/OpenFuego-consume.pid', $pids[1]);

exit;
?>