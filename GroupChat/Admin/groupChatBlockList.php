<?php


// Include the mysql databsae location and login information
include('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

$Users = array();
$SingleUser = array();
		
		
		
		
function getUserName($userID) {
	require_once('../../../private_html/db_login2.php');	
	$query = "SELECT FirstName, LastName FROM Volunteers WHERE UserName = ?";
	$params = [$userID];
	$result = dataQueryTwo($query, $params);
	$volunteerName = null;
	if ($result && !empty($result)) {
		$volunteerName = $result[0]->FirstName . " " . $result[0]->LastName;
	} else {
		error_log("DEBUG: No volunteer record found for userID: " . $userID);
		return null;
	}
	return $volunteerName;
}
		
				
$query = "Select id, name, blockEndTime, blockedBy, userID, message from blockedCallers ORDER BY blockEndTime desc";
$result = groupChatDataQuery($query, $params);

if($result) {
	foreach($result as $item) {
		foreach($item as $key=>$value) {
			$SingleUser[$key] = $value;
			if($key == "blockedBy") {
				$SingleUser['volunteerName'] = getUserName($value);
			}
		}
		array_push($Users, $SingleUser);
		unset($SingleUser);
		$SingleUser = array();
	}
}

echo json_encode($Users);

?>