<?php
// ═══════════════════════════════════════════════════════════════════════════════
// BULLETPROOF GROUP CHAT SSE FEED
// Designed to work reliably with Cloudways Varnish/Nginx proxy layer
// ═══════════════════════════════════════════════════════════════════════════════

// Anti-caching headers - MUST be first
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// make session read-only
session_start();
session_write_close();

// CRITICAL: Disable ALL output buffering layers
while (ob_get_level() > 0) {
	ob_end_clean();
}

// CRITICAL: Enable automatic script termination when client disconnects
ignore_user_abort(false);

// Short execution time - forces clean reconnects
set_time_limit(25);

// SSE headers - order matters for proxy compatibility
header("Content-Type: text/event-stream; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Connection: keep-alive");
header("X-Accel-Buffering: no");  // Nginx: disable buffering

// Is this a new stream or an existing one?
$lastEventId = floatval(isset($_SERVER["HTTP_LAST_EVENT_ID"]) ? $_SERVER["HTTP_LAST_EVENT_ID"] : 0);

if ($lastEventId == 0) {
	$lastEventId = floatval(isset($_POST["lastEventId"]) ? $_POST["lastEventId"] : 0);
}

// Include the mysql database location and login information
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

$userID = $_SESSION['UserID'];
$chatRoomID = $_GET["chatRoomID"];

// Initial padding for proxy buffer bypass (2KB)
echo ":" . str_repeat(" ", 2048) . "\n";

// Tell client to retry quickly (300ms) if connection drops
echo "retry: 300\n";
flush();

// ═══════════════════════════════════════════════════════════════════════════════
// MAIN LOOP - Short cycles with frequent heartbeats to keep proxy alive
// 100 iterations × 0.2 seconds = ~20 seconds max before clean restart
// ═══════════════════════════════════════════════════════════════════════════════
$maxIterations = 100;
$iteration = 0;
$heartbeatCounter = 0;

while($iteration < $maxIterations){
	$iteration++;
	$heartbeatCounter++;

	// Check connection status before doing work
	if (connection_aborted()) {
		break;
	}

	$params = [$chatRoomID , $lastEventId];
	$query = "SELECT transactions.id as id, transactions.type as type, transactions.action as action, transactions.UserID as userID, transactions.chatRoomID as chatRoomID ,
				transactions.messageNumber as messageNumber, transactions.Message as message, transactions.highlightMessage as highlightMessage, transactions.deleteMessage as deleteMessage,
				transactions.created as created, transactions.modified as modified , callers.name as name, callers.avatarID as avatarID, callers.moderator as moderator, callers.sendToChat as sendToChat, (select count(id) from callers where status > 0) as numberOfLoggedOnUsers
				FROM transactions LEFT JOIN callers ON (transactions.UserID = callers.userID and transactions.chatRoomID = callers.chatRoomID)
				WHERE transactions.chatRoomID = ? AND (transactions.id > ?) ORDER BY transactions.id asc";

	$result = groupChatDataQuery($query, $params);
	$num_rows = $result ? sizeof($result) : 0;

	// If new messages, send immediately
	if($num_rows > 0) {
		$singleMessage = [];
		foreach($result as $item) {
			$singleMessage['chatRoom'] = $chatRoomID;
			$type = $item->type;
			$singleMessage['id'] = $item->id;

			switch($type) {
				case "User":
				case "message":
				case "toOneOnOneChat":
					break;
				case "roomStatus":
					$singleMessage['roomStatus'] = $item->action;
					break;

				case "IM":
					$singleMessage['imTo'] = $item->action;
					$singleMessage['imFrom'] = $item->userID;
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

			if(!$singleMessage['MessageNumber']) {
				$singleMessage['MessageNumber'] = $lastEventId * 2;
			}

			// Send message with timestamp for client-side monitoring
			$singleMessage['_serverTime'] = round(microtime(true) * 1000);

			echo "id: " . $singleMessage['id'] . "\n";
			echo "event: chatMessage\n";
			echo "data: ".json_encode($singleMessage)."\n\n";
			flush();

			$lastEventId = $singleMessage['id'];
		}
		$heartbeatCounter = 0; // Reset heartbeat counter after sending data
	} else {
		// AGGRESSIVE HEARTBEAT - Send every iteration to keep proxy connection alive
		// Include timestamp so client can detect stale connections
		echo ": hb " . round(microtime(true) * 1000) . "\n\n";
		flush();
	}

	// Release variables for memory
	unset($query);
	unset($result);
	unset($num_rows);
	unset($singleMessage);
	unset($params);

	// Poll frequently - 200ms between checks
	usleep(200000);
}

// Clean exit - client will auto-reconnect
?>
