<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../private_html/db_login.php');
include('../../private_html/csrf_protection.php');

// Now start the session with the correct configuration
session_start();

// Require admin authentication
requireAdmin();

// DEBUG: Log ExitProgram.php calls
try {
    $debugLog = date('Y-m-d H:i:s') . " - ExitProgram.php called\n";
    $debugLog .= date('Y-m-d H:i:s') . " - Request method: " . $_SERVER['REQUEST_METHOD'] . "\n";
    $debugLog .= date('Y-m-d H:i:s') . " - VolunteerID param: " . ($_REQUEST['VolunteerID'] ?? 'NOT SET') . "\n";
    $debugLog .= date('Y-m-d H:i:s') . " - Session UserID: " . ($_SESSION['UserID'] ?? 'NOT SET') . "\n";
    $debugLog .= date('Y-m-d H:i:s') . " - HTTP Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'NOT SET') . "\n";
    $debugLog .= date('Y-m-d H:i:s') . " - User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'NOT SET') . "\n";
    file_put_contents('../debug_admin_logoff.txt', $debugLog, FILE_APPEND | LOCK_EX);
} catch (Exception $e) {
    // Ignore debug logging errors
}

// Include admin debug logging
include('adminDebugLog.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Validate CSRF token for POST operations only (GET requests use URL parameters)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Skip CSRF validation for trainee exits triggered by trainer logout
    // These are admin-initiated cascading exits, not user-initiated
    $isTraineeExit = isset($_REQUEST['VolunteerID']) &&
                     isset($_SESSION['UserID']) &&
                     $_SESSION['UserID'] !== $_REQUEST['VolunteerID'];

    if (!$isTraineeExit) {
        requireValidCSRFToken($_REQUEST);
    }
}

$VolunteerID = $_REQUEST["VolunteerID"];

// Log this exit request immediately
$currentUser = $_SESSION['UserID'] ?? 'UNKNOWN';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
$referer = $_SERVER['HTTP_REFERER'] ?? 'UNKNOWN';

writeAdminLog("EXIT PROGRAM CALLED - Target: $VolunteerID, By: $currentUser, Method: $requestMethod, Referer: $referer", "EXIT_CALL");

// Capture session file paths BEFORE closing session for explicit cleanup (if logging out self)
$sessionId = session_id();
$sessionFile = session_save_path() . '/sess_' . $sessionId;
$customJsonFile = dirname(__FILE__) . '/../../private_html/session_' . $sessionId . '.json';

// Release session lock after reading all session data
session_write_close();

// Validate VolunteerID
if (empty($VolunteerID)) {
    writeAdminLog("EXIT PROGRAM ERROR - Empty VolunteerID", "ERROR");
    echo "ERROR: VolunteerID required";
    exit;
}

// Get user info before deletion for media server notification
$query = "SELECT LoggedOn, TraineeID FROM volunteers WHERE UserName = ?";
$userInfo = dataQuery($query, [$VolunteerID]);

// Handle case where user doesn't exist or isn't logged on
if (empty($userInfo)) {
    echo "WARNING: User $VolunteerID not found - may already be logged out";
    exit;
}

$loggedOnStatus = $userInfo[0]->LoggedOn;
if ($loggedOnStatus == 0) {
    echo "WARNING: User $VolunteerID already logged out";
    exit;
}

$isTrainer = false;
$isTrainee = false;
$roomName = null;

$traineeID = $userInfo[0]->TraineeID;

if ($loggedOnStatus == 4) { // Trainer
    $isTrainer = true;
    $roomName = $VolunteerID; // Trainer ID is room name
} elseif ($loggedOnStatus == 6) { // Trainee
    $isTrainee = true;
    // Find trainer for this trainee
    $trainerQuery = "SELECT UserName FROM volunteers WHERE FIND_IN_SET(?, TraineeID) > 0";
    $trainerResult = dataQuery($trainerQuery, [$VolunteerID]);
    if (!empty($trainerResult)) {
        $roomName = $trainerResult[0]->UserName;
    }
}

// Array to track operation success
$operations = [];

// Clean up SessionBridge session if exists
if (isset($_SESSION['volunteer_session_id'])) {
    try {
        require_once '../SessionBridge.php';
        $bridge = new SessionBridge();
        $bridge->endSession($_SESSION['volunteer_session_id'], 'admin_exit');
        echo "<!-- SessionBridge cleanup successful -->";
    } catch (Exception $e) {
        // Don't fail exit if SessionBridge cleanup fails
        error_log("SessionBridge cleanup failed during exit: " . $e->getMessage());
    }
}

// Existing operations...
$queryLog = "INSERT INTO Volunteerlog VALUES (null, :volunteer_id, NOW(), 0, null)";
$operations[] = dataQuery($queryLog, [':volunteer_id' => $VolunteerID]);

// CRITICAL: About to set LoggedOn = 0 - this triggers auto-logoff!
writeAdminLog("CRITICAL: SETTING LOGGEDON = 0 for UserName: $VolunteerID", "CRITICAL_DB_UPDATE");

$query = "UPDATE Volunteers
          SET LoggedOn = 0,
              Active1 = NULL,
              Active2 = NULL,
              OnCall = 0,
              ChatInvite = NULL,
              Ringing = NULL,
              TraineeID = NULL,
              Muted = 0,
              IncomingCallSid = NULL
          WHERE UserName = :volunteer_id";
$operations[] = dataQuery($query, [':volunteer_id' => $VolunteerID]);

