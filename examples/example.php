<?php
require_once(dirname(__FILE__) . '../../openfuego-config.php');
$items = openfuego_get_items(30, 24, TRUE, TRUE); // quantity, hours, scoring, metadata

// a test if you just want raw data
// print '<pre>'; print_r($items); print '</pre>'; die;
?>

<!DOCTYPE html>
<html>
	<head>
		<title>OpenFuego Example</title>
		<link rel="stylesheet" href="example.css">
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
	</head>
	<body>

		<div id="container">

<?php 

foreach ($items as $item) {

	$item['html'] = '';
	$url = $item['url'];
	$rank = $item['rank'];
	$has_art = NULL;
	$col = 3;
	
	if (isset($item['thumbnail_url'])) {
	
		$has_art = TRUE;
	
		$img_url = $item['thumbnail_url'];
		$img_aspect = $item['thumbnail_width'] / $item['thumbnail_height'];

		if ($item['thumbnail_width'] >= 380 && $img_aspect >= 1 && $img_aspect <= 2 && $rank == 0) {
			$img_width = 380;
			$col = 4;
			$fontsize = '28';
		}
		
		elseif ($item['thumbnail_width'] >= 280 && $img_aspect < 2) {
			$img_width = 280;
			$fontsize = '20';
			$col = 3;
		}
		
		elseif ($item['thumbnail_width'] >= 180 && $img_aspect < 2) {
			$img_width = 180;
			$col = 2;
			$fontsize = '16';	
		}
		
		else {
			$has_art = FALSE;
		}
		
		if ($has_art)
			$img_height = round($img_width / $img_aspect);
	}
	
	$title = isset($item['title']) ? $item['title'] : NULL;
	$description = isset($item['description']) ? $item['description'] : NULL;
	
	if (!$has_art && !$title && !$description) continue; // skip it
				
	if ($has_art) {
		
		$inner = '<div class="box rank' . $rank . ' col' . $col . '" style="background-image:url(\'' . $img_url . '\'); height:' . $img_height . 'px">';
		if ($description != $title) $inner .= '<div class="overlay" style="height:' . $img_height . 'px;"><p style="font-size:' . $fontsize . 'px;">' . $title . '</p></div>';
		$inner .= '</div>';
		
//		<img src="' . $item['thumbnail_url'] . '" width="' . $item['thumbnail_width'] . '" />';
	
	} elseif (strpos($url, '.png') || strpos($url, '.gif') || strpos($url, '.jpg') || strpos($url, '.jpeg')) {
		$img_url = $url;
		$col = 3;
		$inner = '<div class="box rank' . $rank . ' col' . $col . '" style="background-image:url(\'' . $img_url . '\'); "><img src="' . $img_url . '" style="visibility:hidden" /></div>';
		
	} else {
		$inner = '<div class="box rank' . $rank . ' col' . $col . '">';
		$inner .=  '<div class="text"><h2>' . $title . '</h2>';
		if ($description != $title) $inner .= '<p>' . $description . '</p>';
		$inner .= '</div></div>';
	}
	
	echo '<a href="' . $url . '" title="via @' . $item['first_user'] . '">' . $inner . '</a>';
}
?>
		</div>

		<script src="masonry.min.js"></script>
		<script>
		window.onload = function() {
		  var wall = new Masonry( document.getElementById('container'), {
		    columnWidth: 100,
		    gutterWidth: 0,
		    isFitWidth: true
		  });
		};
		</script>
		 
	</body>
</html>