<?php


// Define Twilio phone number mappings

require_once('../private_html/db_login.php');
session_start();
const HOTLINE_NUMBERS = [
    'GLNH' => ['+18888434564', '+14159929723'],
    'GLSB-NY' => ['+16463621511', '+12129890999'],
    'local' => ['+14153550999'],
    'SAGE' => ['+18882347243']
];

// Collect Twilio variables with null coalescing
$twilioData = [
    'From' => $_REQUEST['From'] ?? null,
    'Length' => $_REQUEST['CallDuration'] ?? null,
    'CallSid' => $_REQUEST['CallSid'] ?? null,
    'CallStatus' => $_REQUEST['DialCallStatus'] ?? null,
    'FromCity' => $_REQUEST['FromCity'] ?? null,
    'FromCountry' => $_REQUEST['FromCountry'] ?? null,
    'FromState' => $_REQUEST['FromState'] ?? null,
    'FromZip' => $_REQUEST['FromZip'] ?? null,
    'To' => $_REQUEST['To'] ?? null,
    'ToCity' => $_REQUEST['ToCity'] ?? null,
    'ToCountry' => $_REQUEST['ToCountry'] ?? null,
    'ToState' => $_REQUEST['ToState'] ?? null,
    'ToZip' => $_REQUEST['ToZip'] ?? null
];

// Determine hotline type
$Hotline = 'Youth'; // Default value
foreach (HOTLINE_NUMBERS as $type => $numbers) {
    if (in_array($twilioData['To'], $numbers)) {
        $Hotline = $type;
        break;
    }
}

// Format caller ID
$CallerID = sprintf(
    "(%s) %s-%s",
    substr($twilioData['From'], 2, 3),
    substr($twilioData['From'], 5, 3),
    substr($twilioData['From'], 8, 4)
);

// Get calling address
$CallingAddress = sprintf("Calling Address: %s", getenv('REMOTE_ADDR'));

// Update caller history with length and message
$query = "UPDATE CallerHistory 
          SET length = SEC_TO_TIME(?), 
              Message = ? 
          WHERE CallSid = ?";
$params = [$twilioData['Length'], $CallingAddress, $twilioData['CallSid']];
$result = dataQuery($query, $params);

// Get call status category
$query = "SELECT Category FROM CallerHistory WHERE CallSid = ?";
$result = dataQuery($query, [$twilioData['CallSid']]);
$CallStatusCategory = $result[0]->Category ?? null;

// Update call history if status is "In Progress"
if ($CallStatusCategory === "In Progress") {
    $query = "UPDATE CallerHistory 
              SET Category = ? 
              WHERE CallSid = ?";
    $result = dataQuery($query, ["Hang Up While Ringing", $twilioData['CallSid']]);
}

// Reset volunteer status
$query = "UPDATE Volunteers 
          SET Ringing = NULL,
              HotlineName = NULL,
              CallCity = NULL,
              CallState = NULL,
              CallZip = NULL,
              IncomingCallSid = NULL 
          WHERE IncomingCallSid = ?";
$result = dataQuery($query, [$twilioData['CallSid']]);

// Set XML content type and output response
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>
<Response></Response>';
