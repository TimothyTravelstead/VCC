<?php
session_start();

require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

$userID = $_SESSION["UserID"];

// Release session lock after reading session data
session_write_close();

$params = [$userID];	

$query = "SELECT chatRoomID, name from callers where userID = ?";
$result = dataQuery($query, $params);

if(!$result) {
	$rooms = false;
} else {
	$rooms = Array();
	foreach($result as $item) {
		foreach($item as $key=>$value) {
			if($key == 'name') {
				$Name = $value;
			}
			
			if($key == 'chatRoomID') {
				$chatRoomID = $value;
			}
		}
		
		$Text = $Name." has left the Chat Room.";
		$params = ["system",$Text,$chatRoomID];
		$query = "INSERT INTO groupChat VALUES (null, ? , '1' , 'System' , 
					? , null , null , null, ? , null, null, DEFAULT)";
		$result = dataQuery($query, $params);


		$params = [$userID, $chatRoomID];
		$query = "UPDATE callers set status = 0 WHERE userID= ? and chatRoomID = ?";
		$result = dataQuery($query, $params);
		
		$params = [$userID,$chatRoomID, null, null,null, null];
		$query = "INSERT INTO transactions VALUES (null, 'user' , 'signoff' , ? , ? , ?, ?, ?, ? , DEFAULT, DEFAULT)";
		$result = dataQuery($query, $params);

	}
}

?>