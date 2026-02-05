<?php
/**
 * signalPollDB.php - Poll for WebRTC signals from database
 *
 * Replaces file-based pollSignals.php with database-backed signaling.
 * Uses atomic transactions for reliable signal delivery.
 *
 * Recommended polling interval: 500ms (vs old 1000ms)
 *
 * Requires authentication.
 *
 * GET Parameters:
 * - roomId: (optional) Room to poll, auto-detected if not provided
 *
 * Returns JSON:
 * {
 *   "participantId": "username",
 *   "roomId": "trainer_username",
 *   "timestamp": 1234567890.123,
 *   "messages": [ ... signal objects ... ]
 * }
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

// Get room ID from request or determine from session
$roomId = $_GET['roomId'] ?? null;

if (!$roomId) {
    // Try to determine room from participant's current session
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
}

// Determine role
$roleResult = dataQuery(
    "SELECT LoggedOn FROM volunteers WHERE UserName = ?",
    [$participantId]
);
$participantRole = 'trainee';
if ($roleResult && !empty($roleResult)) {
    $participantRole = ($roleResult[0]->LoggedOn == 4) ? 'trainer' : 'trainee';
}

// Update participant's last_seen timestamp (heartbeat)
if ($roomId) {
    TrainingDB::touchParticipant($roomId, $participantId);
}

// Get session version for filtering stale signals
// This is the key structural protection - signals from old sessions are invisible
$sessionVersion = $roomId ? TrainingDB::getSessionVersion($roomId) : null;

// Get pending signals using atomic read, filtered by session version
$signals = SignalQueue::getSignals($participantId, 50, $sessionVersion);

// Format response
$response = [
    'participantId' => $participantId,
    'role' => $participantRole,
    'roomId' => $roomId,
    'sessionVersion' => $sessionVersion,  // Client can verify it has correct version
    'timestamp' => microtime(true),
    'messages' => []
];

// Convert signals to message format expected by client
foreach ($signals as $signal) {
    $message = $signal->signal_data;
    if (!is_array($message)) {
        $message = [];
    }

    // Add metadata
    $message['type'] = $signal->signal_type;
    $message['from'] = $signal->sender_id;
    $message['signalId'] = $signal->id;
    $message['sentAt'] = $signal->created_at;

    $response['messages'][] = $message;
}

// Log polling activity (for debugging)
error_log("signalPollDB: Poll by $participantId (room: $roomId, version: " . ($sessionVersion ?? 'null') . ") - " . count($response['messages']) . " messages");

// Log if we delivered signals (for debugging)
if (count($response['messages']) > 0) {
    error_log("signalPollDB: Delivered " . count($response['messages']) . " signals to $participantId: " . implode(', ', array_map(function($m) { return $m['type']; }, $response['messages'])));
}

echo json_encode($response);
