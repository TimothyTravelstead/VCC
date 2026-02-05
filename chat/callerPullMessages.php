<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours
require_once('../../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Release session lock immediately (file doesn't use session data)
session_write_close();

header('Content-Type: application/json; charset=utf-8');

// Disable error display for JSON output
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

$CallerID = $_REQUEST["CallerID"] ?? null;
$LastMessage = $_REQUEST["LastMessage"] ?? null;
if(!$LastMessage) {
	$LastMessage = 0;
}

function encodeURIComponent($str) {
    $revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
    return strtr(rawurlencode($str), $revert);
}


$query = "SELECT Status, Name, Text, MessageNumber, callerDelivered, volunteerDelivered FROM Chat WHERE CallerID = ? AND MessageNumber > ? AND (volunteerDelivered < 2 OR callerDelivered < 2) ORDER BY MessageNumber ASC";
$result = dataQuery($query, [$CallerID, $LastMessage]);
if(!$result) {
	echo json_encode([]);
	exit;
}


//Define the Arrays to hold the results and fill the arrays with the results
	$Status = 				Array();
	$Name = 				Array();
	$Text = 				Array();
	$MessageNumber =		Array();
	$callerDelivered =		Array();
	$volunteerDelivered =	Array();
	$Count = 1;
	$allMessages =			Array();
	
	if(count($result) == 0) {
		echo json_encode([]);
		exit;
	}
	
	foreach($result as $result_row) {
		$Status[$Count] =				$result_row->Status;
		$Name[$Count] =			 		$result_row->Name;
		if (!$Name[$Count]) {
			$Name[$Count] = "CHAT ENDED";
		}
		$Text[$Count] = 				$result_row->Text;
		$MessageNumber[$Count] = 		$result_row->MessageNumber;
		$callerDelivered[$Count] = 		$result_row->callerDelivered;
		$volunteerDelivered[$Count] = 	$result_row->volunteerDelivered;
		$Count = $Count + 1;
	}
	
	$FoundCount = $Count;
	$Count = 1;



	while ($Count < $FoundCount) {
		$singleMessage['id'] = 				$MessageNumber[$Count];
		$singleMessage['name'] = 			$Name[$Count];
		$singleMessage['text'] = 			$Text[$Count];	
		$singleMessage['status'] = 			$Status[$Count];	
		$singleMessage['callerDelivered'] = $callerDelivered[$Count];	
		$singleMessage['volunteerDelivered'] = $volunteerDelivered[$Count];	

		$_SESSION["MessageNumber"] = $MessageNumber[$Count];

		array_push($allMessages, $singleMessage);
		$Count += 1;
	}	
	
	echo json_encode($allMessages);
	
?>