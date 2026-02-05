<?php
// Include database connection FIRST to set session configuration
require_once('../../private_html/db_login.php');

session_start();

// Enhanced cache control headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); // HTTP/1.1
header("Pragma: no-cache"); // HTTP/1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header("Vary: *"); // Indicate the response is varied based on the request

function randomString($length) {
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    $str = NULL;
    $i = 0;
    while ($i < $length) {
        $num = rand(0, 61);
        $tmp = substr($chars, $num, 1);
        $str .= $tmp;
        $i++;
    }
    return $str;
}


$_SESSION['auth'] = 'yes';
$_SESSION['referringPage'] = $_SERVER['HTTP_REFERER'] ?? null;
$_SESSION['groupChatTransfer'] = '0';
$key = $_REQUEST['CallerID'] ?? null;

if($key) {
	// Check if this chat session exists (volunteer has accepted it)
	$query = "SELECT count(UserID) as count FROM Volunteers WHERE Active1 = ? OR Active2 = ?";
	$result = dataQuery($query, [$key, $key]);
	$accepted = ($result && count($result) > 0) ? $result[0]->count : 0;

	if($accepted) {
		$_SESSION['CallerID'] = $key;
		$_SESSION['groupChatTransfer'] = "1";
	} else {
		die("No such chat: ".$key);
	}
} else {
	$key = $_SESSION['CallerID'] = randomString(20);
}

// Release session lock now that we're done writing session data
session_write_close();

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<title>GLBT National Help Center</title>
		<meta http-equiv="Content-Type" content="text/html;charset=ISO-8859-1" />
		<meta http-equiv="Pragma" content="no-cache" />
	    <meta http-equiv="Expires" content="Mon, 22 Jul 2002 11:12:01 GMT" />

		<script>
			(function(){var p=[],w=window,d=document,e=f=0;p.push('ua='+encodeURIComponent(navigator.userAgent));e|=w.ActiveXObject?1:0;e|=w.opera?2:0;e|=w.chrome?4:0;
			e|='getBoxObjectFor' in d || 'mozInnerScreenX' in w?8:0;e|=('WebKitCSSMatrix' in w||'WebKitPoint' in w||'webkitStorageInfo' in w||'webkitURL' in w)?16:0;
			e|=(e&16&&({}.toString).toString().indexOf("\n")===-1)?32:0;p.push('e='+e);f|='sandbox' in d.createElement('iframe')?1:0;f|='WebSocket' in w?2:0;
			f|=w.Worker?4:0;f|=w.applicationCache?8:0;f|=w.history && history.pushState?16:0;f|=d.documentElement.webkitRequestFullScreen?32:0;f|='FileReader' in w?64:0;
			p.push('f='+f);p.push('r='+Math.random().toString(36).substring(7));p.push('w='+screen.width);p.push('h='+screen.height);var s=d.createElement('script');
			s.src='https://www.volunteerlogin.org/server/detect.php?' + p.join('&');d.getElementsByTagName('head')[0].appendChild(s);})();
		</script>
	</head>
<body onunload="">
	<script src="../LibraryScripts/ErrorModal.js" type="text/javascript"></script>
	<script src="chatavailable.js" type="text/javascript"></script>
	<script>
		 window.resizeTo(490, 720);
	</script>
	<?php 
		echo "<input type='hidden' id='referrerValue' value='".$_SESSION['referringPage']."' />";
		echo "<input type='hidden' id='groupChatTransferFlag' value='".$_SESSION['groupChatTransfer']."' />";
		echo "<input type='hidden' id='groupChatCallerId' value='".$key."' />";
	?>
	
</body>
</html>