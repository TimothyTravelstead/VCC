<?php

// Include the database connection and functions

// Twilio Received Variables - using null coalescing operator

require_once('../private_html/db_login.php');
session_start();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$From = $_REQUEST['From'] ?? null;
$Length = $_REQUEST["DialCallDuration"] ?? null;
$CallSid = $_REQUEST["CallSid"] ?? null;
$CallStatus = $_REQUEST["DialCallStatus"] ?? null;

// =============================================================================
// TRAINING CALL DETECTION - Skip processing for training participants
// =============================================================================
// When a trainer or trainee answers a call:
// 1. answerCall.php updates CallRouting with their username
// 2. answerCall.php redirects the call via Twilio API ($call->update())
// 3. Twilio's <Dial> action fires this webhook ANYWAY (Dial completed callback)
//
// RACE CONDITION: If we process this normally, we'd mark the call as unanswered
// even though it's connected in the training conference.
//
// SOLUTION: Check if call was answered by a training participant (LoggedOn 4 or 6)
// and skip processing if so.
// =============================================================================

if ($CallSid) {
    // Check if call was already answered and assigned to a volunteer
    $callRouting = dataQuery(
        "SELECT Volunteer FROM CallRouting WHERE CallSid = ?",
        [$CallSid]
    );

    if ($callRouting && !empty($callRouting[0]->Volunteer)) {
        $volunteer = $callRouting[0]->Volunteer;

        // Check if volunteer is trainer (4) or trainee (6)
        $trainingCheck = dataQuery(
            "SELECT LoggedOn FROM volunteers WHERE UserName = ?",
            [$volunteer]
        );

        if ($trainingCheck && in_array($trainingCheck[0]->LoggedOn, [4, 6])) {
            // Training call - already routed to conference, don't process as unanswered
            error_log("🎓 unAnsweredCall.php: SKIP - Training participant '$volunteer' (LoggedOn={$trainingCheck[0]->LoggedOn}) answered CallSid=$CallSid");

            // Return empty TwiML response - call is already in training conference
            header('Cache-Control: no-cache, no-store, must-revalidate, private');
            header('Pragma: no-cache');
            header('Expires: 0');
            header("content-type: text/xml");
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            echo "<Response></Response>\n";
            exit;
        }
    }
}

// =============================================================================
// Normal processing continues for non-training calls
// =============================================================================
$FromCity = $_REQUEST["FromCity"] ?? null;
$FromCountry = $_REQUEST["FromCountry"] ?? null;
$FromState = $_REQUEST["FromState"] ?? null;
$FromZip = $_REQUEST["FromZip"] ?? null;
$To = $_REQUEST["To"] ?? null;
$ToCity = $_REQUEST["ToCity"] ?? null;
$ToCountry = $_REQUEST["ToCountry"] ?? null;
$ToState = $_REQUEST["ToState"] ?? null;
$ToZip = $_REQUEST["ToZip"] ?? null;
$FinalCallReport = false;

// Determine call category based on status
// Twilio statuses: queued, ringing, in-progress, completed, busy, failed, no-answer, canceled
$CallStatus = $CallStatus ?? '';  // Ensure not null
$Category = match ($CallStatus) {
    "canceled" => "Hang Up While Ringing",
    "failed" => "Twilio Error",
    "ringing" => "Ringing",
    "in-progress" => "In Progress",
    "completed" => ($Length > 90) ? "Conversation" : "Hang Up on Volunteer",
    "no-answer" => "No Answer",
    "busy" => "Busy",
    "queued" => "In Queue",
    default => "Unknown"
};

// Handle unknown/null status - try to determine category from call duration
if ($Category == "Unknown") {
    error_log("unAnsweredCall.php: Unknown CallStatus '$CallStatus' for CallSid=$CallSid, Length=$Length");

    // If we have length data, make an educated guess at the category
    $durationSeconds = intval($Length);
    if ($durationSeconds == 0) {
        // Very short/no duration - likely hung up immediately
        $Category = "Hang Up While Ringing";
    } elseif ($durationSeconds <= 30) {
        // Short call, no answer reached
        $Category = "Hang Up While Ringing";
    } elseif ($durationSeconds <= 90) {
        // Medium call - possibly hung up on volunteer
        $Category = "Hang Up on Volunteer";
    } else {
        // Longer call - likely a conversation
        $Category = "Conversation";
    }
    error_log("unAnsweredCall.php: Assigned fallback category '$Category' based on duration $durationSeconds seconds");
}


// Set default length if not provided
$Length = $Length ?: 0;

// Log unknown calls
if ($Category == "Unknown" && $To != "client:Ringer") {
    $query = "INSERT INTO unknownCalls (idunknownCalls, CallSID, CallObject) VALUES (DEFAULT, ?, ?)";
    $result = dataQuery($query, [$CallSid, $string]);
}

// Update caller history
$query2 = "UPDATE CallerHistory 
           SET Category = ?, 
               Length = sec_to_time(?) 
           WHERE CallSid = ?";
$result2 = dataQuery($query2, [$Category, $Length, $CallSid]);

// Get volunteers who were ringing for this call (before clearing)
$ringingVolunteers = dataQuery(
    "SELECT UserName FROM Volunteers WHERE IncomingCallSid = ? AND oncall = 0",
    [$CallSid]
);

// Update volunteer status
$query3 = "UPDATE Volunteers
           SET Ringing = NULL,
               HotlineName = NULL,
               CallCity = NULL,
               CallState = NULL,
               CallZip = NULL,
               IncomingCallSid = NULL
           WHERE IncomingCallSid = ?
           AND oncall = 0";
$result3 = dataQuery($query3, [$CallSid]);

// Clear Redis ringing state for all affected volunteers
if ($ringingVolunteers && count($ringingVolunteers) > 0) {
    try {
        require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
        $publisher = new VCCFeedPublisher();
        $usernames = array_map(function($v) { return $v->UserName; }, $ringingVolunteers);
        $publisher->clearRingingMultiple($usernames);
        $publisher->refreshUserListCache();
    } catch (Exception $e) {
        error_log("VCCFeedPublisher clearRinging error in unAnsweredCall: " . $e->getMessage());
    }
}

// Generate TwiML response
// Prevent Twilio from caching TwiML responses
header('Cache-Control: no-cache, no-store, must-revalidate, private');
header('Pragma: no-cache');
header('Expires: 0');
header("content-type: text/xml");
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
if ($CallStatus != 'completed') {
    echo "<Response>\n";

    // Generate audio URL with dynamic cache-busting based on file modification time
    $greetingFile = "Open_GLNH.mp3";
    $audioPath = $WebAddress . "/Audio/" . $greetingFile;

    // Output with proper XML encoding
    echo "    <Play>" . htmlspecialchars($audioPath, ENT_XML1, 'UTF-8') . "</Play>\n";
    echo "</Response>\n";
}

?>
