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

// Include database configuration

// Query to get volunteer status data
$query = "SELECT 
    UserID, 
    firstname, 
    lastname, 
    shift, 
    Volunteers.office, 
    Volunteers.desk, 
    oncall, 
    Active1, 
    Active2, 
    UserName, 
    LoggedOn, 
    Ringing, 
    ChatInvite, 
    ChatOnly, 
    OneChatOnly 
FROM Volunteers
WHERE LoggedOn > 0 
ORDER BY Shift, lastname";

$result = dataQuery($query);

if ($result === false) {
    http_response_code(500);
    die("Database error occurred");
}

// Process the results into structured data
$volunteers = array_map(function($row) {
    $shift = $row->shift ?? 0;
    
    // Convert shift numbers to text
    $shiftText = match($shift) {
        0 => "Closed",
        1 => "1st",
        2 => "2nd",
        3 => "3rd",
        4 => "4th",
        default => "Closed"
    };
    
    // Process desk/location
    $desk = $row->desk == 0 ? "NY" : $row->desk;
    
    // Process on-call status
    $onCall = "-";
    if ($row->ChatOnly == 1) {
        $onCall = "Chat Only";
        $desk = "Chat Only";
    } elseif ($row->oncall == 1) {
        $onCall = $row->Ringing;
    } elseif (substr($row->Ringing, 0, 7) == "Logging") {
        $onCall = $row->Ringing;
    } elseif ($row->Ringing !== null) {
        $onCall = "Ringing-" . $row->Ringing;
    }
    
    // Process chat status
    $chat = "-";
    if (($row->Active1 !== null && $row->Active1 !== "Blocked") || 
        ($row->Active2 !== null && $row->Active2 !== "Blocked")) {
        $chat = "YES - 1";
    } elseif ($row->ChatInvite !== null) {
        $chat = "Invite";
    }
    
    // Check for double chat
    if ($row->Active1 !== null && $row->Active2 !== null && 
        $row->Active1 !== "Blocked" && $row->Active2 !== "Blocked") {
        $chat = "YES - 2";
    }
    
    return [
        'UserID' => htmlspecialchars($row->UserName),
        'name' => htmlspecialchars($row->firstname . " " . $row->lastname),
        'shift' => htmlspecialchars($shiftText),
        'line' => htmlspecialchars($desk),
        'onCall' => htmlspecialchars($onCall),
        'chat' => htmlspecialchars($chat),
        'loggedon' => htmlspecialchars($row->LoggedOn),
        'onechatonly' => htmlspecialchars($row->OneChatOnly)
    ];
}, $result);

// Set headers for XML response
header("Content-Type: text/xml");
header("Expires: Sun, 19 Nov 1978 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Generate XML output
echo "<?xml version=\"1.0\" encoding=\"utf-8\"?><responses>";

foreach ($volunteers as $volunteer) {
    echo "<item>";
    foreach ($volunteer as $key => $value) {
        echo "<$key>$value</$key>";
    }
    echo "</item>";
}

echo "</responses>";
?>
