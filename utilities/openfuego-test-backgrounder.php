<?php require_once('openfuego-config.php');

$dbh = openfuego_get_dbh();

$sth = $dbh->prepare('SELECT * FROM openfuego_links ORDER BY last_seen DESC LIMIT 10;');
$sth->execute();

$rows = $sth->fetchAll(PDO::FETCH_ASSOC);

echo '<ol>';
foreach ($rows as $row) {
	
	echo '<li>';
	echo '<a href="' . $row['url'] . '">' . $row['url'] . '</a> (@' . $row['first_user'] . ')<br />Last seen: ' . date('F j, Y, g:i:s a', strtotime($row['last_seen']));
	echo '</li>';
}
echo '</ol>';

?>