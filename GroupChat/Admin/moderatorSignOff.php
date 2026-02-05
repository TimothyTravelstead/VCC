<?php
session_start();

require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');	

$chatRoomID = $_REQUEST["ChatRoomID"];
$userID = $_SESSION["UserID"];

// Release session lock after reading session data
session_write_close();

/*ChatStatus Key
	0 = Signed Off
	1 = Caller Signed On

*/


//Sign Off of Room
$params = [$userID, $chatRoomID];	

$query = "SELECT COUNT(id) as 'exists' from callers where userID = ? AND chatRoomID = ?";
$result = dataQuery($query, $params);

foreach($result as $item) {
	foreach($item as $key=>$value) {
		if($value == 1) {  //If signed on to specific room -- sign off
			$Text = $Name." has left the Chat Room.";
			$params = ["system",$Text,$chatRoomID];
			$query = "INSERT INTO groupChat VALUES (null, ? , '1' , 'System' , 
						? , null , null , null, ? , null, null, null)";
			$result = dataQuery($query, $params);


			$params = [$userID, $chatRoomID];
			$query = "Delete from callers WHERE userID= ? and chatRoomID = ?";
			$result = dataQuery($query, $params);
			
			$params = [$userID,$chatRoomID, null, null,null, null];
			$query = "INSERT INTO transactions VALUES (null, 'user' , 'signoff' , ? , ? , ?, ?, ?, ?, null, null )";
			$result = dataQuery($query, $params);

			
		}
	}
}

if($userID) {
	$params = [$chatRoomID];	
	$query = "SELECT COUNT(id) as 'moderators' from callers where chatRoomID is not null and chatRoomID = ? and status = 1 and moderator = 1";
	$result = dataQuery($query, $params);

	foreach($result as $item) {
		foreach($item as $key=>$value) {
			if($value == 0) {  //If no moderators in the room, close the room
				$query = "DELETE FROM callers WHERE chatRoomID = ?";
				$result = dataQuery($query, $params);

				$query = "DELETE FROM groupChatStatus WHERE chatRoomID = ?";
				$result = dataQuery($query, $params);

				$query = "DELETE from groupChat WHERE chatRoomID = ?";
				$result = dataQuery($query, $params);

				$query = "UPDATE groupChatRooms set Open = 0 WHERE id = ?";
				$result = dataQuery($query, $params);
						
				$query = "DELETE from transactions WHERE chatRoomID = ?";
				$result = dataQuery($query, $params);			
			
			
				$params = [$userID,$chatRoomID];
				$query = "INSERT INTO transactions VALUES (
					null, 		
					'roomStatus' , 		
					'closed' , 		
					? , 		
					? , 		
					0, 		
					null, 			
					'2', 		
					null, 		
					DEFAULT, 		
					DEFAULT)";		// transactions.action carries roomStatus 

				$result = dataQuery($query, $params);

			}
		}
	}
}

echo "OK";

?>