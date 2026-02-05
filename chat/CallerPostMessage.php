<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours
require_once('../../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Release session lock immediately (file doesn't use session data)
session_write_close();

$Status = trim($_REQUEST["Status"]) ?? null;
$CallerID = trim($_REQUEST["CallerID"]) ?? null;
$Message = trim($_REQUEST["Message"]) ?? null;

if ($Status == 2) {
	$CallerName = "Caller";
	$query = "INSERT INTO Chat VALUES (now(), ?, ?, ?, ?, null, 0, 0)";
	$result = dataQuery($query, [$CallerID, $Status, $CallerName, $Message]);
} else {
	$CallerName = "";
	$query = "INSERT INTO Chat VALUES (now(), ?, ?, ?, (SELECT text FROM SystemMessages WHERE Status = ?), null, 0, 0)";
	$result = dataQuery($query, [$CallerID, $Status, $CallerName, $Status]);

	$query2 = "UPDATE Volunteers SET ChatInvite = null WHERE chatInvite = ?";
	$result2 = dataQuery($query2, [$CallerID]);
}

if(!$result) {
	http_response_code(500);
	echo "Database error";
	exit;
}

?>