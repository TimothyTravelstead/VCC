<?php
/**
 * Twilio Call Redirect Handler
 *
 * This file is called by Twilio's webhook when a volunteer accepts a call.
 * It generates TwiML to connect the caller to the volunteer's conference room.
 *
 * IMPORTANT: This file does NOT need session access - it only uses $_REQUEST data from Twilio.
 * DO NOT call session_start() here or it will cause timeouts waiting for session lock!
 */

// Include the database connection and functions
require_once('../private_html/db_login.php');

// NO session_start() - this file doesn't use session data and calling it causes
// Twilio webhook timeouts (Error 11200) while waiting for session lock

// Twilio Received Variables - using null coalescing operator
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

// UPDATED 2025-01-08: Integrated modern training control system
// This replaces the legacy Muted field logic with training_session_control table checks
// 
// Get the volunteer who answered the call from CallRouting table
$query = "SELECT Volunteer FROM CallRouting WHERE CallSid = ?";
$result = dataQuery($query, [$CallSid]);

$Volunteer = null;
$isTrainingMode = false; // Track if this is a training session call

if ($result && count($result) > 0) {
    $Volunteer = $result[0]->Volunteer;

    // Check if the answering volunteer is in a training session
    // LoggedOn values: 1=normal volunteer, 4=trainer, 6=trainee
    $trainingQuery = "SELECT LoggedOn FROM volunteers WHERE UserName = ?";
    $trainingResult = dataQuery($trainingQuery, [$Volunteer]);

    if ($trainingResult && count($trainingResult) > 0) {
        $loggedOnStatus = $trainingResult[0]->LoggedOn;

        // TRAINING SESSION HANDLING
        // If this is a trainee (LoggedOn = 6) in a training session
        if ($loggedOnStatus == 6) {
            $isTrainingMode = true; // This is a training session

            // Find which trainer this trainee belongs to
            // TraineeID field contains comma-separated list of trainees assigned to each trainer
            $findTrainerQuery = "SELECT UserName FROM volunteers
                               WHERE FIND_IN_SET(?, TraineeID) > 0
                               AND LoggedOn = 4";
            $trainerResult = dataQuery($findTrainerQuery, [$Volunteer]);

            if ($trainerResult && count($trainerResult) > 0) {
                $trainerId = $trainerResult[0]->UserName;

                // Check who has control in this training session using modern control system
                $controlQuery = "SELECT active_controller FROM training_session_control
                               WHERE trainer_id = ?";
                $controlResult = dataQuery($controlQuery, [$trainerId]);

                if ($controlResult && count($controlResult) > 0) {
                    $activeController = $controlResult[0]->active_controller;

                    // CRITICAL: External calls ALWAYS route to trainer's conference room
                    // This ensures all training participants (trainer + trainees) can hear the caller
                    // The trainee with control can speak to the caller (unmuted)
                    // Other participants remain muted but can hear
                    $Volunteer = $trainerId;
                } else {
                    // No control record exists, default to trainer's conference
                    $Volunteer = $trainerId;
                }
            }
        } elseif ($loggedOnStatus == 4) {
            // This is a trainer - also training mode
            $isTrainingMode = true;
        }
        // If this is a trainer (LoggedOn = 4), they use their own conference
        // Trainer keeps their own conference name - external calls join their conference
        // For normal volunteers (LoggedOn = 1), no changes needed - standard call routing applies
    }
}

// Set headers
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header("content-type: text/xml");

// Generate TwiML response
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<Response>";
echo "  <Dial action='".$WebAddress."/answeredCallEnd.php' method='POST'>";

// CRITICAL FIX: External callers joining EXISTING training conferences should be participants, not moderators
// Training conferences are already created by trainer with startConferenceOnEnter=true, endConferenceOnExit=true
// Adding external callers with the same flags causes conference ownership conflicts and call drops
// Conference status callback for join/leave/mute events
$confStatusCallback = $WebAddress . "/twilioStatus.php";
$confStatusEvents = "start end join leave mute hold";

if ($isTrainingMode) {
    // External caller joins existing training conference as a regular participant
    // Do NOT start conference (already exists), do end conference when leaving
    echo "    <Conference beep='onExit' startConferenceOnEnter='false' endConferenceOnExit='true' waitUrl='".$WebAddress."/Audio/waitMusic.php' statusCallback='".$confStatusCallback."' statusCallbackEvent='".$confStatusEvents."'>".$Volunteer."</Conference>";
} else {
    // Normal volunteer call - use standard conference settings
    echo "    <Conference beep='onExit' startConferenceOnEnter='true' endConferenceOnExit='true' waitUrl='".$WebAddress."/Audio/waitMusic.php' statusCallback='".$confStatusCallback."' statusCallbackEvent='".$confStatusEvents."'>".$Volunteer."</Conference>";
}

echo "  </Dial>";
echo "</Response>";

// No need to close connection as PDO handles this automatically
?>
