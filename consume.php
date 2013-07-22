<?php namespace OpenFuego;

/** This script processes the data placed
  * in the queue by collect.php.
**/

use OpenFuego\lib\DbHandle as DbHandle;
use OpenFuego\app\Consumer as Consumer;
use OpenFuego\lib\Logger as Logger;

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
	Logger::fatal(__NAMESPACE__ . " consumer was terminated.");
});

$dbh = new DbHandle();

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

} catch (\PDOException $e) {
	die($e);
}

$dbh = NULL;

$consumer = new Consumer();
$consumer->process();

exit;
