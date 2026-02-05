<?php
session_start();

require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

// Read session data first
if(!$_SESSION["Moderator"]) {
	die();
}

$chatRoomID = $_REQUEST['chatRoomID'];
$block = $_REQUEST['block'];
$params = [$chatRoomID];
$userID = $_SESSION["UserID"] ?? "system";

// Release session lock after reading session data
session_write_close();


if($block) {
	$query = "UPDATE groupChatRooms SET Open = '2' WHERE id = ?";
} else {
	$query = "UPDATE groupChatRooms SET Open = '1' WHERE id = ?";
}

$result = groupChatDataQuery($query, $params);



$params = [$userID, $chatRoomID];
if($block) {
	$query = "INSERT INTO Transactions VALUES (NULL, 'roomStatus' , 'full' , ? , ? , NULL , NULL, NULL , NULL , DEFAULT, DEFAULT)";
} else {
	$query = "INSERT INTO Transactions VALUES (NULL, 'roomStatus' , 'reopen' , ? , ? , NULL , NULL, NULL , NULL , DEFAULT, DEFAULT)";

}

$result = groupChatDataQuery($query, $params);

echo "OK";

?>
