<?php
/**
  * These settings should NOT be changed.
  * Edit openfuego-config.php to set up OpenFuego.
  * Gracias.
**/

// This file should be included by openfuego-config.php
if (!defined('OPENFUEGO_DIR'))
	die('Where is openfuego-config.php?');
else
	define('OPENFUEGO', TRUE);

define('OPENFUEGO_DB_DRIVER',		'mysql');

define('OPENFUEGO_USER_AGENT', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
define('OPENFUEGO_REFERER', 'http://google.com');

define('OPENFUEGO_RESOURCES_DIR',	OPENFUEGO_DIR . '/resources');

define('OPENFUEGO_TWITTER_DIR',		OPENFUEGO_RESOURCES_DIR . '/twitter');
define('OPENFUEGO_SHORTENERS_DIR',	OPENFUEGO_RESOURCES_DIR . '/shorteners');
define('OPENFUEGO_PHIREHOSE_DIR',	OPENFUEGO_RESOURCES_DIR . '/phirehose');

define('OPENFUEGO_PHP_DIR',			PHP_BINDIR); // supported by all environments?

define('OPENFUEGO_CACHE_DIR', OPENFUEGO_DIR . '/cache');
if (!is_dir(OPENFUEGO_CACHE_DIR)) {
	mkdir(OPENFUEGO_CACHE_DIR);
	file_put_contents(OPENFUEGO_CACHE_DIR . '/openfuego-cache', 'This is just to initialize the cache directory in some environments. You can delete it.');
}

define('OPENFUEGO_POSTMASTER', 'openfuego@openfuego.local'); // the "from" address on error reports

define('OPENFUEGO_TW_PREDICATE_LIMIT', 5000);

define('OPENFUEGO_EMBEDLY_API_ROOT', 'http://api.embed.ly/1');
define('OPENFUEGO_EMBEDLY_API_ENDPOINT', 'oembed');

/*
global $argv;
if (in_array($argv[1], array('-v', '-verbose'))) define('OPENFUEGO_VERBOSE', TRUE);
*/

require_once(OPENFUEGO_DIR . '/openfuego-salsa.php');

$openfuego_bitly_pro_domains = array(
	'bit.ly','bitly.com','j.mp'
);


$openfuego_short_domains = array( // These domains' canonical URLs are already short, so we don't need to look them up.
	'twitpic.com',
	'instagr.am',
	'yfrog.com',
	'twitpic.com',
	'vimeo.com',
	'i.imgur.com',
	'mlkshk.com',
	'lockerz.com',
	'path.com'
);

require_once(OPENFUEGO_SHORTENERS_DIR . '/shorteners-config.php');
require_once(OPENFUEGO_TWITTER_DIR . '/twitter-oauth.php');

require_once(OPENFUEGO_DIR . '/openfuego-functions.php');
?>