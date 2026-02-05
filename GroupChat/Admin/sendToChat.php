<?php
session_start();

require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');
require_once('../../chat/firstChatAvailableLevel.php');
require_once('../../chat/secondChatAvailableLevel.php');	

$userID = $_REQUEST["userID"];
$chatRoomID = $_REQUEST["chatRoomID"];
$Name = $_REQUEST["name"];
$moderatorID = $_SESSION["UserID"];
$groupChatTransferMessage = $message = $_REQUEST["message"]."---".$userID;
$_SESSION['groupChatTransferMessage'] = $message;



$params = [$message , $userID];
$query = "UPDATE callers set sendToChat = ? WHERE userID= ? ";
$result = dataQuery($query, $params);



$params = [$userID, $chatRoomID , $groupChatTransferMessage];
$query = "INSERT INTO Transactions VALUES (NULL, 'user' , 'toOneOnOneChat' , ? , ? , NULL , ?, NULL , NULL , NULL, NULL)";
$result = dataQuery($query, $params);




/* Chat Statuses:

	1 = No Such Status
	2 = Normal message
	3 = Caller Ended Chat
	4 = Caller Closed Chat window without ending
	5 = Volunteer Ended Chat
*/


$referringPage = $_SESSION['referringPage'];
$ipAddress = json_encode("IP ADDRESS: ".$_SERVER['REMOTE_ADDR']);

// Release session lock after reading session data
session_write_close();

// Timezone is already set in db_login_GroupChat.php

	$browserSupport = null;
	$browser = array();

	$browser['browser'] = null;
	$browser['version'] = null;
	$browser['platform_description'] = null;
	$browser['platform_version'] = null;
	$browser['browser_name_pattern'] = null;
	

	$params = [$userID];
	$query = "SELECT * from chatStatus WHERE userID = ? ORDER BY id desc LIMIT 1";
	$result = dataQuery($query, $params);
	$num_rows = sizeof($result);

	// If new messages, load data and send in event to volunteer's client
	if($num_rows > 0) {
		$singleMessage = [];
		foreach($result as $item) {
			$browser['browser'] = $item->callerBrowser;
			$browser['version'] = $item->callerBrowserVersion;
			$browser['platform_description'] = $item->callerComputerType;
			$browser['platform_version'] = $item->callerOS;
			$browser['browser_name_pattern'] = "None";
		}
	} else {
		echo "No Such UserID";
		die();
	}


	if($browser['browser'] == "Internet Explorer" && $browser['version'] < 9) {
		$browserSupport = "Not";
	}
	
	if($browser['browser'] == "Opera") {
		$browserSupport = "Not";
	}
	
	if($browser['browser'] == "Chrome" && $browser['version'] < 30) {
		$browserSupport = "Not";
	}

	if($browser['browser'] == "Safari" && $browser['version'] < 5) {
		$browserSupport = "Not";
	}

	if($browser['browser'] == "Firefox" && $browser['version'] < 3.6) {
		$browserSupport = "Not";
	}

	if($browser['browser'] == "Android" && $browser['version'] < 4) {
		$browserSupport = "Not";
	}

	if($browser['browser'] != "Firefox" && $browser['browser'] != "Chrome" && $browser['browser'] != "Safari" && $browser['browser'] != "Internet Explorer" && $browser['browser'] != "Android" && $browser['browser'] != "Edge") {
		$browserSupport = "Not";
	}




	if($browserSupport != null) {
		echo "The chatter's browser, ".$browser['browser']." ".$browser['version']." is not supported by one-to-one chat because it is too old a version.";
		die();
	}



global $key;
global $findLoggedon;
global $findEligible;
global $findChatting;
global $findAvailableChatting;
global $status;
global $open;
global $potential;


$key = $_REQUEST["userID"];


$findLoggedon = " WHERE ((LoggedOn = 1) OR 
				(LoggedOn = 4 and Muted = 0) OR 
				(LoggedOn = 6 and Muted = 0))";
