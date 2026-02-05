<?php
session_start();
include('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$page = $_POST["page"];

$params = [$page];	
$query = "SELECT chatRoomID from groupChatRooms WHERE URL = ?";
$result = groupChatDataQuery($query, $params);

$chatRoomID = false;				

if ($result) {
	foreach($result as $item) {
		foreach($item as $key=>$value) {
			if($key == "id") {
				$chatRoomID = $value;				
			}
		}
	}
}

echo $chatRoomID;

?>	

