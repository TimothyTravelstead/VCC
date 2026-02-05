<?php
// EMERGENCY SCRIPT: CLOSE ALL GROUP CHAT ROOMS IMMEDIATELY
// This script can be run from command line without authentication

// Include GroupChat database connection
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

$userID = "EmergencyAdmin";

echo "==============================================\n";
echo "EMERGENCY: CLOSING ALL GROUP CHAT ROOMS NOW\n";
echo "==============================================\n\n";

// Get all room IDs
$query = "SELECT id, name FROM groupChatRooms";
$result = groupChatDataQuery($query, []);

if(!$result) {
	die("No rooms found\n");
}

$roomsClosed = 0;
$usersSignedOff = 0;

foreach($result as $room) {
	$chatRoomID = $room->id;
	$roomName = $room->name;

	echo "Closing room: $roomName (ID: $chatRoomID)\n";

	// 1. IMMEDIATELY CLOSE THE ROOM TO BLOCK NEW ACCESS
	$params = [$chatRoomID];
	$query = "UPDATE groupChatRooms SET Open = 0 WHERE id = ?";
	groupChatDataQuery($query, $params);
	echo "  ✓ Room locked (Open = 0)\n";

	// 2. GET ALL ACTIVE USERS AND FORCE SIGNOFF IMMEDIATELY
	$params = [$chatRoomID];
	$query = "SELECT userID FROM callers WHERE chatRoomID = ? AND status = 1";
	$activeUsers = groupChatDataQuery($query, $params);

	if($activeUsers) {
		foreach($activeUsers as $user) {
			$activeUserID = $user->userID;

			// Set user status to 0 (signed off) IMMEDIATELY
			$params = [$activeUserID, $chatRoomID];
			$query = "UPDATE callers SET status = 0, sendToChat = NULL WHERE userID = ? AND chatRoomID = ?";
			groupChatDataQuery($query, $params);

			// Send signoff transaction to force disconnect NOW
			$params = [$activeUserID, $chatRoomID];
			$query = "INSERT INTO transactions VALUES (NULL, 'user', 'signoff', ?, ?, NULL, NULL, NULL, NULL, DEFAULT, DEFAULT)";
			groupChatDataQuery($query, $params);

			$usersSignedOff++;
		}
		echo "  ✓ Forced signoff of " . count($activeUsers) . " users\n";
	} else {
		echo "  - No active users in room\n";
	}

	// 3. Send room closure transaction
	$params = [$userID, $chatRoomID];
	$query = "INSERT INTO transactions VALUES (NULL, 'roomStatus', 'closed', ?, ?, NULL, NULL, '2', NULL, DEFAULT, DEFAULT)";
	groupChatDataQuery($query, $params);
	echo "  ✓ Closure message sent to all feeds\n\n";

	$roomsClosed++;
}

echo "==============================================\n";
echo "✓ EMERGENCY CLOSURE COMPLETE\n";
echo "==============================================\n";
echo "Rooms closed: $roomsClosed\n";
echo "Users signed off: $usersSignedOff\n";
echo "\n";
echo "RESULT:\n";
echo "- All rooms are now CLOSED (Open = 0)\n";
echo "- All users have been FORCE SIGNED OFF\n";
echo "- All message feeds are now sending 'room closed' messages\n";
echo "- NO ONE can join, post, or view messages\n";
echo "==============================================\n";

?>
