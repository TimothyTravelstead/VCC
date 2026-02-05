<?php
// Force no-cache headers to prevent browsers from caching this page

require_once '../private_html/db_login.php';
session_start();

// Protect authenticated sessions from being destroyed
// If user is already logged in, redirect them back to the console
// This prevents session destruction from browser prefetch, back button, or multiple tabs
if (isset($_SESSION['auth']) && $_SESSION['auth'] === 'yes') {
    header('Location: index2.php');
    exit;
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");

// Add ETag header with current timestamp to force reload
header("ETag: \"" . md5(time() . rand()) . "\"");

// Additional headers to prevent caching by CDNs and proxies
header("Vary: *");
header("X-Accel-Expires: 0");

// Note: session_cache_limiter() removed - caused warnings after session_start()

$system_path = "../private_html/system";
$application_folder = "../private_html/application";



if(!isset($_SESSION['message'])) {
	session_unset();
	$message = null;
} else {
	$message = $_SESSION['message'];
	if ($message == "You are Not Signed Up for this Shift.  Please sign up now.") {
		$message = "";
	}
}

include '../private_html/csrf_protection.php';

// Set Timezone for Date Information
if (!defined('TIMEZONE')) {
    define('TIMEZONE', 'America/Los_Angeles');
}
date_default_timezone_set(TIMEZONE);
$now = new DateTime();
$mins = $now->getOffset() / 60;
$mins = abs($mins);
	

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

// Determine Whether to Play Video
$dir = "HeadquartersMessage/";
$lastMod = 0;
$lastModFile = '';
foreach (scandir($dir) as $entry) {
    $file = $dir.$entry;
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if (is_file($dir.$entry) && filemtime($dir.$entry) > $lastMod && ($ext == "mp4" || $ext == "m4v" || $ext == "html")) {
        $lastMod = filemtime($dir.$entry);
        $lastModFile = $dir.$entry;
    }
}

$_SESSION['videoFileTime'] = $lastMod;
$_SESSION['videoFileName'] = $lastModFile;



// Call the function and set the shared key

$key = $_SESSION['key'] = randomString(20);
$_SESSION["auth"] = "";
$_SESSION["UserID"] = "";

// Release session lock after writing session data
session_write_close();


//header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0, max-age=0"); // HTTP 1.1.
//header("Pragma: no-cache"); // HTTP 1.0.
//header("Expires: 0"); // Proxies.

// PREVENT CACHING
header("Cache-Control: no-store, no-cache, must-revalidate, private");


?>

<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UMF-8" />
	<meta NAME="ROBOTS" CONTENT="NONE">
	<meta NAME="GOOGLEBOT" CONTENT="NOARCHIVE">

	<!-- Cache Control Meta Tags to Force Reload -->
	<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
	<meta http-equiv="Pragma" content="no-cache" />
	<meta http-equiv="Expires" content="0" />
	<meta http-equiv="Last-Modified" content="<?php echo gmdate('D, d M Y H:i:s'); ?> GMT" />

	<!-- Version meta tag to indicate deployment -->
	<meta name="version" content="<?php echo date('YmdHis'); ?>" />

    <title>LGBT National Help Center Volunteer Logon</title>
    <link type="text/css" rel="stylesheet" href="logon.css?v=202" />
  
  	<!-- JavaScript that contains the functions which perform the actual hashing -->
    <script type="text/javascript" src="sha1.js?v=202"></script>
    <script src="LibraryScripts/Ajax.js?v=202" type="text/javascript"></script>
    <script src="LibraryScripts/domAndString.js?v=202" type="text/javascript"></script>
    <script src="logon.js?v=202" type="text/javascript"></script>
    <?php echo getCSRFJavaScript(); ?>
   </head>
  <body>
  	<div class="LogonContent">
  	 	<div class="LogonHeader">
  			<h1>VOLUNTEER LOGIN</h1>
		</div>
		<div ID="javascriptWarning">YOU MUST HAVE JAVASCRIPT ENABLED TO USE THIS WEBSITE.</DIV>
		<div id="LogonMessage">
			<?php if (!empty($message)) echo "<p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>"; ?>
		</div>		
		<div id="Logon">
		    <form action="javascript:validate()" method="post" id="EntryForm">
				<input type="hidden" id="key" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" />
				<table>
					<tr><th id='ColumnWidthSetter'></th><td>  </td></tr>
					<tr>
						<th><label for="user">User Id:</label></th>
						<td><input type="text" id="user" name="user" autocomplete="username" tabindex = "1"/></td>
					</tr>
					<tr>
						<th><label for="pass">Password:</label></th>
						<td><input type="password" id="pass" name="pass" autocomplete="current-password" tabindex = "2"/></td>
					</tr>
					<tr>
						<th id='CallerTypeLabel'><label for="ChatOnly">Chat Only:</label></th>
						<td>
							<input type='checkbox' name='ChatOnly' id='ChatOnly' value='1' tabindex = "3" />
						</td>
					</tr>
					<tr>
						<th><span class="logonType">Type:</span></th>
						<td id="logonTypeMenu">							
						</td>
					</tr>
          <tr><th></th><td><br></td></tr>
					<tr>
						<th><span class="logonType">Type:</span></th>
						<td id="trainees">
							
						</td>
					</tr>
					<tr>
						<th>
							<div id="AdminFlag">
							</div>
						</th>
						<td>
						</td>
					</tr>
				</table>
				<div id="LogonButtons">
				  <input class="LogonButton" id="submitButton" type="submit" value="Submit" tabindex = "4"/>
				  <input id="calendarButton" type="button" value="Calendar" tabindex = "5"/>
				</div>
    		</form>
    	</div>
		<form action="loginverify2.php" method="post" id="finalform">
			<div id='endOfShiftBox'>
				<h2>SELECT END OF SHIFT</h2>
				<p>Because you are not on the Calendar for this shift, please select an end time for your shift today.</p><br /><br />
				<label for="endOfShiftMenu">End of Shift Time:</label>
				<select name='endOfShiftMenu' id='endOfShiftMenu'>
					<option value='None'>END OF SHIFT</option>
				</select>
			</div>
			<input type="hidden" name="UserID" id="UserID"  />
			<input type="hidden" name="hash" id="hash" />
			<input type="hidden" name="DefaultHotline" id="DefaultHotline" />
			<input type="hidden" name="Shift" id="Shift" />
			<input type="hidden" name="Desk" id="Desk" />
			<input type="hidden" name="Admin" id="Admin" value="0" />
			<input type="hidden" name="ResourcesOnly" id="ResourcesOnly" value="0" />
			<input type="hidden" name="ChatOnlyFlag" id="ChatOnlyFlag" value="0" />
			<input type="hidden" name="Trainee" id="Trainee" value="" />
			<input type="hidden" name="Calendar" id="Calendar" value="" />
			<input type="hidden" name="editResources" id="editResources" value="" />
			<?php outputCSRFTokenField(); ?>
			<input type="submit" id="finalFormSubmit" style="position: absolute; left: -9999px; width: 1px; height: 1px;" aria-hidden="true" tabindex="-1" />
		</form>
<?php	
	echo "<input type='hidden' id='serverOffsetMinutes' value = '".$mins."'>";
?>
	</div>
  </body>
</html>

