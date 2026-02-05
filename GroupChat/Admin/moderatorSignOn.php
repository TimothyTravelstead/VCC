<?php
session_start();

// Determine moderator level based on how they got here
if (isset($_SESSION['ModeratorType']) && $_SESSION['ModeratorType'] === 'admin') {
    $Moderator = 2;  // Stealth admin moderator
    $_SESSION['Moderator'] = 2;  // For backward compatibility
} else {
    $Moderator = 1;  // Visible monitor
    $_SESSION['Moderator'] = 1;  // For backward compatibility
}

// Include main VCC database connection FIRST (db_login_GroupChat.php depends on it)
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login.php');

// Then include GroupChat database connection
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

$Name = $_REQUEST["Name"];
$Avatar = "./Images/Avatars/penguin.png";
$chatRoomID = $_REQUEST["ChatRoomID"];
$_SESSION["name"] = $Name;
$userID = trim($_REQUEST["userID"]);
$_SESSION["UserID"] = $userID;  // Also set UserID in session to prevent session reset
$chatRoomID = $_REQUEST["ChatRoomID"];

// Release session lock after writing session data
session_write_close();

/*ChatStatus Key
	0 = Signed Off
	1 = Caller Signed On

*/



// NOTE: This file is called when moderator SELECTS a room from the dropdown
// The actual room sign-in happens in callerSignOn.php when the iframe loads
// Do NOT set room to open here - that happens when moderator actually enters the room

echo "OK";

?>
