<?php
// Include db_login.php FIRST to get session configuration (8-hour timeout)
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

// Start session (inherits 8-hour timeout from db_login.php)
session_start();

// Read session variables
$userID = $_SESSION['UserID'] ?? null;
$chatRoomID = $_SESSION['chatRoomID'] ?? ($_POST["chatRoomID"] ?? ($_GET["chatRoomID"] ?? null));

// Release session lock IMMEDIATELY before database operations
session_write_close();

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

	$params = [$chatRoomID];
	$query = "SELECT transactions.id as id, transactions.type as type, transactions.action as action, transactions.UserID as userID, transactions.chatRoomID as chatRoomID ,
				transactions.messageNumber as messageNumber, transactions.Message as message, transactions.highlightMessage as highlightMessage, transactions.deleteMessage as deleteMessage,
				transactions.created as created, transactions.modified as modified , callers.name as name, callers.avatarID as avatarID, callers.moderator as moderator, callers.sendToChat as sendToChat, (select count(id) from callers where status > 0) as numberOfLoggedOnUsers
				FROM transactions LEFT JOIN callers ON (transactions.UserID = callers.userID and transactions.chatRoomID = callers.chatRoomID)
				WHERE transactions.chatRoomID = ? AND (transactions.id > 0) ORDER BY transactions.id asc";

	$queryDebug = interpolateQuery($query, $params);
	$result = groupChatDataQuery($query, $params);
	$num_rows = $result ? sizeof($result) : 0;

// If new messages, load data and send in event to volunteer's client

	if($num_rows > 0) {
		$singleMessage = [];
//		$singleMessage['query'] = $queryDebug;
		foreach($result as $item) {
			$singleMessage['chatRoom'] = $chatRoomID;
			$type = $item->type;
			$singleMesssage['id'] = $item->id;					//transactions.action holds roomStatus for roomStatus messages

			switch($type) {
				case "User":
				case "message":
					break;
				case "roomStatus":
					$singleMessage['roomStatus'] = $item->action;					//transactions.action holds roomStatus for roomStatus messages
					break;

				case "IM":
					if($item->action === $userID || $item->userID === $userID ) {
						$singleMessage['imTo'] = $item->action;     					//transactions.action field contains the imTo userID data
						$singleMessage['imFrom'] = $item->userID;						//transactions.UserID field contains the imFrom userid data
						$singleMessage['toDelivered'] = $item->highlightMessage;
						$singleMessage['fromDelivered'] = $item->deleteMessage;
					}
					break;

				default:
					break;

			}
			foreach($item as $key => $value) {
				$singleMessage[$key] = $value;
			}
			echo json_encode($singleMessage);
		}
	}

?>
