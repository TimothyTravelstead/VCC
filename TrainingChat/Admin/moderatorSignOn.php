<?php

require_once('../db_login.php');
session_start();
ini_set("session.gc.maxlifetime", "14400000");
session_cache_limiter('nocache');
$_SESSION['Moderator'] = true;



$Name = $_REQUEST["Name"];
$Avatar = "./Images/Avatars/penguin.png";
$chatRoomID = $_REQUEST["ChatRoomID"];
$_SESSION["name"] = $Name;

$Moderator = 1;
$userID = trim($_REQUEST["userID"]);
$chatRoomID = $_REQUEST["ChatRoomID"];

// Release session lock after writing session data
session_write_close();

/*ChatStatus Key
	0 = Signed Off
	1 = Caller Signed On

*/

    
    
//Remove Close Room Transaction if it exists
    $params = [$chatRoomID];
    $query = "DELETE FROM transactions WHERE chatRoomID = ? and type = 'roomStatus' and action = 'closed'";
    $result = dataQuery($query, $params);
    
    

//See if already signed ON
$params = [$userID, $chatRoomID];	
$query = "SELECT COUNT(id) as 'exists' from callers where userID = ? AND chatRoomID = ?";
$result = dataQuery($query, $params);

foreach($result as $item) {
	foreach($item as $key=>$value) {
		if($value == 0) {  //If not signed on to specific room -- sign on to room
			$params = [$userID, $chatRoomID ];
			$query = "INSERT INTO callers VALUES (null, ?, null , null , ? , null , 0, now(), 1 , null, null)
						ON DUPLICATE KEY UPDATE status = 0, modified = now(), moderator = 1";
			$result = dataQuery($query, $params);			
        }
	}
}

echo "OK";

?>
