<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');
require_once '/home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html/vendor/autoload.php';

$userID = $_SESSION["UserID"];

// Release session lock after reading session data
session_write_close();

//Sign Off of Room
$params = [$userID];	

$query = "SELECT id as 'exists', chatRoomID from callers WHERE status > 0 and userID is not null and userID = ?";
$result = dataQuery($query, $params);

if($result) {
	foreach($result as $item) {
		foreach($item as $key=>$value) {
			switch($key) {
		
				case 'chatRoomID':
					$chatRoomID = $value;
					break;
				
				default:
					$exists = $value;
					break;
			}
	
			if($exists) {  //If signed on to specific room -- sign off
				$Text = $Name." has left the Chat Room.";
				$params = ["system",$Text,$chatRoomID];
				$query = "INSERT INTO groupChat VALUES (null, ? , '1' , 'System' , 
							? , null , null , null, ? , null, null, null)";
				$result = dataQuery($query, $params);


				$params = [$userID, $chatRoomID];
				$query = "UPDATE callers set status = 0 WHERE userID= ? and chatRoomID = ?";
				$result = dataQuery($query, $params);
			
				$params = [$userID,$chatRoomID, null, null,null, null];
				$query = "INSERT INTO transactions VALUES (null, 'user' , 'signoff' , ? , ? , ?, ?, ?, ?, null, null )";
				$result = dataQuery($query, $params);		
			}
		}
	}
}

sleep(2);


//Close Rooms with No Moderators;


$params = [];
$query = "SELECT id, open FROM groupChatRooms ORDER BY id";
$result = dataQuery($query, $params);

if($result) {
	foreach($result as $item) {
		$close = true;
		$open = false;
			
		foreach($item as $key=>$value) {
			switch($key) {
		
				case 'id':
					$chatRoomID = $value;
					break;
				
				case 'open':
					$open = $value;
					break;
				
				default:
					break;
			}
		}


		$params = [$chatRoomID];
		$query2 = "SELECT chatRoomID from callers WHERE chatRoomID = ? AND moderator = 1 and status > 0";
		$result2 = dataQuery($query2, $params);
		if($result2) {
			foreach($result2 as $thing) {
				foreach($thing as $label=>$data) {
					if(isset($data) && $data) {
						$close = false;
					}
				}
			}
		}
		
		if($close) {

			$params = [$chatRoomID];

			$query = "DELETE FROM callers WHERE ChatRoomID = ?";
			$result = dataQuery($query, $params);

			$query = "DELETE FROM groupChatStatus WHERE ChatRoomID = ?";
			$result = dataQuery($query, $params);

			$query = "DELETE from groupChat WHERE ChatRoomID = ?";
			$result = dataQuery($query, $params);

			$query = "UPDATE groupChatRooms set Open = 0 WHERE id = ?";
			$result = dataQuery($query, $params);

			$query = "DELETE from transactions WHERE ChatRoomID = ?";
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


echo "OK";



?>