$findEligible = $findLoggedon." AND Ringing is null AND OnCall = 0 AND (((Active1 is null OR Active2 is null) and OneChatOnly is null) or (Active1 is null AND Active2 is null and OneChatOnly = '1')) AND ChatInvite is null AND DESK != 2"; // Desk is used for CallerType and 2 = Calls Only
$findChatting = $findLoggedon." AND OnCall = 0 AND ((Active1 is not null) OR (Active2 is not null) OR ChatInvite is not null)";
$findAvailableChatting = $findLoggedon." AND OnCall = 0 AND ((Active1 is not null AND Active2 is null AND OneChatOnly is null) OR (Active2 is not null AND Active1 is null AND OneChatOnly is null)) AND ChatInvite is null";

$findChatOnly = $findEligible." AND ChatOnly = 1";

//Determine if Hotline is Open
	$query = "SELECT DayofWeek FROM Hours WHERE (DayofWeek = DATE_FORMAT(curdate(),'%w') + 1) AND (start < curtime() AND end > curtime())";
	$result = dataQuery($query);
	if (is_array($result) && !empty($result)) {
		foreach ($result as $row) {
			$open = $row->DayofWeek;
		}
	}

//Chat Invite Counts Routine
	$query2= "SELECT count(username) as cnt FROM volunteers".$findLoggedon;
	$result2 = dataQuery($query2);
	$loggedon = (is_array($result2) && !empty($result2)) ? $result2[0]->cnt : 0;
		
	if ($loggedon < $chatAvailableLevel1) {
		$potential = 0;
	} elseif ($loggedon < $chatAvailableLevel2) {
		$potential = 1; 
	} else {
		$potential = 2;
	}
	
	$query3 = "SELECT count(username) as cnt FROM volunteers".$findEligible;
	$result3 = dataQuery($query3);
	$eligible = (is_array($result3) && !empty($result3)) ? $result3[0]->cnt : 0;
	
	$query4 = "SELECT count(username) as cnt FROM volunteers".$findChatting;
	$result4 = dataQuery($query4);
	$chatting = (is_array($result4) && !empty($result4)) ? $result4[0]->cnt : 0;
	
	
	$query5 = "SELECT count(username) as cnt FROM volunteers".$findAvailableChatting;
	$result5 = dataQuery($query5);
	$availableChatting = (is_array($result5) && !empty($result5)) ? $result5[0]->cnt : 0;
	
	$query6 = "SELECT count(username) as cnt FROM volunteers".$findChatOnly;
	$result6 = dataQuery($query6);
	$chatOnly = (is_array($result6) && !empty($result6)) ? $result6[0]->cnt : 0;

	
	
if (!$open) {
	$status = "closed";	
} else if ($chatOnly > 0) {
	//Invite All Eligible Chat Only Volunteers
		$status = "allChatOnly";
		$query7A = "UPDATE Volunteers SET Volunteers.InstantMessage = ? , Volunteers.ChatInvite = ?".$findChatOnly;
		$result7A = dataQuery($query7A, [$groupChatTransferMessage, $key]);	
		$status = "OK";
} else if ($loggedon < $chatAvailableLevel1) {
	$status = "busy";
} else if ($chatting < $potential) {
	if ($eligible > 0) {
		//Invite All Eligible
			$status = "allEligible";
			$query6 = "UPDATE Volunteers SET Volunteers.InstantMessage = ? , Volunteers.ChatInvite = ?".$findEligible;
			$result6 = dataQuery($query6, [$groupChatTransferMessage, $key]);	
			$status = "OK";
	} else {	
		$status = "busy";
	}	
} else if ($chatting == $potential) {
	if ($availableChatting > 0) {
		//Invite All Available Chatting
			$status = "allAvailableChatting";
			$query7 = "UPDATE Volunteers SET Volunteers.InstantMessage = ? , Volunteers.ChatInvite = ?".$findAvailableChatting;
			$result7 = dataQuery($query7, [$groupChatTransferMessage, $key]);	
			$status = "OK";
	} else {
		$status = "busy";
	}
} else {
	$status = "busy";
}
	
	
	
echo $status;

?>