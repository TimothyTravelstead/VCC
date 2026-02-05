<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours
require_once('../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

function errorLog($message, $originalQuery, $originalResult, $Type) {
    $sessionData = json_encode($_SESSION);
    $requestData = 'none';
    $volunteerFromSession = $_SESSION['UserID'] ?? null;
    $volunteerFromRequest = $_REQUEST['volunteerID'] ?? null;
    $cleanedOriginalQuery = json_encode($originalQuery) ?? null;
    
    $query = "INSERT INTO ErrorLog 
              (timestamp, page, volunteer_session, volunteer_request, message, 
               session_data, request_data, original_query, original_result, type) 
              VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)";
              
    $params = [
        'postCallLog.php',
        $volunteerFromSession,
        $volunteerFromRequest,
        $message,
        $sessionData,
        $requestData,
        $cleanedOriginalQuery,
        $originalResult,
        $Type
    ];
    
    try {
        dataQuery($query, $params);
    } catch (Exception $e) {
        die("Volunteer postCallLog.php error Log Routine - Could not update the errorLog: " . $e->getMessage());
    }
}

// Get necessary variables
$redirectURL = $WebAddress . "/twilioRedirect.php";
$VolunteerID = $_SESSION['UserID'] ?? null;
$CallSid = $_REQUEST['clientCallerSid'] ?? null;

// Release session lock after reading all session data
session_write_close();

error_log("🎯 answerCall.php START - Volunteer: " . ($VolunteerID ?? 'NULL') . ", CallSid: " . ($CallSid ?? 'NULL'));

// Check if this is a training session participant
$trainingModeCheck = dataQuery(
    "SELECT LoggedOn FROM volunteers WHERE UserName = ?",
    [$VolunteerID]
);
$isTrainingMode = $trainingModeCheck && in_array($trainingModeCheck[0]->LoggedOn ?? 0, [4, 6]);
$trainingRole = $isTrainingMode ? ($trainingModeCheck[0]->LoggedOn == 4 ? 'trainer' : 'trainee') : 'none';
error_log("🎓 answerCall.php: Training mode check - LoggedOn=" . ($trainingModeCheck[0]->LoggedOn ?? 'NULL') . ", isTraining=$isTrainingMode, role=$trainingRole");

if (!$VolunteerID) {
    error_log("❌ answerCall.php ERROR: No Volunteer ID present");
    die("Answer Call Error: No Volunteer ID present.");
}

// Update CallSid to Show Which Volunteer Answered It
error_log("📝 answerCall.php: Updating CallRouting to assign call to volunteer");
$query = "UPDATE CallRouting
          SET Volunteer = ?
          WHERE CallSid = ?
          AND Volunteer IS NULL";
$result = dataQuery($query, [$VolunteerID, $CallSid]);
error_log("✅ answerCall.php: CallRouting updated - Result: " . ($result !== false ? 'SUCCESS' : 'FAILED'));

// Add small delay to ensure DB consistency
usleep(500);

// Check which volunteer actually answered the call
error_log("🔍 answerCall.php: Verifying who answered the call");
$query = "SELECT Volunteer
          FROM CallRouting
          WHERE CallSid = ?";
$result = dataQuery($query, [$CallSid]);

if (!empty($result)) {
    $AnsweringVolunteer = $result[0]->Volunteer;
    error_log("📊 answerCall.php: CallRouting shows volunteer: " . ($AnsweringVolunteer ?? 'NULL'));

    // Check if another volunteer answered first
    if (strcasecmp(trim($AnsweringVolunteer), trim($VolunteerID)) !== 0) {
        error_log("⚠️ answerCall.php: Call already answered by $AnsweringVolunteer, not $VolunteerID");
        $query = "INSERT INTO callConflictLog
                  (CallSid, AnsweringVolunteer, AttemptingVolunteer, timestamp)
                  VALUES (?, ?, ?, NOW())";
        dataQuery($query, [$CallSid, $AnsweringVolunteer, $VolunteerID]);
        die('The call was already answered by another volunteer');
    }
    error_log("✅ answerCall.php: Verification passed - $VolunteerID is the answering volunteer");
}

// Cancel call notifications for other volunteers
$query = "UPDATE Volunteers 
          SET OnCall = 0,
              Ringing = NULL,
              HotlineName = NULL,
              CallCity = NULL,
              CallState = NULL,
              CallZip = NULL,
              IncomingCallSid = NULL 
          WHERE UserName != ? 
          AND IncomingCallSid = ?";
dataQuery($query, [$VolunteerID, $CallSid]);

