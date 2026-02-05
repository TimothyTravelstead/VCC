<?php



require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Read session data before releasing lock
$currentUserID = $_SESSION["UserID"] ?? null;

// Capture session file paths BEFORE closing session for explicit cleanup (if logging out self)
$sessionId = session_id();
$sessionFile = session_save_path() . '/sess_' . $sessionId;
$customJsonFile = dirname(__FILE__) . '/../../private_html/session_' . $sessionId . '.json';

// Release session lock before database operations
session_write_close();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$VolunteerID = $_REQUEST["VolunteerID"];
	
// Include the mysql databsae location and login information



$queryLog = "INSERT INTO volunteerlog VALUES (null, \"".$VolunteerID."\", now(), 0 , null)";
$result = mysqli_query($connection,$queryLog);

$query = "Update volunteers SET LoggedOn = 0, Active1 = Null, Active2 = Null, OnCall = 0, ChatInvite = Null, Ringing = Null, TraineeID = NULL, Muted = 0, IncomingCallSid = NULL WHERE UserName = '".$VolunteerID."'";
$result = mysqli_query($connection,$query);

// Remove from CallControl table
$deleteControl = "DELETE FROM CallControl WHERE user_id = '".$VolunteerID."'";
mysqli_query($connection, $deleteControl);

$query7 = "Select count(username) from volunteers where loggedon = 1";
$result7 = mysqli_query($connection,$query7);

$query8 = "Delete from VolunteerIM WHERE imTo = '".$VolunteerID."' or imFrom = '".$VolunteerID."'";
$result8 = mysqli_query($connection,$query8);

$result_row = mysqli_fetch_row($result7);
$LastLogOff = $result_row[0];

if ($LastLogOff == 0) {
	$query9 = "DELETE from Chat";
	$result9 = mysqli_query($connection,$query9);
}
echo "OK";

mysqli_close($connection);

// If logging out self, explicitly delete session files
// (session_destroy() doesn't work after session_write_close())
if ($VolunteerID == $currentUserID && !empty($sessionId)) {
	if (file_exists($sessionFile)) {
		@unlink($sessionFile);
	}
	if (file_exists($customJsonFile)) {
		@unlink($customJsonFile);
	}
}
?>