// Remove from CallControl table
$deleteControl = "DELETE FROM CallControl WHERE user_id = ?";
$operations[] = dataQuery($deleteControl, [$VolunteerID]);
writeAdminLog("CallControl: Removed user $VolunteerID from CallControl table", "EXIT_CALLCONTROL");

// Clean up training_session_control table (mirrors volunteerPosts.php exitProgram logic)
if ($isTrainer) {
    // Trainer exiting: Delete their control record entirely
    $deleteTrainingControl = "DELETE FROM training_session_control WHERE trainer_id = ?";
    dataQuery($deleteTrainingControl, [$VolunteerID]);
    writeAdminLog("TrainingControl: Deleted training_session_control for trainer $VolunteerID", "EXIT_TRAINING");

    // Clear OnCall status for all trainees assigned to this trainer
    // ($traineeID was captured earlier before TraineeID was set to NULL)
    if (!empty($traineeID)) {
        $traineeIds = array_map('trim', explode(',', $traineeID));
        if (count($traineeIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($traineeIds), '?'));
            dataQuery("UPDATE Volunteers SET OnCall = 0, Ringing = NULL WHERE UserName IN ($placeholders)", $traineeIds);
            writeAdminLog("TrainingControl: Cleared OnCall for " . count($traineeIds) . " trainee(s)", "EXIT_TRAINING");
        }
    }
} elseif ($isTrainee && $roomName) {
    // Trainee exiting: Check if they had control, transfer back to trainer
    $checkControl = "SELECT active_controller FROM training_session_control WHERE trainer_id = ?";
    $controlResult = dataQuery($checkControl, [$roomName]);

    if ($controlResult && count($controlResult) > 0 && $controlResult[0]->active_controller === $VolunteerID) {
        // Trainee had control - transfer back to trainer
        $transferControl = "UPDATE training_session_control
                           SET active_controller = trainer_id, controller_role = 'trainer'
                           WHERE trainer_id = ?";
        dataQuery($transferControl, [$roomName]);
        writeAdminLog("TrainingControl: Transferred control back to trainer $roomName", "EXIT_TRAINING");

        // Re-add trainer to CallControl so they can receive calls
        $readdTrainer = "INSERT INTO CallControl (user_id, logged_on_status, can_receive_calls, can_receive_chats)
                        VALUES (?, 4, 1, 1)
                        ON DUPLICATE KEY UPDATE can_receive_calls = 1, can_receive_chats = 1";
        dataQuery($readdTrainer, [$roomName]);
        writeAdminLog("TrainingControl: Re-added trainer $roomName to CallControl", "EXIT_TRAINING");
    }

    // Write signal file to notify trainer that trainee has left
    $signalDir = dirname(__FILE__) . '/../trainingShare3/Signals/';
    $signalFile = $signalDir . 'participant_' . $roomName . '.txt';

    $message = json_encode([
        'type' => 'trainee-exited',
        'traineeId' => $VolunteerID,
        'timestamp' => time(),
        'message' => $VolunteerID . ' has left the training session (admin logout)',
        'reason' => 'admin-forced-exit'
    ]);

    // Append to signal file (trainer will pick it up via polling)
    file_put_contents($signalFile, $message . "\n", FILE_APPEND | LOCK_EX);
    writeAdminLog("TrainingControl: Wrote trainee-exited signal for trainer $roomName", "EXIT_TRAINING");
}

// **PUBLISH LOGOUT EVENT TO REDIS FOR REAL-TIME UPDATES**
try {
    require_once(__DIR__ . '/../lib/VCCFeedPublisher.php');
    $publisher = new VCCFeedPublisher();
    $publisher->publishUserListChange('logout', [
        'username' => $VolunteerID,
        'loggedOnStatus' => $loggedOnStatus,
        'isTrainer' => $isTrainer,
        'isTrainee' => $isTrainee,
        'timestamp' => time()
    ]);
    // Refresh the user list cache for polling clients
    $publisher->refreshUserListCache();
} catch (Exception $e) {
    // Log but don't fail logout for publisher issues
    error_log("VCCFeedPublisher error on logout: " . $e->getMessage());
}

// Notify media server of admin-forced exit
if ($roomName && ($isTrainer || $isTrainee)) {
    $eventType = $isTrainer ? 'trainer-signed-off' : 'trainee-signed-off';
    notifyMediaServer($eventType, [
        'roomName' => $roomName,
        'userId' => $VolunteerID,
        'role' => $isTrainer ? 'trainer' : 'trainee',
        'reason' => 'admin-forced-exit'
    ]);
}

// Rest of existing logic...
$query7 = "SELECT COUNT(username) as count FROM Volunteers WHERE loggedon = 1";
$result7 = dataQuery($query7);

$query8 = "DELETE FROM VolunteerIM WHERE imTo = :volunteer_id OR imFrom = :volunteer_id";
$operations[] = dataQuery($query8, [':volunteer_id' => $VolunteerID]);

if ($result7 && $result7[0]->count == 0) {
    $query9 = "DELETE FROM Chat";
    $operations[] = dataQuery($query9);
}

$success = !in_array(false, $operations, true);

if ($success) {
    echo "OK";
    // If logging out self, explicitly delete session files
    // (session_destroy() doesn't work after session_write_close())
    if ($VolunteerID === $currentUser && !empty($sessionId)) {
        if (file_exists($sessionFile)) {
            @unlink($sessionFile);
        }
        if (file_exists($customJsonFile)) {
            @unlink($customJsonFile);
        }
    }
} else {
    echo "Error during logout process";
    error_log("Volunteer logout failed for UserID: " . $VolunteerID);
}
?>