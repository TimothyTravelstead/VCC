<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include db_login.php FIRST to get session configuration (8-hour timeout)
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

// Start session (inherits 8-hour timeout from db_login.php)
session_start();

// Read session variables
$userID = $_REQUEST["userID"] ?? $_SESSION["UserID"];
$chatRoomID = $_REQUEST["chatRoomID"] ?? $_SESSION['chatRoomID'];

// Release session lock IMMEDIATELY before database operations
session_write_close();


$params = [$userID, $chatRoomID];
$query = "SELECT name, status from callers WHERE userID= ? and chatRoomID = ?";
$result = groupChatDataQuery($query, $params);


if(!$result) {
	$Name = null;
	$status = 0;
} else {

	foreach($result as $item) {
		foreach($item as $key=>$value) {
			if($key == "name") {
				$Name = $value;
			} else if ($key == "status") {
				$status = $value;
			}
		}
	}
}
if($status == 0) {
	$params = [$userID, $chatRoomID];	
	$query = "DELETE FROM callers WHERE userID = ? AND chatRoomID = ?";
	$result = groupChatDataQuery($query, $params);

	$query = "DELETE FROM groupChatStatus WHERE userID = ? AND chatRoomID = ?";
	$result = groupChatDataQuery($query, $params);
}


$Text = htmlspecialchars($Name)." has left the Chat Room.";


if($Name) {

	$params = [$Text,$chatRoomID];
	$query = "INSERT INTO groupChat VALUES (NULL, DEFAULT, 'system' , '1' , 'System' , 
				? , NULL , NULL , ? , NULL, NULL, DEFAULT)";
	$result = groupChatDataQuery($query, $params);

	$params = [$chatRoomID , $Text];
	$query = "INSERT INTO Transactions VALUES (NULL, 'message' , 'create' , 'system' , ? , NULL , ?, NULL , NULL , DEFAULT, DEFAULT)";
	$result = groupChatDataQuery($query, $params);

}


// Check if this user is a moderator and if they would be the last moderator before signing them off
$shouldCleanupRoom = false;
if($userID) {
	$params = [$userID, $chatRoomID];
	$query = "SELECT moderator FROM callers WHERE userID = ? AND chatRoomID = ? AND status = 1";
	$result = groupChatDataQuery($query, $params);

	$isCurrentUserModerator = false;
	if($result) {
		foreach($result as $item) {
			// Check for both visible moderators (1) and stealth admins (2)
			if($item->moderator == 1 || $item->moderator == 2) {
				$isCurrentUserModerator = true;
				break;
			}
		}
	}

	// If current user is a moderator, check if they would be the last one
	if($isCurrentUserModerator) {
		$params = [$chatRoomID];
		// Count both visible moderators (moderator=1) AND stealth admins (moderator=2)
		$query = "SELECT COUNT(id) as 'moderators' from callers where chatRoomID = ? and status = 1 and (moderator = 1 OR moderator = 2)";
		$result = groupChatDataQuery($query, $params);

		if($result) {
			foreach($result as $item) {
				foreach($item as $key=>$value) {
					if($value <= 1) {  // If 1 or fewer moderators (this user), room should be cleaned up
						$shouldCleanupRoom = true;
					}
				}
			}
		}
	}
}

$params = [$userID, $chatRoomID];
$query = "UPDATE callers set status = 0, sendToChat = NULL WHERE userID= ? and chatRoomID = ?";
$result = groupChatDataQuery($query, $params);


$params = [$userID , $chatRoomID];
$query = "INSERT INTO transactions VALUES (NULL, 'user' , 'signoff' , ? , ? , NULL, NULL, NULL, NULL, DEFAULT, DEFAULT )";
$result = groupChatDataQuery($query, $params);

echo "OK";

if($shouldCleanupRoom) {  //If this was the last moderator in the room, email the transcript and close the room
	$params = [$chatRoomID];

	// Send room closure message to all participants FIRST
	$params = [$userID,$chatRoomID];
	$query = "INSERT INTO transactions VALUES (
		NULL,
		'roomStatus' ,
		'closed' ,
		? ,
		? ,
		NULL,
		NULL,
		'2',
		NULL,
		DEFAULT,
		DEFAULT)";		// transactions.action carries roomStatus
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

	// Now proceed with room cleanup and email transcript
	$params = [$chatRoomID];
	require_once('Admin/includeFormatGroupChatEmail.php');

	$query = "DELETE FROM callers WHERE chatRoomID = ?";
	$result = groupChatDataQuery($query, $params);

	$query = "DELETE FROM groupChatStatus WHERE chatRoomID = ?";
	$result = groupChatDataQuery($query, $params);

	$query = "DELETE from groupChat WHERE chatRoomID = ?";
	$result = groupChatDataQuery($query, $params);

	$query = "DELETE from groupchattimeouts WHERE chatRoomID = ?";
	$result = groupChatDataQuery($query, $params);

	$query = "UPDATE groupChatRooms set Open = 0 WHERE id = ?";
	$result = groupChatDataQuery($query, $params);

	// Delete all transactions EXCEPT the closure message that participants need to see
	if($closureTransactionId) {
		$params = [$chatRoomID, $closureTransactionId];
		$query = "DELETE from transactions WHERE chatRoomID = ? AND id != ?";
		$result = groupChatDataQuery($query, $params);
	} else {
		// Fallback: delete all transactions if we couldn't get the closure ID
		$params = [$chatRoomID];
		$query = "DELETE from transactions WHERE chatRoomID = ?";
		$result = groupChatDataQuery($query, $params);
	}
}


if(!$_SESSION['Moderator']) {
	session_unset();
	session_destroy();
	session_write_close();
	setcookie(session_name(),'',0,'/');
}

?>
