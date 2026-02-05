<?php
session_start();

require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$userID = $_POST["userID"] ?? null;
$delivered = $_POST["text"] ?? null;
$messageID = $_POST["action"] ?? null;
$Text = $delivered;


/*
if($Text == 'to') {
	$params = [$messageID];
	$query = "Update groupChatIM Set toDelivered = '2' WHERE MessageNumber = ?";
	$result = groupChatDataQuery($query, $params);

	$params = [$imTo, $userID,$Text];


	$params = [$imTo, $userID, $chatRoomID, $messageID];
	$query = "INSERT INTO transactions VALUES (
		null, 		
		'IM' , 		
		? , 		
		? , 		
		? , 		
		?, 		
		null, 			
		'2', 		
		null, 		
		now(), 		
		null)";		// toDelivered information is carried in the transactions.highlightMessage field


} else if ($Text == 'from') {
	$params = [$messageID];

	$query = "Update groupChatIM Set fromDelivered = '2' WHERE MessageNumber = ?";
	$result = groupChatDataQuery($query, $params);

	$params = [$imTo, $userID, $chatRoomID, $messageID];
	$query = "INSERT INTO transactions VALUES (
			null, 		
			'IM' , 		
			? , 		
			? , 		
			? , 		
			?, 		
			null, 			
			null, 		
			'2', 		
			now(), 		
			null)";		// fromDelivered information is carried in the transactions.deleteMessage field

}
*/

// Since the code above is commented out, we don't need to execute any query
// $result = groupChatDataQuery($query, $params);


echo "OK";

?>
