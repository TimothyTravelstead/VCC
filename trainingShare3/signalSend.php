<?php
/**
 * signalSend.php - Send WebRTC signals via database
 *
 * Replaces file-based signalingServerMulti.php POST handling.
 * Uses database for reliable, atomic signal delivery.
 *
 * Requires authentication.
 *
 * POST Parameters (JSON body):
 * - type: Signal type (offer, answer, ice-candidate, etc.)
 * - to: (optional) Recipient ID for direct messages, omit for broadcast
 * - ...signal data
 */

require_once('../../private_html/db_login.php');
session_start();
requireAuth();

// Get session data then release lock
$participantId = $_SESSION['UserID'];
session_write_close();

require_once(__DIR__ . '/lib/TrainingDB.php');
require_once(__DIR__ . '/lib/SignalQueue.php');

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse JSON body
$input = file_get_contents('php://input');
$signal = json_decode($input, true);

if (!$signal || !isset($signal['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signal format - type required']);
    exit;
}

// Get room ID from request or determine from session
$roomId = $signal['roomId'] ?? null;

if (!$roomId) {
    // Try to determine room from participant's current session
    // For trainees, room is trainer's ID; for trainers, room is their own ID
    $volunteerInfo = dataQuery(
        "SELECT LoggedOn, TraineeID FROM volunteers WHERE UserName = ?",
        [$participantId]
    );

    if ($volunteerInfo && !empty($volunteerInfo)) {
        $loggedOn = $volunteerInfo[0]->LoggedOn;

        if ($loggedOn == 4) {
            // Trainer - room is their own ID
            $roomId = $participantId;
        } elseif ($loggedOn == 6) {
            // Trainee - need to find their trainer
            $trainerResult = dataQuery(
                "SELECT UserName FROM volunteers
                 WHERE FIND_IN_SET(?, TraineeID) > 0 AND LoggedOn = 4",
                [$participantId]
            );
            if ($trainerResult && !empty($trainerResult)) {
                $roomId = $trainerResult[0]->UserName;
            }
        }
    }
}

if (!$roomId) {
    http_response_code(400);
    echo json_encode(['error' => 'Could not determine room ID']);
    exit;
}

// Determine participant role
$roleResult = dataQuery(
    "SELECT LoggedOn FROM volunteers WHERE UserName = ?",
    [$participantId]
);
$participantRole = 'trainee';
if ($roleResult && !empty($roleResult)) {
    $participantRole = ($roleResult[0]->LoggedOn == 4) ? 'trainer' : 'trainee';
}

// Update room activity
TrainingDB::touchRoom($roomId);

// Update participant last_seen
TrainingDB::touchParticipant($roomId, $participantId);

// Extract signal data (remove metadata fields)
$signalType = $signal['type'];
$recipientId = $signal['to'] ?? null;

// Remove routing fields from signal data
$signalData = $signal;
unset($signalData['type']);
unset($signalData['to']);
unset($signalData['roomId']);

// Add sender info (server-authoritative)
$signalData['from'] = $participantId;
$signalData['fromRole'] = $participantRole;
$signalData['timestamp'] = microtime(true);

// Handle different signal types
$result = false;

switch ($signalType) {
    case 'leave-room':
        // Remove participant
        TrainingDB::removeParticipant($roomId, $participantId);

        // Clear their pending signals
        SignalQueue::clearParticipantSignals($participantId);

        // Broadcast to others
        $result = SignalQueue::broadcastParticipantLeft($roomId, $participantId);

        // Log event
        TrainingDB::logEvent($roomId, 'participant_left', [
            'participantId' => $participantId
        ], $participantId);
        break;

    case 'offer':
    case 'answer':
    case 'ice-candidate':
        // WebRTC signaling - must have recipient
        if (!$recipientId) {
            http_response_code(400);
            echo json_encode(['error' => 'WebRTC signals require recipient (to field)']);
            exit;
        }
        $result = SignalQueue::sendToParticipant($roomId, $participantId, $recipientId, $signalType, $signalData);
        break;

    case 'screen-share-start':
        $result = SignalQueue::broadcastScreenShareStart($roomId, $participantId);
        TrainingDB::logEvent($roomId, 'screen_share_start', null, $participantId);
        break;

    case 'screen-share-stop':
        $result = SignalQueue::broadcastScreenShareStop($roomId, $participantId);
        TrainingDB::logEvent($roomId, 'screen_share_stop', null, $participantId);
        break;

    case 'control-change':
        $newController = $signalData['newController'] ?? null;
        if ($newController) {
            $result = SignalQueue::broadcastControlChange($roomId, $participantId, $newController);
            TrainingDB::logEvent($roomId, 'control_change', [
                'from' => $participantId,
                'to' => $newController
            ], $participantId);
        }
        break;

    case 'call-start':
        $callSid = $signalData['callSid'] ?? null;
        $result = SignalQueue::broadcastCallStart($roomId, $participantId, $callSid);
        TrainingDB::logEvent($roomId, 'call_start', ['callSid' => $callSid], $participantId);
        break;

    case 'call-end':
        $result = SignalQueue::broadcastCallEnd($roomId, $participantId);
        TrainingDB::logEvent($roomId, 'call_end', null, $participantId);
        break;

    case 'conference-restart':
        // Pass through full signal data so activeController and newConferenceId reach clients
        $result = SignalQueue::broadcastToRoom($roomId, $participantId, 'conference-restart', $signalData);
        TrainingDB::logEvent($roomId, 'conference_restart', [
            'activeController' => $signalData['activeController'] ?? null,
            'newConferenceId' => $signalData['newConferenceId'] ?? null
        ], $participantId);
        break;

    default:
        // Generic signal - broadcast or send directly
        if ($recipientId) {
            $result = SignalQueue::sendToParticipant($roomId, $participantId, $recipientId, $signalType, $signalData);
        } else {
            $result = SignalQueue::broadcastToRoom($roomId, $participantId, $signalType, $signalData);
        }
}

// Return response
if ($result !== false) {
    echo json_encode([
        'success' => true,
        'type' => $signalType,
        'roomId' => $roomId,
        'timestamp' => microtime(true)
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to send signal',
        'type' => $signalType
    ]);
}
