<?php
// 1. Include db_login.php FIRST (sets session configuration)
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login.php');

// 2. Start session (inherits 8-hour timeout from db_login.php)
session_start();

// 3. Check authentication
if ($_SESSION['auth'] != 'yes') {
	die("Unauthorized");
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

// 4. Read session data
$userID = $_SESSION["UserID"] ?? "Admin";
$chatRoomID = $_REQUEST['chatRoomID'] ?? null;

// 5. Release session lock immediately
session_write_close();

if(!$chatRoomID) {
	die("Error: No chat room ID provided");
}

// Include GroupChat database connection
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

// Send room closure message to all participants FIRST (before cleanup)
$params = [$userID, $chatRoomID];
$query = "INSERT INTO transactions VALUES (
	NULL,
	'roomStatus',
	'closed',
	?,
	?,
	NULL,
	NULL,
	'2',
	NULL,
	DEFAULT,
	DEFAULT)";
$result = groupChatDataQuery($query, $params);

$closureTransactionId = null;
if($result) {
	// Get the ID of the closure transaction we just inserted
	$params = [$chatRoomID];
	$query = "SELECT id FROM transactions WHERE chatRoomID = ? AND type = 'roomStatus' AND action = 'closed' ORDER BY id DESC LIMIT 1";
	$result = groupChatDataQuery($query, $params);
	if($result) {
		foreach($result as $item) {
			$closureTransactionId = $item->id;
			break;
		}
	}
}

// Email the transcript BEFORE deleting the chat
$params = [$chatRoomID];
$_REQUEST["chatRoomID"] = $chatRoomID;  // Set for includeFormatGroupChatEmail.php
require_once('includeFormatGroupChatEmail.php');

// Now proceed with room cleanup
$params = [$chatRoomID];

$query = "DELETE FROM callers WHERE chatRoomID = ?";
$result = groupChatDataQuery($query, $params);

$query = "DELETE FROM groupChatStatus WHERE chatRoomID = ?";
$result = groupChatDataQuery($query, $params);

$query = "DELETE FROM groupChat WHERE chatRoomID = ?";
$result = groupChatDataQuery($query, $params);

$query = "DELETE FROM groupchattimeouts WHERE chatRoomID = ?";
$result = groupChatDataQuery($query, $params);

$query = "UPDATE groupChatRooms SET Open = 0 WHERE id = ?";
$result = groupChatDataQuery($query, $params);

// Delete all transactions EXCEPT the closure message that participants need to see
if($closureTransactionId) {
	$params = [$chatRoomID, $closureTransactionId];
	$query = "DELETE FROM transactions WHERE chatRoomID = ? AND id != ?";
	$result = groupChatDataQuery($query, $params);
} else {
	// Fallback: delete all transactions if we couldn't get the closure ID
	$params = [$chatRoomID];
	$query = "DELETE FROM transactions WHERE chatRoomID = ?";
	$result = groupChatDataQuery($query, $params);
}

echo "Room closed successfully. Transcript has been emailed.";

?>
