<?php
// Include db_login.php FIRST to get session configuration (8-hour timeout)
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

// Start session (inherits 8-hour timeout from db_login.php)
session_start();

// Read session variables
$userID = $_SESSION['UserID'] ?? null;

// Release session lock IMMEDIATELY before database operations
session_write_close();

// Get POST parameters
$chatRoomID = $_POST["chatRoomID"] ?? null;
$lastMessagePullTime = date("Y-m-d H:i:s", mktime(12, 0, 0, 1, 1, 1900));

// Set Timezone for Date Information
define('TIMEZONE', 'America/Los_Angeles');
date_default_timezone_set(TIMEZONE);
$now = new DateTime();
$mins = $now->getOffset() / 60;
$sgn = ($mins < 0 ? -1 : 1);
$mins = abs($mins);
$hrs = floor($mins / 60);
$mins -= $hrs * 60;
$offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);

$params = [$offset];
$query = "SET time_zone=?";
$result = groupChatDataQuery($query, $params);

$params = [$lastMessagePullTime, $userID, $chatRoomID];
$query="Update groupChatStatus set lastMessagePullTime = ? WHERE userID = ? and chatRoomID = ?";
$result = groupChatDataQuery($query, $params);

echo "none";


/*
	$params = [$chatRoomID, $chatRoomID, $lastMessagePullTime];

	$query="SELECT Status as status, Name as name, (SELECT avatarID from callers WHERE userID = chat.userID and chatRoomID = ?) as avatarID , Text as text, MessageNumber, userID, callerDelivered, volunteerDelivered, chatTime as time, Modified, highlightMessage, deleteMessage from Chat WHERE chatRoomID = ? AND Modified > ? ORDER By MessageNumber asc"; 
	$result = groupChatDataQuery($query, $params);

	$num_rows = sizeof($result);

	$singleMessage = array();
	$messages = array();

	// If new messages, load data and send in event to volunteer's client
	if($num_rows > 0) {
		foreach($result as $item) {
			foreach($item as $key=>$value) {

				switch($key) {
			
					case 'MessageNumber':
						$singleMessage['id'] = 			$value;
						break;

					case 'userID':
						$singleMessage['userID'] = $value;	
						break;
						
					case 'Modified':				
						$singleMessage[$key] = $value;
						break;
			
					default:
						$singleMessage[$key] = $value;
						break;		
				}
			}

			array_push($messages, $singleMessage);

			if($singleMessage['Modified'] > $lastMessagePullTime) {
				$lastMessagePullTime = $singleMessage['Modified'];
			}
			unset($singleMessage);
			$singleMessage = array();
		}	

		echo json_encode($messages);
		flush();
		
		$params = [$lastMessagePullTime, $userID, $chatRoomID];
		$query="Update callers set lastPullTime = ? WHERE userID = ? and chatRoomID = ?";
		$result = groupChatDataQuery($query, $params);

	} else {
		echo "none";
	}		
*/

?>
