<?php
// Notify participants about external call ending
header('Content-Type: application/json');

// Load database connection
require_once '../../private_html/db_login.php';

// Start session to read user data if needed, then immediately release lock
session_start();
session_write_close();

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$trainerId = $input['trainerId'] ?? '';
$activeController = $input['activeController'] ?? '';
$notifyAll = $input['notifyAll'] ?? false;
$callerId = $input['callerId'] ?? '';
$callerRole = $input['callerRole'] ?? '';

error_log("INFO: notifyCallEnd called - trainerId: $trainerId, activeController: $activeController, callerId: $callerId, callerRole: $callerRole");

try {
    // Trainer ID should come from the hidden field (populated during login)
    // Database lookup is only a LAST-RESORT fallback for edge cases
    if (empty($trainerId)) {
        error_log("WARNING: Trainer ID empty - hidden field not populated correctly! Attempting database fallback.");

        if (!empty($callerId)) {
            // Last-resort: Look up trainer from database
            error_log("INFO: Database fallback - looking up trainer for caller: $callerId");

            // Check if the caller is a trainee - look up their trainer
            $trainerQuery = "SELECT UserName FROM volunteers WHERE FIND_IN_SET(?, TraineeID) > 0 LIMIT 1";
            $trainerResult = dataQuery($trainerQuery, [$callerId]);

            if (!empty($trainerResult)) {
                $trainerId = $trainerResult[0]->UserName;
                error_log("INFO: Database fallback found trainer: $trainerId");
            } else {
                // Caller might be the trainer themselves
                $trainerId = $callerId;
                error_log("INFO: Database fallback - using caller ID as trainer ID: $trainerId");
            }
        } else {
            error_log("ERROR: No trainerId AND no callerId - cannot determine trainer");
            http_response_code(400);
            echo json_encode(['error' => 'Trainer ID required and could not be determined']);
            exit;
        }
    } else {
        error_log("INFO: Using trainerId from hidden field: $trainerId");
    }

    // Get all participants from database - trainer and their assigned trainees
    $participants = [];

    // Add the trainer
    $participants[] = $trainerId;

    // Get assigned trainees for this trainer
    $query = "SELECT TraineeID FROM volunteers WHERE UserName = ? AND TraineeID IS NOT NULL AND TraineeID != ''";
    $result = dataQuery($query, [$trainerId]);

    if ($result && count($result) > 0) {
        // FIXED: Use object property access instead of array access
        $traineeIds = explode(',', $result[0]->TraineeID);
        error_log("INFO: Found trainees for trainer $trainerId: " . $result[0]->TraineeID);
        foreach ($traineeIds as $traineeId) {
            $traineeId = trim($traineeId);
            if ($traineeId) {
                $participants[] = $traineeId;
            }
        }
    } else {
        error_log("INFO: No trainees found for trainer $trainerId");
    }

    error_log("INFO: Participants to notify of call end: " . implode(', ', $participants));

    // Ensure Signals directory exists
    $signalsDir = __DIR__ . '/Signals';
    if (!is_dir($signalsDir)) {
        error_log("WARNING: Signals directory does not exist, creating it: $signalsDir");
        mkdir($signalsDir, 0755, true);
    }

    // For call end, notify EVERYONE (including active controller) to unmute
    // since the call is over and normal conference should resume

    // Send notification to all participants to unmute themselves after external call
    $notifiedCount = 0;
    foreach ($participants as $participantId) {
        $callNotification = [
            'type' => 'external-call-end',
            'activeController' => $activeController,
            'trainerId' => $trainerId,
            'timestamp' => microtime(true)
        ];

        $participantFile = $signalsDir . '/participant_' . $participantId . '.txt';

        // Append to existing signals
        $existingData = '';
        if (file_exists($participantFile)) {
            $existingData = file_get_contents($participantFile);
        }

        $newData = json_encode($callNotification) . "_MULTIPLEVENTS_" . $existingData;
        $writeResult = file_put_contents($participantFile, $newData);

        if ($writeResult === false) {
            error_log("ERROR: Failed to write notification file for participant: $participantId");
        } else {
            $notifiedCount++;
            error_log("INFO: Notified participant $participantId of external call end");
        }
    }

    error_log("INFO: Successfully notified $notifiedCount participants of external call end");

    echo json_encode([
        'success' => true,
        'message' => 'Notified ' . $notifiedCount . ' participants of external call end',
        'activeController' => $activeController,
        'participantsNotified' => $participants
    ]);
    
} catch (Exception $e) {
    error_log("CRITICAL ERROR in notifyCallEnd: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to notify call end: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>