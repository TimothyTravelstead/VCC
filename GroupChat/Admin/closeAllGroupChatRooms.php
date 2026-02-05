<?php


$sapi_type = php_sapi_name();

if($sapi_type !== "cli") {
	echo "No Dice";
	exit;
}

// Include the mysql databsae location and login information
include('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

$params = [];	

$query = "DELETE FROM callers";
$result = dataQuery($query, $params);			

$query = "DELETE FROM groupChatStatus";
$result = dataQuery($query, $params);			

$query = "DELETE from groupChat";
$result = dataQuery($query, $params);			

$query = "DELETE from groupChatIM";
$result = dataQuery($query, $params);			

$query = "UPDATE groupChatRooms set Open = 0";
$result = dataQuery($query, $params);			
	
$query = "DELETE from transactions";
$result = dataQuery($query, $params);			




$query = "SELECT id from groupChatRooms";
$result = dataQuery($query, $params);


foreach($result as $item) {
	foreach($item as $key=>$chatRoomID) {


		$params = [$chatRoomID];	


		$query = "INSERT INTO transactions VALUES (
			null, 		
			'roomStatus' , 		
			'closed' , 		
			'admin' , 		
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

echo "OK";

?>