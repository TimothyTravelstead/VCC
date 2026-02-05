<?php
/**
 * bulkMute.php - Bulk mute/unmute non-controllers in training session
 *
 * Used when:
 * - External call starts: Mute all except active controller
 * - External call ends: Unmute all participants
 *
 * Requires authentication.
 *
 * POST Parameters (JSON):
 * - trainerId: Trainer username (conference identifier)
 * - action: 'mute_non_controllers' or 'unmute_all'
 * - controllerId: (optional) Active controller to keep unmuted
 * - reason: (optional) Reason for bulk mute
 */

// Load Twilio SDK early for use statement
require_once(__DIR__ . '/../twilio-php-main/src/Twilio/autoload.php');
use Twilio\Rest\Client;

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
$action = $data['action'] ?? null;
$controllerId = $data['controllerId'] ?? null;
$reason = $data['reason'] ?? null;

// Validate required fields
if (!$trainerId || !$action) {
    http_response_code(400);
    echo json_encode(['error' => 'trainerId and action are required']);
    exit;
}

// Get session state for controller info
$sessionState = TrainingDB::getSessionState($trainerId);
if (!$controllerId && $sessionState) {
    $controllerId = $sessionState->active_controller;
}

error_log("🔇 bulkMute: trainerId=$trainerId, action=$action, controllerId=$controllerId, reason=$reason");

// Twilio SDK already loaded at top of file
global $accountSid, $authToken;

$twilioClient = null;
$conferenceSid = null;

if (!empty($accountSid) && !empty($authToken)) {
    try {
        $twilioClient = new Client($accountSid, $authToken);

        // Look up conference
        $conferences = $twilioClient->conferences->read([
            'friendlyName' => $trainerId,
            'status' => 'in-progress'
        ]);

        if (!empty($conferences)) {
            $conferenceSid = $conferences[0]->sid;
        }
    } catch (Exception $e) {
        error_log("🔇 bulkMute: Twilio client error - " . $e->getMessage());
    }
}

// Get all participants
$participants = TrainingDB::getParticipants($trainerId);

$results = [];
$mutedCount = 0;
$unmutedCount = 0;
$errors = [];

if ($action === 'mute_non_controllers') {
    // Mute everyone except the controller
    foreach ($participants as $participant) {
        $isController = ($participant->participant_id === $controllerId);
        $shouldMute = !$isController;

        // Update database
        TrainingDB::setMuteState(
            $trainerId,
            $participant->participant_id,
            $shouldMute,
            $participant->call_sid,
            $shouldMute ? ($reason ?? 'external_call') : null
        );

        // Call Twilio API if we have a conference and CallSid
        if ($twilioClient && $conferenceSid && $participant->call_sid) {
            try {
                $twilioClient->conferences($conferenceSid)
                    ->participants($participant->call_sid)
                    ->update(['muted' => $shouldMute]);

                // Log to TwilioStatusLog
                $event = $shouldMute ? 'app_mute' : 'app_unmute';
                dataQuery(
                    "INSERT INTO TwilioStatusLog
                     (CallSid, ConferenceSid, FriendlyName, StatusCallbackEvent, CallStatus, RawRequest)
                     VALUES (?, ?, ?, ?, 'in-progress', ?)",
                    [
                        $participant->call_sid,
                        $conferenceSid,
                        $trainerId,
                        $event,
                        json_encode([
                            'source' => 'bulkMute.php',
                            'action' => $action,
                            'participantId' => $participant->participant_id,
                            'reason' => $reason,
                            'isController' => $isController
                        ])
                    ]
                );

            } catch (Exception $e) {
                $errors[] = [
                    'participantId' => $participant->participant_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        if ($shouldMute) {
            $mutedCount++;
        }

        $results[] = [
            'participantId' => $participant->participant_id,
            'isController' => $isController,
            'muted' => $shouldMute
        ];
    }

    // Broadcast mute state changes
    SignalQueue::broadcastToRoom($trainerId, $currentUserId, 'bulk-mute', [
        'action' => 'mute_non_controllers',
        'controllerId' => $controllerId,
        'reason' => $reason
    ]);

} elseif ($action === 'unmute_all') {
    // Unmute everyone
    foreach ($participants as $participant) {
        // Update database
        TrainingDB::setMuteState(
            $trainerId,
            $participant->participant_id,
            false,
            $participant->call_sid,
            null
        );

        // Call Twilio API
        if ($twilioClient && $conferenceSid && $participant->call_sid) {
            try {
                $twilioClient->conferences($conferenceSid)
                    ->participants($participant->call_sid)
                    ->update(['muted' => false]);

                // Log to TwilioStatusLog
                dataQuery(
                    "INSERT INTO TwilioStatusLog
                     (CallSid, ConferenceSid, FriendlyName, StatusCallbackEvent, CallStatus, RawRequest)
                     VALUES (?, ?, ?, ?, 'in-progress', ?)",
                    [
                        $participant->call_sid,
                        $conferenceSid,
                        $trainerId,
                        'app_unmute',
                        json_encode([
                            'source' => 'bulkMute.php',
                            'action' => $action,
                            'participantId' => $participant->participant_id,
                            'reason' => $reason
                        ])
                    ]
                );

            } catch (Exception $e) {
                $errors[] = [
                    'participantId' => $participant->participant_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        $unmutedCount++;

        $results[] = [
            'participantId' => $participant->participant_id,
            'muted' => false
        ];
    }

    // Broadcast unmute
    SignalQueue::broadcastToRoom($trainerId, $currentUserId, 'bulk-mute', [
        'action' => 'unmute_all',
        'reason' => $reason
    ]);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action. Use mute_non_controllers or unmute_all']);
    exit;
}

// Log event
TrainingDB::logEvent($trainerId, 'bulk_mute', [
    'action' => $action,
    'controllerId' => $controllerId,
    'mutedCount' => $mutedCount,
    'unmutedCount' => $unmutedCount,
    'errors' => count($errors)
], $currentUserId);

// Return response
echo json_encode([
    'success' => true,
    'trainerId' => $trainerId,
    'action' => $action,
    'controllerId' => $controllerId,
    'mutedCount' => $mutedCount,
    'unmutedCount' => $unmutedCount,
    'participants' => $results,
    'errors' => $errors,
    'conferenceActive' => !empty($conferenceSid)
]);
