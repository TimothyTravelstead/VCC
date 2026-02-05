<?php

require_once '../../private_html/training_chat_db_login.php';
session_start();
ini_set("session.gc.maxlifetime", "14400000");
session_cache_limiter('nocache');

if ($_SESSION['auth'] != 'yes') {
    die("Unauthorized");
}

// Enable error reporting to display errors in the browser
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Set timezone in database using dataQuery
$timezoneQuery = "SET time_zone = ?";
dataQuery($timezoneQuery, [$offset]);

$UserID = $_SESSION['UserID'] ?? null;
$trainer = ($_SESSION["trainer"] == 1) ? $_SESSION['UserID'] : $_SESSION["trainer"];
$trainee = $_SESSION["trainee"] ?? null;

function getRealIpAddr() {
    if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
    {
        $ip=$_SERVER['HTTP_CLIENT_IP'];
    }
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
    {
        $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    else
    {
        $ip=$_SERVER['REMOTE_ADDR'];
    }
    
    if(empty($ip)) {
    	$ip = 0;   	
    }
    return $ip;
}

// Fetch participant names from the database
function getUserNames($usernames) {
	include '../../private_html/db_login2.php';
    if (empty($usernames)) {
        return [];
    }
    $placeholders = implode(",", array_fill(0, count($usernames), "?"));
    $query = "SELECT UserName, FirstName, LastName FROM Volunteers WHERE UserName IN ($placeholders)";
    $results = dataQueryTwo($query, $usernames);

    $userNames = [];
    if ($results) {
        foreach ($results as $row) {
            $userNames[$row->UserName] = trim($row->FirstName . ' ' . $row->LastName);
        }
    }
    return $userNames;
}

$traineeList = $trainee ? explode(',', $trainee) : [];
$allUserNames = array_merge([$trainer], $traineeList, [$UserID]);

// Fetch names
$userNames = getUserNames($allUserNames);
$userNamesJSON = json_encode($userNames);

// Set currentUserFullName from database or session, with fallback
$currentUserFullName = $_SESSION['currentUserFullName'] ?? $userNames[$UserID] ?? 'Unknown User';
$_SESSION['currentUserFullName'] = $currentUserFullName;
$ipAddress = $_SESSION['ipAddress'] = getRealIpAddr();

// Release session lock after reading/writing session data
session_write_close();

$params = [$UserID, $currentUserFullName, $trainer, $ipAddress];	
$query = "INSERT INTO callers VALUES (null, ?, ? , null , ? , ? , 1 , now(), null, null, null)
    ON DUPLICATE KEY UPDATE status = 1, modified = now()";
$results = dataQuery($query, $params);





?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, private">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black">
	<meta NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
	<META HTTP-EQUIV="Expires" CONTENT="-1">
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no" />
	<title>LGBT Help Center Group Chat</title>
	<link type="text/css" rel="stylesheet" href="groupChat2.css">
	<link type="text/css" rel="stylesheet" href="nicEditPanel.css">
	<script src="nicEdit/nicEdit.js" type="text/javascript"></script>

	<script src="./groupChat.js" type="text/javascript"></script>
	<script src="../LibraryScripts/Ajax.js" type="text/javascript"></script>
	<script src="../LibraryScripts/domAndString.js" type="text/javascript"></script>
	<script src="../LibraryScripts/Dates.js" type="text/javascript"></script>
		
</head>
	<body id = "body">
			<div id='groupChatUpperChatArea' >
				<div id='groupChatMainWindow' class ="div">
				</div>
			</div>		
			<div id="groupChatTypingWrapper">
				<div id="groupChatTypingWindow"></div> 
				<div id='groupChatControlButtonArea' >
					<input type='submit' value="SUBMIT" id="groupChatSubmitButton"/>
					<div id="groupChatScroll">
						<input type='hidden' value="0" id="LastMessage"/>
						<input type='hidden' value="Tim" id="userName"/>
          </div>
				</div>
			</div>
		<input id='trainerID' type='hidden' value='<?php echo htmlspecialchars($trainer); ?>'>
		<input id='groupChatRoomID' type='hidden' value='<?php echo htmlspecialchars($trainer); ?>'>
		<input id='assignedTraineeIDs' type='hidden' value='<?php echo htmlspecialchars($trainee); ?>'>
		<input id='userNames' type='hidden' value='<?php echo htmlspecialchars($userNamesJSON); ?>'>
		<input id='volunteerID' type='hidden' value='<?php echo htmlspecialchars($UserID); ?>'>
		<input id='userID' type='hidden' value='<?php echo htmlspecialchars($UserID); ?>'>
		<input id='currentUserFullName' type='hidden' value='<?php echo htmlspecialchars($currentUserFullName); ?>'>
	</body>
</html>
