<?php

// Include the mysql databsae location and login information
require_once('../../private_html/db_login.php');
include 'firstChatAvailableLevel.php';
include 'secondChatAvailableLevel.php';


// Timezone is already set in db_login.php
// No need to set it again here



$open = "";

$findLoggedon = " WHERE ((LoggedOn = 1) OR 
				(LoggedOn = 4 and Muted = 0) OR 
				(LoggedOn = 6 and Muted = 0))";
$findEligible = $findLoggedon." AND Ringing is null AND OnCall = 0 AND (((Active1 is null OR Active2 is null) and OneChatOnly is null) or (Active1 is null AND Active2 is null and OneChatOnly = '1')) AND ChatInvite is null";
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
	
		

	$query3 = "SELECT count(username) as cnt FROM volunteers".$findEligible;
	$result3 = dataQuery($query3);
	$eligible = (is_array($result3) && !empty($result3)) ? $result3[0]->cnt : 0;
	

	$query4 = "SELECT count(username) as cnt FROM volunteers".$findChatting;
	$result4 = dataQuery($query4);
	$chatting = (is_array($result4) && !empty($result4)) ? $result4[0]->cnt : 0;
	
	
	$query5 = "SELECT count(username) as cnt FROM volunteers".$findAvailableChatting;
	$result5 = dataQuery($query5);
	$availableChatting = (is_array($result5) && !empty($result5)) ? $result5[0]->cnt : 0;

	$query6 = "SELECT count(UserName) as cnt FROM volunteers".$findChatOnly;
	$result6 = dataQuery($query6);
	$chatOnly = (is_array($result6) && !empty($result6)) ? $result6[0]->cnt : 0;
	
	
	if ($loggedon < $chatAvailableLevel1) {
		$potential = 0;
	} elseif ($loggedon < $chatAvailableLevel2) {
		$potential = 1; 
	} else {
		$potential = 2;
	}




	if (!$open) {
		$status = "closed";
	} else if ($chatOnly > 0) {
		$status = "available";
	} else if ($loggedon < $chatAvailableLevel1) { 
		$status = "busy";
	} else if ($chatting < $potential) {
		if ($eligible > 0) {
			$status = "available";	
		} else {	
			$status = "busy";
		}	
	} else if ($chatting == $potential) {
		if ($availableChatting > 0) {
			$status = "available";	
		} else {
			$status = "busy";
		}
	} else {
		$status = "busy";
	}

	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
	
echo $status;

?>