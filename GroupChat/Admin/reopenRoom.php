<?php
// ADMIN SCRIPT: REOPEN A SPECIFIC GROUP CHAT ROOM

// 1. Include db_login.php FIRST (sets session configuration)
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login.php');

// 2. Start session
session_start();

// 3. Check authentication
if ($_SESSION['auth'] != 'yes') {
	die("Unauthorized - Admin access required");
}

$userID = $_SESSION["UserID"] ?? "Admin";
$chatRoomID = $_REQUEST['chatRoomID'] ?? null;

// 4. Release session lock
session_write_close();

if(!$chatRoomID) {
	die("Error: No chat room ID provided");
}

// Include GroupChat database connection
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

// Get room info
$params = [$chatRoomID];
$query = "SELECT name, Open FROM groupChatRooms WHERE id = ?";
$result = groupChatDataQuery($query, $params);

if(!$result) {
	die("Error: Room not found");
}

$roomName = "";
$currentStatus = "";
foreach($result as $room) {
	$roomName = $room->name;
	$currentStatus = $room->Open;
}

if($currentStatus == 1) {
	die("Room '$roomName' is already open");
}

// REOPEN THE ROOM
$params = [$chatRoomID];
$query = "UPDATE groupChatRooms SET Open = 1 WHERE id = ?";
$result = groupChatDataQuery($query, $params);

// Send room reopen transaction
$params = [$userID, $chatRoomID];
$query = "INSERT INTO transactions VALUES (NULL, 'roomStatus', 'reopen', ?, ?, NULL, NULL, NULL, NULL, DEFAULT, DEFAULT)";
$result = groupChatDataQuery($query, $params);

// Clear any old closure transactions
$params = [$chatRoomID];
$query = "DELETE FROM transactions WHERE chatRoomID = ? AND type = 'roomStatus' AND action = 'closed'";
$result = groupChatDataQuery($query, $params);

echo "Room '$roomName' (ID: $chatRoomID) has been reopened successfully";

?>
