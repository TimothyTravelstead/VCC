<?php
// make session read-only
session_start();
session_write_close();

ob_implicit_flush();


// disable default disconnect checks
//ignore_user_abort(true);
set_time_limit(60);

// set headers for stream
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Access-Control-Allow-Origin: *");
header('Connection: keep-alive');
header('X-Accel-Buffering: no');//Nginx: unbuffered responses suitable for Comet and HTTP streaming applications

// Is this a new stream or an existing one?
$lastEventId = floatval(isset($_SERVER["HTTP_LAST_EVENT_ID"]) ? $_SERVER["HTTP_LAST_EVENT_ID"] : 0);

if ($lastEventId == 0) {
	$lastEventId = floatval(isset($_POST["lastEventId"]) ? $_POST["lastEventId"] : 0);
}



// Include the mysql database location and login information
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');



$userID = $_SESSION['UserID'];
$chatRoomID = $_GET["chatRoomID"];


$params = [$chatRoomID , $lastEventId];
$query = "SELECT transactions.id as id, transactions.type as type, transactions.action as action, transactions.UserID as userID, transactions.chatRoomID as chatRoomID ,
			transactions.messageNumber as messageNumber, transactions.Message as message, transactions.highlightMessage as highlightMessage, transactions.deleteMessage as deleteMessage, 
			transactions.created as created, transactions.modified as modified , callers.name as name, callers.avatarID as avatarID, callers.moderator as moderator, callers.sendToChat as sendToChat, (select count(id) from callers where status > 0) as numberOfLoggedOnUsers 
			FROM transactions LEFT JOIN callers ON (transactions.UserID = callers.userID and transactions.chatRoomID = callers.chatRoomID) 
			WHERE transactions.chatRoomID = ? AND (transactions.id > ?) ORDER BY transactions.id asc";
			
			
//		$queryDebug = interpolateQuery($query, $params);			
$result = groupChatDataQuery($query, $params);
if($result) {
	$num_rows = sizeof($result);	
} else {
	$num_rows = 0;
}

// If new messages, load data and send in event to volunteer's client
if($num_rows > 0) {
	$singleMessage = [];
//			$singleMessage['query'] = $queryDebug;
	foreach($result as $item) {
		$singleMessage['chatRoom'] = $chatRoomID;
		$type = $item->type;
		$singleMesssage['id'] = $item->id;					//transactions.action holds roomStatus for roomStatus messages

		switch($type) {
			case "User":
			case "message":
			case "toOneOnOneChat":
				break;
			case "roomStatus":
				$singleMessage['roomStatus'] = $item->action;					//transactions.action holds roomStatus for roomStatus messages
				break;		
			
			case "IM":
				$singleMessage['imTo'] = $item->action;     					//transactions.action field contains the imTo userID data
				$singleMessage['imFrom'] = $item->userID;						//transactions.UserID field contains the imFrom userid data
				$singleMessage['toDelivered'] = $item->highlightMessage;	
				$singleMessage['fromDelivered'] = $item->deleteMessage;	
				break;

			default:
				break;
			
		}
		foreach($item as $key => $value) {
			$singleMessage[$key] = $value;
		}
		echo "id: $lastEventId\n";
		echo "event: chatMessage\n";
		echo "data: ".json_encode($singleMessage)."\n\n";

		$lastEventId = $singleMessage['id'];
	}	
} else {
	echo "heartbeat\n";
}


// Release variables
unset($query);
unset($result);
unset($num_rows);
unset($result_row);
unset($singleMessage);
unset($params);

echo "heartbeat\n\n";


ob_implicit_flush();
ob_flush();
flush();


// 1 second sleep then carry on
sleep(1);
set_time_limit(60);
		
?>
