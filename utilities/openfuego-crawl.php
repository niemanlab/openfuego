<?php // Crawl a URL as OpenFuego, for testing.

ini_set('display_errors', 1);
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header('Content-Type: text/plain');

require('../openfuego-config.php');

$url = 'http://www.nytimes.com/2013/05/03/nyregion/central-park-five-petition-oversimplifies-blame-in-a-collective-failure.html?partner=socialflow&smid=tw-nytmetro&_r=3&';

$response = openfuego_curl($url, 'GET', FALSE, TRUE);

echo $response;

?>