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

ini_set('max_execution_time', 0);
ini_set('max_input_time', 0);
set_time_limit(0);

pcntl_signal(SIGHUP, SIG_IGN);

$dbh = openfuego_get_dbh();

$sql = "
CREATE TABLE IF NOT EXISTS `openfuego_citizens` (
  `user_id` bigint(20) unsigned NOT NULL,
  `influence` tinyint(2) unsigned NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `openfuego_links` (
  `link_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `first_seen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `first_tweet` bigint(20) unsigned NOT NULL,
  `first_user` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `first_user_id` bigint(20) unsigned DEFAULT NULL,
  `weighted_count` smallint(5) unsigned NOT NULL,
  `count` smallint(5) unsigned NOT NULL DEFAULT '1',
  `last_seen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`link_id`),
  UNIQUE KEY `url` (`url`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `openfuego_short_links` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `input_url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `long_url` text COLLATE utf8_unicode_ci NOT NULL,
  `last_seen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`input_url`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `openfuego_tweets_cache` (
  `link_id` mediumint(8) unsigned NOT NULL,
  `id_str` bigint(20) unsigned NOT NULL,
  `screen_name` varchar(15) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `text` varchar(140) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `profile_image_url` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`link_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
	
ALTER TABLE `openfuego_tweets_cache`
  ADD CONSTRAINT `FK.openfuego_tweets_cache.link_id` FOREIGN KEY (`link_id`) REFERENCES `openfuego_links` (`link_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";

try {
	$sth = $dbh->prepare($sql);
	$sth->execute();

} catch (PDOException $e) {
	die($e);
}

$pids = array();

$pids[0] = pcntl_fork();

if (!$pids[0]) {

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
	
	$fqc = new OpenFuegoQueueConsumer();
	$fqc->process();
	
	exit;
}

echo 'OpenFuego collect running as PID ' . $pids[0] . "\n";
echo 'OpenFuego consume running as PID ' . $pids[1] . "\n";
@file_put_contents(OPENFUEGO_CACHE_DIR . '/OpenFuego-collect.pid', $pids[0]);
@file_put_contents(OPENFUEGO_CACHE_DIR . '/OpenFuego-consume.pid', $pids[1]);

exit;
?>