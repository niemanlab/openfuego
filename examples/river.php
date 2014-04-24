<!DOCTYPE html>
<meta charset="utf-8">

<html>
  <head>
    <title>OpenFuego River</title>
	
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	
	<link href="//netdna.bootstrapcdn.com/bootstrap/3.0.2/css/bootstrap.min.css" rel="stylesheet">
	<link href='//fonts.googleapis.com/css?family=Fjalla+One' rel='stylesheet' type='text/css'>
	<link href='//fonts.googleapis.com/css?family=Vollkorn:400italic,400' rel='stylesheet' type='text/css'>
	<link href="//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css" rel="stylesheet">

    <script src="//code.jquery.com/jquery-2.0.3.js"></script>
	<script src="//netdna.bootstrapcdn.com/bootstrap/3.0.2/js/bootstrap.min.js"></script>
			
	<style>

	h1,h2,h3,h4,a {
		color: #333;
		font-family: 'Fjalla One', sans-serif !important;
		font-weight: 400;
		line-height: 1.3em;
	}

	p {
		color: #666;
		font-family: 'Vollkorn', serif;
		font-size: 1.3em;
		line-height: 1.3em;
	}

	.tweet p, .tweet a {
		font-family: 'Helvetica Neue', Helvetica, Arial, serif !important;
		font-size: 14px;
		line-height: 18px;	
	}
	
	.thumb {
		width: 140px
	}
	
	.space-below {
		margin-bottom: 5px;
	}
	
	</style>
	
	
</head>
<body>

	<nav class="navbar navbar-default" role="navigation">
	  <div class="navbar-header">
	    <a class="navbar-brand" href="#">Top Headlines</a>
	  </div>
	</nav>

    <div class="container">
		<div class="row">
			<div class="col-md-8 col-md-offset-2">

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
 
		require(__DIR__ . '/../init.php');
		use OpenFuego\app\Getter as Getter;

		$fuego = new Getter();
		$items = $fuego->getItems(20, 24, FALSE, TRUE); // quantity, hours, scoring, metadata
		
		$i = 0;
			
		print("<div class='media'>");
		
		foreach ($items as &$value) {
			$j = $i + 1;
			
			print("<a target='_blank' href='");
			print($items[$i]["metadata"]["url"]);
			print("'><img class='visible-xs img-responsive space-below' src='");
			print($items[$i]["metadata"]["thumbnail_url"]);
			print("'></a>");
			print("<a class='pull-left' target='_blank' href='");
			print($items[$i]["metadata"]["url"]);
			print("'><img class='thumb hidden-xs' class='media-object' src='");
			print($items[$i]["metadata"]["thumbnail_url"]);
			print("'></a><div class='media-body'><h3 class='media-heading'>$j. <a target='_blank' href='");
			print($items[$i]["metadata"]["url"]);
			print("'>");
			print($items[$i]["metadata"]["title"]);
			print("</a></h3><p><em>");
			print($items[$i]["metadata"]["description"]);
			print("</em></p>");
		
			
			print("<div class='well tweet'><p><a target='_blank' href='");
			print($items[$i]["tw_tweet_url"]);
			print("'><i class='fa fa-twitter'></i> Via @");
			print($items[$i]["tw_screen_name"].": ");
			print($items[$i]["tw_text"]."</a></p></div>");
			
			print("</div>");
			
			print("<hr><br/>");
			
			
			$i = $i + 1;
		}
		
		print("</div>");
				
		print '<br/><br/><button type="button" class="btn btn-default btn-block" data-toggle="collapse" data-target="#debug">DEBUG</button><br/><br/>';
		print '<div id="debug" class="collapse"><pre>';
		print_r($items);
		print '</pre></div>';
		?>
		
	   		</div> <!-- /column -->
			
 	   </div> <!-- /row -->

    </div> <!-- /container -->

</body>
</html>
