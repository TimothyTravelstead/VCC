<?php
session_start();

require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');	

$chatRoomID = $_REQUEST["chatRoomID"];
$userID = $_SESSION["UserID"];

// Release session lock after reading session data
session_write_close();

$params = [$chatRoomID, $userID];
$query = "UPDATE callers set chatRoomID = ? WHERE userID= ? ";
$result = dataQuery($query, $params);


echo "OK";


?>