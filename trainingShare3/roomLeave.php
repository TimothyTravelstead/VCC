<?php
/**
 * roomLeave.php - Leave a training room
 *
 * Removes participant from room, notifies others, and cleans up signals.
 * If trainer leaves, closes the entire room.
 *
 * Requires authentication.
 *
 * POST Parameters (JSON or form):
 * - roomId: (optional) Room to leave, auto-detected if not provided
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

// Determine participant role
$volunteerInfo = dataQuery(
    "SELECT LoggedOn FROM volunteers WHERE UserName = ?",
    [$participantId]
);

$loggedOn = $volunteerInfo[0]->LoggedOn ?? 0;
$participantRole = ($loggedOn == 4) ? 'trainer' : 'trainee';

// Auto-detect room if not provided
if (!$roomId) {
    if ($loggedOn == 4) {
        // Trainer - room is their own ID
        $roomId = $participantId;
    } elseif ($loggedOn == 6) {
        // Trainee - find their trainer
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

if (!$roomId) {
    http_response_code(400);
    echo json_encode(['error' => 'Could not determine room ID']);
    exit;
}

// Log the leave event before cleanup
TrainingDB::logEvent($roomId, 'participant_left', [
    'participantId' => $participantId,
    'role' => $participantRole
], $participantId);

// Notify other participants BEFORE removing
SignalQueue::broadcastParticipantLeft($roomId, $participantId);

// Remove participant from room
TrainingDB::removeParticipant($roomId, $participantId);

// Clear participant's pending signals
SignalQueue::clearParticipantSignals($participantId);

// Clear participant's mute state
$muteResult = dataQuery(
    "DELETE FROM training_mute_state
     WHERE trainer_id = ? AND participant_id = ?",
    [$roomId, $participantId]
);

// If trainer leaves, close the entire room
if ($participantRole === 'trainer') {
    // Notify all remaining participants that session is ending
    SignalQueue::broadcastToRoom($roomId, $participantId, 'session-end', [
        'reason' => 'trainer_left'
    ]);

    // Clean up entire session
    TrainingDB::cleanupSession($roomId);

    echo json_encode([
        'success' => true,
        'action' => 'session_closed',
        'roomId' => $roomId
    ]);
    exit;
}

// For trainees, just confirm they left
echo json_encode([
    'success' => true,
    'action' => 'left_room',
    'roomId' => $roomId,
    'participantId' => $participantId
]);
