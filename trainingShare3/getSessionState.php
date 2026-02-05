<?php
/**
 * getSessionState.php - Get current training session state
 *
 * Returns the current state of a training session including:
 * - Session state (INITIALIZING, CONNECTED, ON_CALL, RECONNECTING, DISCONNECTED)
 * - Active controller
 * - External call status
 * - All participants with their status
 * - Mute states
 *
 * Requires authentication.
 *
 * GET Parameters:
 * - trainerId: (optional) Trainer username, auto-detected if not provided
 */

require_once('../../private_html/db_login.php');
session_start();
requireAuth();

// Get session data then release lock
$currentUserId = $_SESSION['UserID'];
session_write_close();

require_once(__DIR__ . '/lib/TrainingDB.php');

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$trainerId = $_GET['trainerId'] ?? null;

// Auto-detect trainer ID if not provided
if (!$trainerId) {
    $volunteerInfo = dataQuery(
        "SELECT LoggedOn FROM volunteers WHERE UserName = ?",
        [$currentUserId]
    );

    if ($volunteerInfo && !empty($volunteerInfo)) {
        $loggedOn = $volunteerInfo[0]->LoggedOn;

        if ($loggedOn == 4) {
            // Current user is a trainer
            $trainerId = $currentUserId;
        } elseif ($loggedOn == 6) {
            // Current user is a trainee - find their trainer
            $trainerResult = dataQuery(
                "SELECT UserName FROM volunteers
                 WHERE FIND_IN_SET(?, TraineeID) > 0 AND LoggedOn = 4",
                [$currentUserId]
            );
            if ($trainerResult && !empty($trainerResult)) {
                $trainerId = $trainerResult[0]->UserName;
            }
        }
    }
}

if (!$trainerId) {
    http_response_code(400);
    echo json_encode(['error' => 'Could not determine trainer ID']);
    exit;
}

// Get session state
$sessionState = TrainingDB::getSessionState($trainerId);

if (!$sessionState) {
    // No session state - might be initializing
    echo json_encode([
        'trainerId' => $trainerId,
        'exists' => false,
        'state' => null,
        'message' => 'No active training session found'
    ]);
    exit;
}

// Get room info
$room = TrainingDB::getRoom($trainerId);

// Get participants
$participants = TrainingDB::getParticipants($trainerId);

// Get mute states
$muteStates = TrainingDB::getAllMuteStates($trainerId);
$muteMap = [];
foreach ($muteStates as $ms) {
    $muteMap[$ms->participant_id] = [
        'isMuted' => (bool) $ms->is_muted,
        'reason' => $ms->mute_reason,
        'callSid' => $ms->call_sid
    ];
}

// Build participant list with mute states
$participantList = [];
foreach ($participants as $p) {
    $mute = $muteMap[$p->participant_id] ?? ['isMuted' => false, 'reason' => null, 'callSid' => null];

    $participantList[] = [
        'id' => $p->participant_id,
        'role' => $p->participant_role,
        'callSid' => $p->call_sid ?? $mute['callSid'],
        'connected' => (bool) $p->is_connected,
        'joinedAt' => $p->joined_at,
        'lastSeen' => $p->last_seen,
        'isMuted' => $mute['isMuted'],
        'muteReason' => $mute['reason'],
        'isController' => ($p->participant_id === $sessionState->active_controller)
    ];
}

// Determine current user's role and status
$currentUserParticipant = null;
foreach ($participantList as $p) {
    if ($p['id'] === $currentUserId) {
        $currentUserParticipant = $p;
        break;
    }
}

// Return full state
echo json_encode([
    'trainerId' => $trainerId,
    'exists' => true,
    'state' => $sessionState->session_state,
    'activeController' => $sessionState->active_controller,
    'externalCall' => [
        'active' => (bool) $sessionState->external_call_active,
        'callSid' => $sessionState->external_call_sid,
        'conferenceSid' => $sessionState->conference_sid
    ],
    'stateChangedAt' => $sessionState->state_changed_at,
    'room' => $room ? [
        'id' => $room->room_id,
        'status' => $room->status,
        'createdAt' => $room->created_at,
        'lastActivity' => $room->last_activity
    ] : null,
    'participants' => $participantList,
    'participantCount' => count($participantList),
    'currentUser' => $currentUserParticipant ? [
        'id' => $currentUserParticipant['id'],
        'role' => $currentUserParticipant['role'],
        'isController' => $currentUserParticipant['isController'],
        'isMuted' => $currentUserParticipant['isMuted']
    ] : null,
    'shouldBeMuted' => $currentUserParticipant
        ? ($sessionState->external_call_active && !$currentUserParticipant['isController'])
        : false
]);
