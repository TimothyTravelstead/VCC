<?php

// Authentication check

require_once('../../private_html/db_login.php');
session_start();
if ($_SESSION['auth'] != 'yes') {
    http_response_code(401);
    die("Unauthorized");
}

// Release session lock after authentication check
session_write_close();

// Include database configuration if not already included

$Room = $_REQUEST["Room"];
$VolunteerID = $_REQUEST["VolunteerID"];

// First query: Get volunteer status and IM info
$query = "SELECT LoggedOn, Active?, IMSender, InstantMessage 
          FROM Volunteers 
          WHERE UserName = ?";
          
// Replace ? with Room number and add VolunteerID parameter
$query = str_replace("Active?", "Active" . $Room, $query);
$result = dataQuery($query, [$VolunteerID]);

if ($result === false) {
    http_response_code(500);
    die("Database error occurred");
}

// Second query: Clear IM data
$query2 = "UPDATE Volunteers 
           SET IMSender = '', InstantMessage = '' 
           WHERE UserName = ?";
$result2 = dataQuery($query2, [$VolunteerID]);

if (empty($result)) {
    echo "ERROR";
    exit;
}

// Set headers for XML response
header("Content-Type: text/xml");
header("Expires: Sun, 19 Nov 1978 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Start building XML response
echo "<?xml version=\"1.0\" encoding=\"utf-8\"?><responses><item>";

// Get the volunteer data
$volunteerData = $result[0];
$LoggedOn = $volunteerData->LoggedOn;
$CallerID = $volunteerData->{"Active" . $Room};
$IMSenderID = $volunteerData->IMSender;
$InstantMessage = $volunteerData->InstantMessage;

// Initialize sender info
$IMSender = "None";
$FirstName = "";
$LastName = "";

// If there's an IM sender, get their name
if (!empty($IMSenderID)) {
    $query3 = "SELECT FirstName, LastName 
               FROM Volunteers 
               WHERE UserName = ?";
    $result3 = dataQuery($query3, [$IMSenderID]);
    
    if ($result3 && !empty($result3)) {
        $senderData = $result3[0];
        $FirstName = $senderData->FirstName;
        $LastName = $senderData->LastName;
        
        if (!empty($FirstName)) {
            $IMSender = trim($FirstName . " " . $LastName);
        }
    }
}

// Determine logged off status
echo "<loggedoff>";
if ($LoggedOn == 0) {
    echo "LoggedOff";
} else if (!$CallerID) {
    echo "Open";
} else if ($CallerID == "End") {
    echo "End";
} else if ($CallerID == "Blocked") {
    echo "Blocked";
} else {
    echo "Active";
}
echo "</loggedoff>";

// Output IM information
// Note: Adding space to ensure non-empty values as per original code's comment
echo "<imsender>" . htmlspecialchars($IMSender) . "</imsender>";
echo "<imsenderid>" . htmlspecialchars($IMSenderID . " ") . "</imsenderid>";
echo "<instantmessage>" . htmlspecialchars($InstantMessage . " ") . "</instantmessage>";

echo "</item></responses>";
?>
