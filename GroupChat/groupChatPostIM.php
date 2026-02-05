<?php
session_start();

require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$userID = $_POST["userID"];
$Text = $_POST["Text"];
$imTo = $_POST["imTo"];
$chatRoomID = $_POST["chatRoomID"];

$params = [$imTo, $userID, $Text];
$query = "INSERT into groupChatIM VALUES (now(), ? , ? , ?, null, 0, 0)";
$result = groupChatDataQuery($query, $params);

$params = [];
$query = "SELECT MessageNumber from groupChatIM order by MessageNumber desc LIMIT 1";
$result = groupChatDataQuery($query, $params);

foreach($result as $item) {
	foreach($item as $key=>$value) {
		$messageNumber = $value;
	}
}

$params = [$imTo, $userID, $chatRoomID, $messageNumber, $Text];
$query = "INSERT INTO transactions VALUES (
	NULL, 		
	'IM' , 		
	? , 		
	? , 		
	? , 		
	?, 		
	?, 			
	NULL, 		
	NULL, 		
	now(), 		
	NULL)";		// imTo is in transactions.action field; transactions IM data is chatRoom specific, so Moderator will only see it in the relevant chat room 
	
	
$result = groupChatDataQuery($query, $params);


echo "OK";

?>
