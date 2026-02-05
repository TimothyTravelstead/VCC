<?php

require_once('../db_login.php');
session_start();
ini_set("session.gc.maxlifetime", "14400000");
session_cache_limiter('nocache');

error_reporting(E_ERROR | E_WARNING | E_PARSE);

$userID = $_SESSION['UserID'] ?? null;

// Release session lock after reading session data
session_write_close();

if(!$userID) {
	exit("none");
}

$params = [$userID];	

$query = "SELECT chatRoomID from callers where userID = ? and status = 1";
$result = dataQuery($query, $params);

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