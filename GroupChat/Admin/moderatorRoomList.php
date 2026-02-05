<?php
session_start();

// Include main VCC database connection FIRST (db_login_GroupChat.php depends on it)
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login.php');

// Then include GroupChat database connection
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');
error_reporting(E_ERROR | E_WARNING | E_PARSE);

$userID = $_SESSION['UserID'] ?? null;

// Release session lock after reading session data
session_write_close();

if(!$userID) {
	exit("none");
}

$params = [$userID];

// Check for active moderator sessions (status = 1 for visible moderators, OR moderator = 2 for stealth admins)
// Admin moderators have status = 0 (stealth mode) but still need to sign off before exiting
$query = "SELECT chatRoomID from callers where userID = ? and (status = 1 OR moderator = 2)";
$result = groupChatDataQuery($query, $params);

if(!$result) {
	$rooms = false;
} else {
	$rooms = Array();
	foreach($result as $item) {
		foreach($item as $key=>$value) {
			array_push($rooms, $value);
		}
	}
}		



if($rooms) {
	echo json_encode($rooms);
} else {
	echo "none";
}

?>