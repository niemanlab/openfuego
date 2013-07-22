<?php

/**
  * The Getter object has one method:
  *
  * getItems($quantity, $hours, $scoring, $metadata)
  *
  * $quantity (int): Number of links desired. Default 20.
  * $hours (int): How far back to look for links. Default 24.
  * $scoring (bool): TRUE to employ  "freshness vs. quality" algorithm
  *   or FALSE to simply return most frequently tweeted links. Default TRUE.
  * $metadata (bool): TRUE to hydrate URLs with Embed.ly metadata.
  *   An API key must be set in config.php. Default FALSE.
 */
 
require(__DIR__ . '../../init.php');
use OpenFuego\app\Getter as Getter;

$fuego = new Getter();
$items = $fuego->getItems(20, 24, TRUE, TRUE); // quantity, hours, scoring, metadata

print '<pre>';
print_r($items);
print '</pre>';
?>