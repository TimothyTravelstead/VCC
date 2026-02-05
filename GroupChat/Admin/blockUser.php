<?php
session_start();

require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');	

$userID = $_REQUEST["userID"];
$chatRoomID = $_REQUEST["chatRoomID"];
$Name = $_REQUEST["name"];
$endBlockTime = $_REQUEST["endDate"];
$blockType = $_REQUEST["type"];
$moderatorID = $_SESSION["UserID"];
$message = $_REQUEST["message"];

// Release session lock after reading session data
session_write_close();

$endDate = $endBlockTime." 23:59:59";

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



$Text = $Name." has left the Chat Room.";
$params = ["system",$Text,$chatRoomID];
$query = "INSERT INTO groupChat VALUES (null, ? , '1' , 'System' , 
			? , null , null , null, ? , null, null, now())";
$result = groupChatDataQuery($query, $params);


$params = [$chatRoomID , $Text];
$query = "INSERT INTO Transactions VALUES (NULL, 'message' , 'create' , 'system' , ? , NULL , ?, NULL , NULL , DEFAULT, DEFAULT)";
$result = groupChatDataQuery($query, $params);



$params = [$userID , $chatRoomID];
$query = "INSERT INTO transactions VALUES (NULL, 'user' , 'signoff' , ? , ? , NULL, NULL, NULL, NULL, DEFAULT, DEFAULT )";
$result = groupChatDataQuery($query, $params);



$params = [$userID];
$query = "UPDATE callers set status = 0 WHERE userID= ? ";
$result = groupChatDataQuery($query, $params);


if($blockType !== 'ip') {
	$params = [$uniqueCallerID, $endDate, $moderatorID, $Name , $userID, $message];
} else {
	$params = [$ipAddress, $endDate, $moderatorID, $Name , $userID, $message];
}

$query = "INSERT INTO blockedCallers VALUES (DEFAULT, ? , 1 , DEFAULT, null, now(), ?, ?,?,?,?)";
$result = groupChatDataQuery($query, $params);

echo "OK";

?>