<?php
session_start();
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$id = $_REQUEST["id"];
$type = $_REQUEST["type"];


if($type == "delete") {
	$params = [$id];
	$query = "DELETE FROM blockedCallers WHERE id = ?";
	$result = groupChatDataQuery($query, $params);
} elseif ($type = "update") {
	$params = [$id];
	$query = "Update blockedCallers set blockEndTime = '2035-12-31 23:59:59' WHERE id = ?";
	$result = groupChatDataQuery($query, $params);
}

echo "OK";

?>