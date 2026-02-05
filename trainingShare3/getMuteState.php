<?php
/**
 * getMuteState.php - Get mute state for training session participants
 *
 * Returns server-authoritative mute states from database.
 * Client should poll this periodically to sync local state.
 *
 * Requires authentication.
 *
 * GET Parameters:
 * - trainerId: Trainer username (conference identifier)
 * - participantId: (optional) Get state for specific participant only
 *
 * Returns JSON:
 * {
 *   "trainerId": "...",
 *   "participants": [
 *     { "participantId": "...", "isMuted": true/false, "reason": "...", "updatedAt": "..." }
 *   ]
 * }
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
$participantId = $_GET['participantId'] ?? null;

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

// Get mute states
if ($participantId) {
    // Get state for specific participant
    $muteState = TrainingDB::getMuteState($trainerId, $participantId);

    if ($muteState) {
        echo json_encode([
            'trainerId' => $trainerId,
            'participantId' => $participantId,
            'isMuted' => (bool) $muteState->is_muted,
            'callSid' => $muteState->call_sid,
            'reason' => $muteState->mute_reason,
            'updatedAt' => $muteState->updated_at
        ]);
    } else {
        // No explicit state - default to unmuted
        echo json_encode([
            'trainerId' => $trainerId,
            'participantId' => $participantId,
            'isMuted' => false,
            'callSid' => null,
            'reason' => null,
            'updatedAt' => null
        ]);
    }
} else {
    // Get all mute states for the session
    $muteStates = TrainingDB::getAllMuteStates($trainerId);

    $participants = [];
    foreach ($muteStates as $state) {
        $participants[] = [
            'participantId' => $state->participant_id,
            'isMuted' => (bool) $state->is_muted,
            'callSid' => $state->call_sid,
            'reason' => $state->mute_reason,
            'updatedAt' => $state->updated_at
        ];
    }

    // Also get session state for context
    $sessionState = TrainingDB::getSessionState($trainerId);

    echo json_encode([
        'trainerId' => $trainerId,
        'participants' => $participants,
        'sessionState' => $sessionState ? [
            'state' => $sessionState->session_state,
            'activeController' => $sessionState->active_controller,
            'externalCallActive' => (bool) $sessionState->external_call_active
        ] : null
    ]);
}
