<!DOCTYPE html>
<html>
<head>
<title>OpenFuego stream checker</title>
<script src="http://code.jquery.com/jquery-1.7.2.min.js" type="text/javascript"></script>
<script type="text/javascript">
	function updateStream(){
	    $('#openfuego-stream').load('openfuego-test-backgrounder.php');
	}
	setInterval('updateStream()', 1000);
</script>
</head>
<body>

<img src="ajax-loader.gif" width="24" height="24" alt="Continuously updating" title="Continuously updating" />

<div id="openfuego-stream"><?php include('openfuego-test-backgrounder.php'); ?></div>

</body>
</html>