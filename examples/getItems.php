<?php
require_once(__DIR__ . '../../init.php');
$fuego = new \OpenFuego\app\Getter\Getter;
$items = $fuego->getItems(20, 24, TRUE, TRUE); // quantity, hours, scoring, metadata

print '<pre>';
print_r($items);
print '</pre>';
?>