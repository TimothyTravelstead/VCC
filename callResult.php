<?php

// Include database connection and functions

// Twilio Received Variables with null coalescing

require_once('../private_html/db_login.php');
session_start();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$From = $_REQUEST['From'] ?? null;
$Length = $_REQUEST["CallDuration"] ?? null;
$CallSid = $_REQUEST["CallSid"] ?? null;
$CallStatus = $_REQUEST["CallStatus"] ?? null;
$FromCity = $_REQUEST["FromCity"] ?? null;
$FromCountry = $_REQUEST["FromCountry"] ?? null;
$FromState = $_REQUEST["FromState"] ?? null;
$FromZip = $_REQUEST["FromZip"] ?? null;
$To = $_REQUEST["To"] ?? null;
$ToCity = $_REQUEST["ToCity"] ?? null;
$ToCountry = $_REQUEST["ToCountry"] ?? null;
$ToState = $_REQUEST["ToState"] ?? null;
$ToZip = $_REQUEST["ToZip"] ?? null;

// Determine call category based on status
$Category = match($CallStatus) {
    "no-answer" => "No Answer",
    "canceled" => "Hang Up While Ringing",
    "failed" => "Twilio Error",
    "ringing" => "Ringing",
    "in-progress" => "In Progress",
    "completed" => "Hang Up While Ringing",
    default => "Unknown"
};

try {
    // Update call record with length and category
    $query = "UPDATE CallerHistory 
              SET Length = SEC_TO_TIME(?),
                  Category = ? 
              WHERE CallSid = ? 
              AND Category = 'In Progress'";
    dataQuery($query, [$Length, $Category, $CallSid]);

    // Clear call status for all volunteers with this CallSid
    $query = "UPDATE Volunteers 
              SET Ringing = NULL,
                  HotlineName = NULL,
                  CallCity = NULL,
                  CallState = NULL,
                  CallZip = NULL,
                  IncomingCallSid = NULL 
              WHERE IncomingCallSid = ?";
    dataQuery($query, [$CallSid]);

    // Output TwiML response
    // Prevent Twilio from caching TwiML responses
    header('Cache-Control: no-cache, no-store, must-revalidate, private');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Type: text/xml');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    
    if ($CallStatus !== 'completed') {
        echo "<Response>\n";

        // Generate audio URL
        $greetingFile = "Open_GLNH.mp3";
        $audioPath = $WebAddress . "/Audio/" . $greetingFile;

        // Output with proper XML encoding
        echo "    <Play>" . htmlspecialchars($audioPath, ENT_XML1, 'UTF-8') . "</Play>\n";
        echo "</Response>\n";
    }

} catch (Exception $e) {
    // Log error and return empty response
    error_log("Call status update error: " . $e->getMessage());
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response></Response>";
}