// Update status for answering volunteer
$query = "UPDATE Volunteers
          SET OnCall = 1
          WHERE UserName = ?";
dataQuery($query, [$VolunteerID]);

// Sync OnCall status for ALL training session participants
// This prevents new calls from ringing while any participant is on a call
$trainingCheck = "SELECT LoggedOn FROM volunteers WHERE UserName = ?";
$trainingResult = dataQuery($trainingCheck, [$VolunteerID]);

if ($trainingResult && count($trainingResult) > 0) {
    $loggedOnStatus = $trainingResult[0]->LoggedOn;

    if ($loggedOnStatus == 6) {
        // Trainee answered - find and mark trainer as on-call
        $findTrainer = "SELECT UserName FROM volunteers
                       WHERE FIND_IN_SET(?, TraineeID) > 0 AND LoggedOn = 4";
        $trainerResult = dataQuery($findTrainer, [$VolunteerID]);
        if ($trainerResult && count($trainerResult) > 0) {
            $trainerId = $trainerResult[0]->UserName;
            dataQuery("UPDATE Volunteers SET OnCall = 1 WHERE UserName = ?", [$trainerId]);
            error_log("📞 answerCall.php: Training sync - marked trainer '$trainerId' as OnCall (trainee '$VolunteerID' answered)");
        }
    } elseif ($loggedOnStatus == 4) {
        // Trainer answered - mark all trainees as on-call
        $getTrainees = "SELECT TraineeID FROM volunteers WHERE UserName = ?";
        $traineeResult = dataQuery($getTrainees, [$VolunteerID]);
        if ($traineeResult && !empty($traineeResult[0]->TraineeID)) {
            $traineeIds = array_map('trim', explode(',', $traineeResult[0]->TraineeID));
            foreach ($traineeIds as $traineeId) {
                if (!empty($traineeId)) {
                    dataQuery("UPDATE Volunteers SET OnCall = 1 WHERE UserName = ?", [$traineeId]);
                }
            }
            error_log("📞 answerCall.php: Training sync - marked " . count($traineeIds) . " trainee(s) as OnCall (trainer '$VolunteerID' answered)");
        }
    }
}

// **PUBLISH CALL ANSWERED EVENT TO REDIS FOR REAL-TIME UPDATES**
try {
    require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
    $publisher = new VCCFeedPublisher();
    $publisher->publishUserListChange('call_answered', [
        'username' => $VolunteerID,
        'callSid' => $CallSid,
        'timestamp' => time()
    ]);
    // Clear ringing state and refresh cache for polling clients
    $publisher->clearRinging($VolunteerID);
    $publisher->refreshUserListCache();
} catch (Exception $e) {
    // Log but don't fail call answer for publisher issues
    error_log("VCCFeedPublisher error on call answer: " . $e->getMessage());
}

$query = "UPDATE CallerHistory
          SET Category = 'In Progress',
              VolunteerID = ?
          WHERE CallSid = ?";
dataQuery($query, [$VolunteerID, $CallSid]);
error_log("✅ answerCall.php: Updated CallerHistory - Category='In Progress', VolunteerID='$VolunteerID' for CallSid=$CallSid");

// Twilio API handling
require('twilio-php-main/src/Twilio/autoload.php');
use \Twilio\Rest\Client;

// Check if credentials are available
if (empty($accountSid) || empty($authToken)) {
    error_log("❌ answerCall.php ERROR: Missing Twilio credentials");
    errorLog("Missing Twilio credentials", null, "Credentials not found", "ERROR");
    die("Configuration error");
}

error_log("📞 answerCall.php: Preparing to redirect call via Twilio API");
$client = new Client($accountSid, $authToken);
try {
    error_log("🔄 answerCall.php: Fetching call $CallSid from Twilio");
    $call = $client->calls($CallSid)->fetch();
    error_log("📊 answerCall.php: Current call status: " . $call->status);

    error_log("🎯 answerCall.php: Calling \$call->update() to redirect to twilioRedirect.php");
    $call->update([
        "Url" => $WebAddress . "/twilioRedirect.php",
        "Method" => "POST"
    ]);
    error_log("✅ answerCall.php: Call update completed successfully - External caller should redirect to conference");
} catch (Exception $e) {
    error_log("❌ answerCall.php ERROR: Call update FAILED - " . $e->getMessage());
    error_log("❌ answerCall.php ERROR: Exception code: " . $e->getCode());
    errorLog($e->getMessage(), $CallSid, 'ERROR', 'ERROR');
}
error_log("🏁 answerCall.php COMPLETE - Returning OK to client");
echo "OK";