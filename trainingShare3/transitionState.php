<?php
/**
 * transitionState.php - Training session state machine transitions
 *
 * Manages formal state transitions for training sessions:
 * - INITIALIZING: Session starting
 * - CONNECTED: Normal training, all in conference
 * - ON_CALL: External caller in conference
 * - RECONNECTING: Conference restart in progress
 * - DISCONNECTED: Session ended
 *
 * Transitions:
 * - INITIALIZING -> CONNECTED (trainer joined)
 * - CONNECTED -> ON_CALL (external call started)
 * - ON_CALL -> RECONNECTING (external call ended)
 * - RECONNECTING -> CONNECTED (restart complete)
 * - * -> DISCONNECTED (trainer logout or error)
 *
 * Requires authentication.
 *
 * POST Parameters (JSON):
 * - trainerId: Trainer username
 * - event: Event triggering transition (trainer_join, call_start, call_end, restart_complete, trainer_logout, error)
 * - callSid: (optional) External call SID for call_start
 * - conferenceSid: (optional) Conference SID
 * - controllerId: (optional) New controller ID for control changes
 */

require_once('../../private_html/db_login.php');
session_start();
requireAuth();

// Get session data then release lock
$currentUserId = $_SESSION['UserID'];
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
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$trainerId = $data['trainerId'] ?? null;
$event = $data['event'] ?? null;
$callSid = $data['callSid'] ?? null;
$conferenceSid = $data['conferenceSid'] ?? null;
$controllerId = $data['controllerId'] ?? null;

// Validate required fields
if (!$trainerId || !$event) {
    http_response_code(400);
    echo json_encode(['error' => 'trainerId and event are required']);
    exit;
}

// Get current state
$currentState = TrainingDB::getSessionState($trainerId);
$currentStateName = $currentState ? $currentState->session_state : null;

error_log("🔄 transitionState: trainerId=$trainerId, event=$event, currentState=$currentStateName");

// Define valid transitions
$transitions = [
    // INITIALIZING can transition to CONNECTED when trainer joins
    'INITIALIZING' => [
        'trainer_join' => 'CONNECTED',
        'trainer_logout' => 'DISCONNECTED',
        'error' => 'DISCONNECTED'
    ],
    // CONNECTED can transition to ON_CALL when external call starts
    'CONNECTED' => [
        'call_start' => 'ON_CALL',
        'trainer_logout' => 'DISCONNECTED',
        'error' => 'DISCONNECTED'
    ],
    // ON_CALL can transition to RECONNECTING when call ends
    'ON_CALL' => [
        'call_end' => 'RECONNECTING',
        'trainer_logout' => 'DISCONNECTED',
        'error' => 'DISCONNECTED'
    ],
    // RECONNECTING can transition to CONNECTED when restart is complete
    'RECONNECTING' => [
        'restart_complete' => 'CONNECTED',
        'trainer_logout' => 'DISCONNECTED',
        'error' => 'DISCONNECTED'
    ],
    // DISCONNECTED is final (can reinitialize)
    'DISCONNECTED' => [
        'trainer_join' => 'INITIALIZING'
    ]
];

// Handle the case where no state exists (new session)
if (!$currentState) {
    if ($event === 'trainer_join') {
        // Initialize new session
        TrainingDB::initSessionState($trainerId, $controllerId ?? $trainerId);
        TrainingDB::transitionState($trainerId, 'CONNECTED');

        $newState = 'CONNECTED';
        $sideEffect = 'session_initialized';

        // Log event
        TrainingDB::logEvent($trainerId, 'state_transition', [
            'from' => null,
            'to' => $newState,
            'event' => $event,
            'sideEffect' => $sideEffect
        ], $currentUserId);

        echo json_encode([
            'success' => true,
            'trainerId' => $trainerId,
            'previousState' => null,
            'newState' => $newState,
            'event' => $event,
            'sideEffect' => $sideEffect
        ]);
        exit;
    } else {
        http_response_code(400);
        echo json_encode([
            'error' => 'No session state exists. Use trainer_join event to initialize.',
            'trainerId' => $trainerId
        ]);
        exit;
    }
}

// Check if transition is valid
if (!isset($transitions[$currentStateName][$event])) {
    http_response_code(400);
    echo json_encode([
        'error' => "Invalid transition: cannot go from '$currentStateName' via event '$event'",
        'currentState' => $currentStateName,
        'event' => $event,
        'validEvents' => array_keys($transitions[$currentStateName] ?? [])
    ]);
    exit;
}

$newState = $transitions[$currentStateName][$event];

// Execute side effects based on transition
$sideEffect = null;

switch ($event) {
    case 'call_start':
        // Set external call state
        TrainingDB::setExternalCallState($trainerId, true, $callSid, $conferenceSid);

        // Mute all non-controllers
        require_once(__DIR__ . '/lib/TrainingDB.php');
        $activeController = $controllerId ?? $currentState->active_controller;
        TrainingDB::bulkMuteNonControllers($trainerId, $activeController, true, 'external_call');

        // Broadcast call start notification
        SignalQueue::broadcastCallStart($trainerId, $currentUserId, $callSid);

        $sideEffect = 'muted_non_controllers';
        break;

    case 'call_end':
        // Clear external call state
        TrainingDB::setExternalCallState($trainerId, false, null, null);

        // Broadcast call end notification
        SignalQueue::broadcastCallEnd($trainerId, $currentUserId);

        $sideEffect = 'call_cleared';
        break;

    case 'restart_complete':
        // Unmute all participants
        TrainingDB::bulkMuteNonControllers($trainerId, '', false, null);

        // Broadcast conference restart
        SignalQueue::broadcastConferenceRestart($trainerId, $currentUserId);

        $sideEffect = 'unmuted_all';
        break;

    case 'trainer_logout':
        // Clean up entire session
        TrainingDB::cleanupSession($trainerId);

        // Broadcast session end
        SignalQueue::broadcastToRoom($trainerId, $currentUserId, 'session-end', [
            'reason' => 'trainer_logout'
        ]);

        $sideEffect = 'session_cleaned_up';
        break;

    case 'error':
        // Log error but don't clean up (might recover)
        $sideEffect = 'error_logged';
        break;
}

// Execute the state transition
$transitioned = TrainingDB::transitionState($trainerId, $newState);

if (!$transitioned) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update state in database']);
    exit;
}

// Update controller if provided
if ($controllerId && $event !== 'trainer_logout') {
    TrainingDB::setActiveController($trainerId, $controllerId);
}

// Log event
TrainingDB::logEvent($trainerId, 'state_transition', [
    'from' => $currentStateName,
    'to' => $newState,
    'event' => $event,
    'callSid' => $callSid,
    'sideEffect' => $sideEffect
], $currentUserId);

error_log("🔄 transitionState: $trainerId: $currentStateName -> $newState (event: $event, sideEffect: $sideEffect)");

// Return response
echo json_encode([
    'success' => true,
    'trainerId' => $trainerId,
    'previousState' => $currentStateName,
    'newState' => $newState,
    'event' => $event,
    'sideEffect' => $sideEffect,
    'activeController' => $controllerId ?? $currentState->active_controller
]);
