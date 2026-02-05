<?php

// Include database connection and functions

// Twilio Received Variables - using null coalescing operator

require_once('../private_html/db_login.php');
session_start();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$From = $_REQUEST['From'] ?? null;
$Length = $_REQUEST["DialCallDuration"] ?? null;
$CallSid = $_REQUEST["CallSid"] ?? null;
$CallStatus = $_REQUEST["DialCallStatus"] ?? null;
$FromCity = $_REQUEST["FromCity"] ?? null;
$FromCountry = $_REQUEST["FromCountry"] ?? null;
$FromState = $_REQUEST["FromState"] ?? null;
$FromZip = $_REQUEST["FromZip"] ?? null;
$To = $_REQUEST["To"] ?? null;
$ToCity = $_REQUEST["ToCity"] ?? null;
$ToCountry = $_REQUEST["ToCountry"] ?? null;
$ToState = $_REQUEST["ToState"] ?? null;
$ToZip = $_REQUEST["ToZip"] ?? null;

// Initial category
$Category = "Updating";

// Update call record with end time and calculate duration
$query = "UPDATE CallerHistory 
          SET callEnd = NOW(), 
              Length = TIMEDIFF(NOW(), callStart),
              Category = ? 
          WHERE CallSid = ?";
dataQuery($query, [$Category, $CallSid]);

// Get call duration in seconds
$query = "SELECT TIME_TO_SEC(Length) as duration 
          FROM CallerHistory 
          WHERE CallSid = ?";
$result = dataQuery($query, [$CallSid]);

if (!empty($result)) {
    $Length = $result[0]->duration;

    // Determine call category based on duration
    $Category = ($Length <= 90) ? "Hang Up on Volunteer" : "Conversation";

    // Get the volunteer who answered from CallRouting (fallback if not already set)
    $volunteerQuery = "SELECT Volunteer FROM CallRouting WHERE CallSid = ? AND Volunteer IS NOT NULL";
    $volunteerResult = dataQuery($volunteerQuery, [$CallSid]);
    $Volunteer = (!empty($volunteerResult)) ? $volunteerResult[0]->Volunteer : null;

    // Update call record with final category, duration, and volunteer (if available)
    if ($Volunteer) {
        $query = "UPDATE CallerHistory
                  SET Category = ?,
                      Message = ?,
                      VolunteerID = CASE WHEN VolunteerID IS NULL OR VolunteerID = 'N/A' THEN ? ELSE VolunteerID END
                  WHERE CallSid = ?";
        dataQuery($query, [$Category, $Length, $Volunteer, $CallSid]);
        error_log("answeredCallEnd.php: Updated CallerHistory for $CallSid - Category=$Category, Volunteer=$Volunteer");
    } else {
        $query = "UPDATE CallerHistory
                  SET Category = ?,
                      Message = ?
                  WHERE CallSid = ?";
        dataQuery($query, [$Category, $Length, $CallSid]);
    }
}

// Output Twilio TwiML response
header('Content-Type: text/xml');
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<Response>
    <Hangup/>
</Response>
