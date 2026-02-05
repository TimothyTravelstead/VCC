<?php
// EMERGENCY SCRIPT: CLOSE ALL GROUP CHAT ROOMS IMMEDIATELY

// 1. Include db_login.php FIRST (sets session configuration)
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login.php');

// 2. Start session
session_start();

// 3. Check authentication
if ($_SESSION['auth'] != 'yes') {
	die("Unauthorized");
}

$userID = $_SESSION["UserID"] ?? "Admin";

// 4. Release session lock
session_write_close();

// Include GroupChat database connection
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

echo "EMERGENCY: CLOSING ALL GROUP CHAT ROOMS\n\n";

// Get all room IDs
$query = "SELECT id, name FROM groupChatRooms";
$result = groupChatDataQuery($query, []);

if(!$result) {
	die("No rooms found");
}

$roomsClosed = 0;

foreach($result as $room) {
	$chatRoomID = $room->id;
	$roomName = $room->name;

	echo "Closing room: $roomName (ID: $chatRoomID)\n";

	// 1. IMMEDIATELY CLOSE THE ROOM
	$params = [$chatRoomID];
	$query = "UPDATE groupChatRooms SET Open = 0 WHERE id = ?";
	groupChatDataQuery($query, $params);

	// 2. GET ALL ACTIVE USERS AND FORCE SIGNOFF
	$params = [$chatRoomID];
	$query = "SELECT userID FROM callers WHERE chatRoomID = ? AND status = 1";
	$activeUsers = groupChatDataQuery($query, $params);

	if($activeUsers) {
		foreach($activeUsers as $user) {
			$activeUserID = $user->userID;

			// Set user status to 0 (signed off)
			$params = [$activeUserID, $chatRoomID];
			$query = "UPDATE callers SET status = 0, sendToChat = NULL WHERE userID = ? AND chatRoomID = ?";
			groupChatDataQuery($query, $params);

			// Send signoff transaction
			$params = [$activeUserID, $chatRoomID];
			$query = "INSERT INTO transactions VALUES (NULL, 'user', 'signoff', ?, ?, NULL, NULL, NULL, NULL, DEFAULT, DEFAULT)";
			groupChatDataQuery($query, $params);

			echo "  - Forced signoff: $activeUserID\n";
		}
	}

	// 3. Send room closure transaction
	$params = [$userID, $chatRoomID];
	$query = "INSERT INTO transactions VALUES (NULL, 'roomStatus', 'closed', ?, ?, NULL, NULL, '2', NULL, DEFAULT, DEFAULT)";
	groupChatDataQuery($query, $params);

	echo "  - Room closed and closure message sent\n\n";
	$roomsClosed++;
}

echo "\n======================\n";
echo "COMPLETE: Closed $roomsClosed rooms\n";
echo "All users have been signed off\n";
echo "All feeds will now send 'room closed' messages\n";
echo "======================\n";

?>
