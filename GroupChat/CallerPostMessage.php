<?php
// Anti-caching headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Include db_login.php FIRST to get session configuration (8-hour timeout)
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

// Start session (inherits 8-hour timeout from db_login.php)
session_start();

// Release session lock IMMEDIATELY before database operations
session_write_close();

$messageID = $_POST["messageID"] ?? null;
$userID = $_POST["userID"] ?? null;
$Text = $_POST["Text"] ?? null;
$highlightMessage = $_POST["highlightMessage"] ?? 0;
$deleteMessage = $_POST["deleteMessage"] ?? 0;
$newMessageNumber = null;

$chatRoomID = $_POST["chatRoomID"] ?? null;

if(!$chatRoomID) {
	die("No ChatRoom Specified");
}

if(!$userID) {
	die("No UserID Specified");
}

// URGENT DEBUG - Check timeout with comprehensive logging
error_log("TIMEOUT CHECK - UserID: '$userID', ChatRoomID: '$chatRoomID'");

// First, let's see ALL timeout records for this user
$allTimeoutsQuery = "SELECT userID, chatRoomID, EndTimeout, NOW() as current_db_time FROM groupchattimeouts WHERE userID = '$userID' AND chatRoomID = '$chatRoomID'";
$allTimeoutsResult = groupChatDataQuery($allTimeoutsQuery, []);
error_log("TIMEOUT CHECK - ALL timeouts for user: " . print_r($allTimeoutsResult, true));

$timeoutQuery = "SELECT COUNT(*) as timeout_count FROM groupchattimeouts WHERE userID = '$userID' AND chatRoomID = '$chatRoomID' AND EndTimeout > NOW()";
error_log("TIMEOUT CHECK - Query: $timeoutQuery");

$timeoutResult = groupChatDataQuery($timeoutQuery, []);
error_log("TIMEOUT CHECK - Raw result: " . print_r($timeoutResult, true));

$timeoutCount = 0;
if (is_array($timeoutResult) && count($timeoutResult) > 0) {
    $timeoutCount = $timeoutResult[0]->timeout_count;
}
error_log("TIMEOUT CHECK - Timeout count: $timeoutCount");

if ($timeoutCount > 0) {
    error_log("TIMEOUT CHECK - ACTIVE TIMEOUT FOUND!");
    // Get the actual timeout end time
    $endTimeQuery = "SELECT EndTimeout FROM groupchattimeouts WHERE userID = '$userID' AND chatRoomID = '$chatRoomID' AND EndTimeout > NOW() ORDER BY EndTimeout DESC LIMIT 1";
    $endTimeResult = groupChatDataQuery($endTimeQuery, []);
    error_log("TIMEOUT CHECK - EndTime result: " . print_r($endTimeResult, true));
    
    if (is_array($endTimeResult) && count($endTimeResult) > 0) {
        $endTime = $endTimeResult[0]->EndTimeout;
        $minutesLeft = ceil((strtotime($endTime) - time()) / 60);
        error_log("TIMEOUT CHECK - BLOCKING MESSAGE! Minutes left: $minutesLeft");
        die((string)$minutesLeft);
    }
    error_log("TIMEOUT CHECK - BLOCKING MESSAGE! Using fallback timeout");
    die("999"); // Fallback timeout value
} else {
    error_log("TIMEOUT CHECK - NO ACTIVE TIMEOUT, ALLOWING MESSAGE");
}


// Get uniqueCallerID for current user
$uniqueCallerID = null;
$params = [$userID, $chatRoomID];
$query = "SELECT uniqueCallerID from groupChatStatus WHERE userID = ? and chatRoomID = ? ORDER By Date desc LIMIT 1";
$result = groupChatDataQuery($query, $params);
foreach($result as $item) {
	foreach($item as $key=>$value) {
		$uniqueCallerID = $value;
	}
}

// OLD timeout check removed - now handled at top of file



function minutesUntil($futureTimestamp) {
    // Convert the MySQL timestamp to a DateTime object
    $futureDate = new DateTime($futureTimestamp);

    // Get the current time as a DateTime object
    $now = new DateTime();

    // Calculate the difference
    $difference = $now->diff($futureDate);

    // Get the total difference in minutes
    $minutes = ($difference->days * 24 * 60) + ($difference->h * 60) + $difference->i;

    // If the future timestamp is in the past, return negative minutes
    if ($futureDate < $now) {
        $minutes = -$minutes;
    }

    $minutes = $minutes + 1;

    return (string)$minutes; // Return as a string
}


// OLD timeout check removed - now handled at top of file


if($highlightMessage === 'false') {
	$highlightMessage = 0;
} else if ($highlightMessage === 'true') {
	$highlightMessage = 1;
}

if($deleteMessage === 'false') {
	$deleteMessage = 0;
} else if ($deleteMessage === 'true') {
	$deleteMessage = 1;
}



if($messageID != '0') {
	$params = [$highlightMessage, $deleteMessage, $messageID];
	$query = "UPDATE groupChat set highlightMessage = ?, deleteMessage = ? WHERE MessageNumber = ?";

	$result = groupChatDataQuery($query, $params);


	$params = [$userID,$chatRoomID, $messageID, $Text, $highlightMessage, $deleteMessage];
	$query = "INSERT INTO Transactions VALUES (NULL, 'message' , 'update' , ? , ? , ? , ? , ? , ? , DEFAULT , DEFAULT)";

	$result = groupChatDataQuery($query, $params);


} elseif($messageID == '0') {
	
	// Check for duplicate message within last 10 seconds to prevent race conditions
	$recentDupeCheck = "SELECT id FROM Transactions WHERE UserID = ? AND chatRoomID = ? AND Message = ? AND action = 'create' AND created > DATE_SUB(NOW(), INTERVAL 10 SECOND)";
	$dupeResult = groupChatDataQuery($recentDupeCheck, [$userID, $chatRoomID, $Text]);
	
	if (is_array($dupeResult) && count($dupeResult) > 0) {
		// Duplicate message detected within 10 seconds - ignore this submission
		die("OK");
	}

	$params = [$userID,$chatRoomID, $messageID, $Text, $highlightMessage, $deleteMessage];
	$query = "INSERT INTO Transactions VALUES (NULL, 'message' , 'create' , ? , ? , ? , ? , ? , ? , DEFAULT , DEFAULT)";
	$result = groupChatDataQuery($query, $params);


	$params = [$userID,$chatRoomID, $Text];
	$query = "SELECT id from Transactions WHERE action='create' 
		AND UserID = ? AND chatRoomID = ? AND MessageNumber = '0' AND Message = ? 
		AND highlightMessage = '0' AND deleteMessage = '0'";
	$result = groupChatDataQuery($query, $params);

	if($result) {
		foreach($result as $item) {
			foreach($item as $key=>$value) {
				$newMessageNumber = $value;
			}
		}
	}



	$params = [$newMessageNumber, $userID , $userID , $chatRoomID , $Text , $chatRoomID];
	
	$query = "INSERT INTO groupChat VALUES (?, now(), ? , '1' , (select name from callers where userID = ? and chatRoomID = ? LIMIT 1) , 
				? , NULL , NULL , ? , NULL, NULL, DEFAULT)";

	$result = groupChatDataQuery($query, $params);


}

echo "OK";

?>
