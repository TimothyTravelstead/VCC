<?php
// Anti-caching headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include db_login.php FIRST to get session configuration (8-hour timeout)
require_once('/home/master/applications/dgqtkqjasj/private_html/db_login_GroupChat.php');

// Start session (inherits 8-hour timeout from db_login.php)
session_start();

// Read/write session variables
$Name = $_REQUEST["Name"] ?? null;
$Avatar = $_REQUEST["Avatar"] ?? null;
$chatRoomID = $_REQUEST["chatRoomID"] ?? null;
$userID = $_REQUEST["userID"] ?? ($_SESSION["UserID"] ?? null);
$ipAddress = $_SERVER['REMOTE_ADDR'];
$_SESSION["name"] = $Name;
$Moderator = $_REQUEST["Moderator"] ?? ($_SESSION["Moderator"] ?? null);
$roomOpen = null;

// For admins coming through the admin panel, ensure stealth mode
if (isset($_SESSION['ModeratorType']) && $_SESSION['ModeratorType'] === 'admin' && !isset($_REQUEST["Moderator"])) {
    $Moderator = 2;  // Force stealth mode for admin panel users
}

$_SESSION["Moderator"] = $Moderator;
$_SESSION["UserID"] = $userID;
$_SESSION['chatRoomID'] = $chatRoomID;

// Release session lock IMMEDIATELY before database operations
session_write_close();

if(!$Moderator) {
	$Moderator = 0;

	if($chatRoomID) {
		$params = [$chatRoomID];
		$query = "SELECT Open from groupChatRooms WHERE id = ? LIMIT 1";
		$result = groupChatDataQuery($query, $params);

		if($result) {
			foreach($result as $item) {
				foreach($item as $key=>$value) {
					$roomOpen = $value ?? null;
				}
			}
		}
	}

	if(!$roomOpen) {
		echo "Closed";
		return;
	}
}




//Block attempts to log in with a banned name
$name = strtolower($Name);
$bannedNames = explode("," , file_get_contents('./bannedNames.txt', true));
$bannedNamesArray = Array();
ksort($bannedNamesArray);


foreach ($bannedNames as $key => $value) {
	$bannedNamesArray[$value] = true;
	if (strpos($name, strtolower($value)) !== false) {
		echo 'Unknown Error';
		return;
	}
}

/*Chat Status Key
	0 = Signed Off
	1 = Caller Signed On

*/

// Check to See if user is blocked
$params = [$userID, $chatRoomID];
$query = "SELECT uniqueCallerID from groupChatStatus WHERE userID = ? and chatRoomID = ? ORDER By Date desc LIMIT 1";
$result = groupChatDataQuery($query, $params);

if($result) {
	foreach($result as $item) {
		foreach($item as $key=>$value) {
			$uniqueCallerID = $value ?? null;
		}
	}
}

$now = date("Y-m-d H:i:s");

$params = [$uniqueCallerID, $ipAddress, $now, $now];
$query = "SELECT blocked from blockedCallers WHERE (uniqueCallerID = ? OR uniqueCallerID = ?) AND blockStartTime <= ? AND blockEndTime >= ?";
$result = groupChatDataQuery($query, $params);

$blocked = 0;

if($result) {
	foreach($result as $item) {
		foreach($item as $key=>$value) {
			$blocked = $value;
		}
	}
}

if($blocked) {
	echo "Blocked";
	return;
}


//See if room is full
$params = [$chatRoomID];
$query = "SELECT Open from groupChatRooms  WHERE id = ?";
$result = groupChatDataQuery($query, $params);

$full = 0;

foreach($result as $item) {
	foreach($item as $key=>$value) {
		$full = $value;
	}
}
if($full === 2 && !$Moderator) {
	die("Full");
}

$query = "SELECT count(userID) as Users from callers WHERE chatRoomID = ? AND status = 1";
$result = groupChatDataQuery($query, $params);

$numberOfUsers = 0;

foreach($result as $item) {
	foreach($item as $key=>$value) {
		$numberOfUsers = $value;
	}
}


if($numberOfUsers > 50 && !$Moderator) {
	die("Full");
}



$params = [$userID, $Name, $Avatar, $chatRoomID, $Moderator, $Name, $Avatar, $Moderator];
$query = "INSERT INTO callers VALUES (null, ?, ?, ?, ?, null, 1, now(), ?, null, null)
          ON DUPLICATE KEY UPDATE name = ?, avatarID = ?, status = 1, moderator = ?, modified = now()";
$result = groupChatDataQuery($query, $params);


$params = [0, $userID, $chatRoomID];
$query = "update groupChatStatus set lastMessagePullTime = ? WHERE userID= ? and chatRoomID = ?";
$result = groupChatDataQuery($query, $params);


$params = [$userID, $chatRoomID];
$query = "update stats set SignedIn = 1 WHERE userID= ? and chatRoomID = ? and Date(stats.Date) = Date(now())";
$result = groupChatDataQuery($query, $params);


$params = [$userID , $chatRoomID];
$query = "INSERT INTO transactions VALUES (NULL, 'user' , 'signon' , ? , ? , NULL, NULL, NULL, NULL, DEFAULT, DEFAULT )";
$result = groupChatDataQuery($query, $params);

if($Moderator) {

	// Remove any old closure transactions before opening
	$params = [$chatRoomID];
	$query = "DELETE FROM transactions WHERE chatRoomID = ? and type = 'roomStatus' and action = 'closed'";
	$result = groupChatDataQuery($query, $params);

	// Send room open transaction
	$params = [$userID,$chatRoomID];
	$query = "INSERT INTO transactions VALUES (
		NULL,
		'roomStatus' ,
		'open' ,
		? ,
		? ,
		NULL,
		NULL,
		'2',
		NULL,
		DEFAULT,
		DEFAULT)";		// transactions.action carries roomStatus

	$result = groupChatDataQuery($query, $params);

	// Set room to open in database
	$params = [$chatRoomID];
	$query = "UPDATE groupChatRooms set open = 1 WHERE id = ?";
	$result = groupChatDataQuery($query, $params);
}


$Text = htmlspecialchars($Name)." has joined the Chat Room.";


$params = [$chatRoomID , $Text];
$query = "INSERT INTO Transactions VALUES (NULL, 'message' , 'create' , 'system' , ? , NULL , ?, NULL , NULL , DEFAULT, DEFAULT)";
$result = groupChatDataQuery($query, $params);


$params = [$userID,$chatRoomID];
$query = "SELECT id from Transactions WHERE action='signon' 
	AND UserID = ? AND chatRoomID = ? ORDER By id desc LIMIT 1";
$result = groupChatDataQuery($query, $params);

foreach($result as $item) {
	foreach($item as $key=>$value) {
		$newMessageNumber = $value;
	}
}


$params = [$newMessageNumber, $Text,$chatRoomID];
$query = "INSERT INTO groupChat VALUES (?, DEFAULT, 'system' , '1' , 'System' , 
			? , NULL , NULL , ? , NULL, NULL, DEFAULT)";
$result = groupChatDataQuery($query, $params);




echo "OK";

?>
