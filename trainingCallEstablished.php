<?php
session_start();

/// Twilio Received Variables
$CallSid = $_REQUEST["CallSid"];
$ConferenceSid = $_REQUEST["ConferenceSid"] ?? null;

$_SESSION['ConferenceSid'] = $ConferenceSid;
$_SESSION['CallSid'] = $CallSid;

// Release session lock after writing session data
session_write_close();

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	echo "<Response>";
	echo "</Response>";
?>
