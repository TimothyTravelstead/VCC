<?php
/**
 * roomJoin.php - Join a training room
 *
 * Creates the room if it doesn't exist (for trainers).
 * Adds participant to room and notifies others.
 *
 * Requires authentication.
 *
 * POST Parameters (JSON or form):
 * - roomId: (optional) Room to join, auto-detected if not provided
 * - callSid: (optional) Twilio CallSid for conference participant
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

// Parse input (supports both JSON and form data)
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    $data = $_POST;
}

$roomId = $data['roomId'] ?? null;
$callSid = $data['callSid'] ?? null;

// Determine participant role
$volunteerInfo = dataQuery(
    "SELECT LoggedOn, TraineeID FROM volunteers WHERE UserName = ?",
    [$participantId]
);

if (!$volunteerInfo || empty($volunteerInfo)) {
    http_response_code(400);
    echo json_encode(['error' => 'Volunteer not found']);
    exit;
}

$loggedOn = $volunteerInfo[0]->LoggedOn;

// Determine role and room
if ($loggedOn == 4) {
    // Trainer - they create/own the room
    $participantRole = 'trainer';
    $roomId = $roomId ?? $participantId; // Trainer's room is their own ID
} elseif ($loggedOn == 6) {
    // Trainee - must join trainer's room
    $participantRole = 'trainee';

    if (!$roomId) {
        // Find their trainer
        $trainerResult = dataQuery(
            "SELECT UserName FROM volunteers
             WHERE FIND_IN_SET(?, TraineeID) > 0 AND LoggedOn = 4",
            [$participantId]
        );
        if ($trainerResult && !empty($trainerResult)) {
            $roomId = $trainerResult[0]->UserName;
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'No active trainer found for trainee']);
            exit;
        }
    }
} else {
    http_response_code(403);
    echo json_encode(['error' => 'User is not in training mode']);
    exit;
}

// Create room if trainer, or verify it exists for trainee
// NOTE: Signal clearing removed - session versioning handles stale signals structurally
$sessionVersion = null;

if ($participantRole === 'trainer') {
    // createRoom generates a new session_version, making old signals invalid
    $sessionVersion = TrainingDB::createRoom($roomId, $participantId);

    if ($sessionVersion === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create room']);
        exit;
    }

    // Initialize session state
    TrainingDB::initSessionState($participantId, $participantId);

    error_log("roomJoin: Trainer $participantId created room $roomId with version $sessionVersion");
} else {
    // Verify room exists and is active
    $room = TrainingDB::getRoom($roomId);
    if (!$room || $room->status !== 'active') {
        http_response_code(404);
        echo json_encode(['error' => 'Training room not found or inactive']);
        exit;
    }
    $sessionVersion = $room->session_version;
}

// Add participant to room (idempotent - returns whether this was a new join)
$joinResult = TrainingDB::addParticipant($roomId, $participantId, $participantRole, $callSid);

if (!$joinResult['success']) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to join room']);
    exit;
}

// Update room activity timestamp (heartbeat on join)
TrainingDB::touchRoom($roomId);

// Only notify and log if this is a NEW join (idempotent operation)
// Duplicate joins don't broadcast - this eliminates duplicate signal race conditions
if ($joinResult['isNewJoin']) {
    SignalQueue::broadcastParticipantJoined($roomId, $participantId, $participantRole);

    TrainingDB::logEvent($roomId, 'participant_joined', [
        'participantId' => $participantId,
        'role' => $participantRole,
        'callSid' => $callSid
    ], $participantId);

    error_log("roomJoin: New participant $participantId joined $roomId");
} else {
    error_log("roomJoin: Participant $participantId already in $roomId (idempotent rejoin)");
}

// Get current participants for response
$participants = TrainingDB::getParticipants($roomId);

// Get session state
$sessionState = TrainingDB::getSessionState($roomId);

echo json_encode([
    'success' => true,
    'roomId' => $roomId,
    'participantId' => $participantId,
    'role' => $participantRole,
    'sessionVersion' => $sessionVersion,  // Client uses this to filter stale signals
    'isNewJoin' => $joinResult['isNewJoin'],
    'participants' => array_map(function ($p) {
        return [
            'id' => $p->participant_id,
            'role' => $p->participant_role,
            'connected' => (bool) $p->is_connected
        ];
    }, $participants),
    'sessionState' => $sessionState ? [
        'state' => $sessionState->session_state,
        'activeController' => $sessionState->active_controller,
        'externalCallActive' => (bool) $sessionState->external_call_active
    ] : null
]);
