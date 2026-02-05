<?php
session_start();

require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');	

$userID = $_REQUEST["userID"];
$chatRoomID = $_REQUEST["chatRoomID"];
$Name = $_REQUEST["name"];
$minutes = $_REQUEST["minutes"];
$moderatorID = $_SESSION["UserID"];

// Release session lock after reading session data
session_write_close();


// Get uniqueCallerID for current user
$params = [$userID, $chatRoomID];
$query = "SELECT uniqueCallerID from groupChatStatus WHERE userID = ? and chatRoomID = ? ORDER By Date desc LIMIT 1";
$result = groupChatDataQuery($query, $params);
foreach($result as $item) {
	foreach($item as $key=>$value) {
		$uniqueCallerID = $value;
	}
}



// Get ipAddress for current user
$params = [$userID, $chatRoomID];
$query = "SELECT ipAddress from callers WHERE userID = ? and chatRoomID = ? ORDER By modified desc LIMIT 1";
$result = groupChatDataQuery($query, $params);
foreach($result as $item) {
	foreach($item as $key=>$value) {
		$ipAddress = $value;
	}
}


$params = [$userID, $uniqueCallerID, $chatRoomID, $minutes];
$query = "INSERT INTO groupchattimeouts (Date, userID, UniqueCallerID, chatRoomID, timeoutMinutes) VALUES (NOW(), ?, ?, ?, ?)";

// URGENT DEBUG: Log timeout creation
error_log("TIMEOUT CREATE DEBUG - UserID: $userID, ChatRoomID: $chatRoomID, Minutes: $minutes");
error_log("TIMEOUT CREATE DEBUG - UniqueCallerID: " . ($uniqueCallerID ? $uniqueCallerID : 'NULL'));

$result = groupChatDataQuery($query, $params);

if (is_array($result) && isset($result['error'])) {
    error_log("TIMEOUT CREATE ERROR: " . print_r($result, true));
} else {
    error_log("TIMEOUT CREATE DEBUG - Insert successful");
}


# Insert Transaction to let timeout user know.
$messageID = null;
$highlightMessage = null;
$deleteMessage = null;
$params = [$userID,$chatRoomID, $messageID, $minutes, $highlightMessage, $deleteMessage];
$query = "INSERT INTO Transactions VALUES (NULL, 'timeout' , 'create' , ? , ? , ? , ? , ? , ? , DEFAULT , DEFAULT)";
$result = groupChatDataQuery($query, $params);





echo "OK";

?>