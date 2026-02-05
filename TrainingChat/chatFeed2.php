<?php
// make session read-only

require_once('../../private_html/training_chat_db_login.php');
session_start();
session_cache_limiter('nocache');
session_write_close();

ob_implicit_flush();


// disable default disconnect checks
//ignore_user_abort(true);
// Set long timeout to keep connection alive during training calls (similar to vccFeed.php)
// Training sessions can last several hours, so we need to keep the EventSource connection alive
set_time_limit(14400); // 4 hours

// set headers for stream
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Access-Control-Allow-Origin: *");
header('Connection: keep-alive');
header('X-Accel-Buffering: no');//Nginx: unbuffered responses suitable for Comet and HTTP streaming applications

// Is this a new stream or an existing one?
$lastEventId = floatval($_SERVER["HTTP_LAST_EVENT_ID"] ?? $_GET["lastEventId"] ?? 0);

if ($lastEventId == 0) {
	$lastEventId = floatval(isset($_POST["lastEventId"]) ? $_POST["lastEventId"] : 0);
}


// Include the mysql database location and login information



$userID = $_SESSION['UserID'];	
$chatRoomID = $_GET["chatRoomID"];


echo ":" . str_repeat(" ", 2048) . "\n"; // 2 kB padding for IE
echo "retry: 500\n";


while(true){

		$params = [$chatRoomID , $lastEventId];
		$query = "SELECT transactions.id as id, transactions.type as type, transactions.action as action, transactions.UserID as userID, transactions.chatRoomID as chatRoomID ,
					transactions.messageNumber as messageNumber, transactions.Message as message, transactions.highlightMessage as highlightMessage, transactions.deleteMessage as deleteMessage, 
					transactions.created as created, transactions.modified as modified , callers.name as name, callers.avatarID as avatarID, callers.moderator as moderator, callers.sendToChat as sendToChat, (select count(id) from callers where status > 0) as numberOfLoggedOnUsers 
					FROM transactions LEFT JOIN callers ON (transactions.UserID = callers.userID and transactions.chatRoomID = callers.chatRoomID) 
					WHERE transactions.chatRoomID = ? AND (transactions.id > ?) ORDER BY transactions.id asc";
					
					
//		$queryDebug = interpolateQuery($query, $params);			
		$result = chatDataQuery($query, $params);
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

					case "timeout":
						$singleMessage['minutes'] = $item->message;	
						break;

					default:
						break;
					
				}
				foreach($item as $key => $value) {
					$singleMessage[$key] = $value;
				}
								
				if(!isset($singleMessage['messageNumber'])) {
					$singleMessage['messageNumber'] = $lastEventId * 2;
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

}		
		
		
?>
