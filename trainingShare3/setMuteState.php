<?php
/**
 * setMuteState.php - Server-authoritative mute state management
 *
 * Sets mute state in database AND calls Twilio REST API to enforce it.
 * This is the single source of truth for mute state.
 *
 * Requires authentication.
 *
 * POST Parameters (JSON):
 * - trainerId: Trainer username (conference identifier)
 * - participantId: Participant to mute/unmute
 * - muted: Boolean - should be muted?
 * - callSid: (optional) Participant's Twilio CallSid
 * - reason: (optional) Reason for muting (external_call, control_change, etc.)
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
$participantId = $data['participantId'] ?? null;
$shouldMute = isset($data['muted']) ? (bool) $data['muted'] : null;
$callSid = $data['callSid'] ?? null;
$reason = $data['reason'] ?? null;

// Validate required fields
if (!$trainerId || !$participantId || $shouldMute === null) {
    http_response_code(400);
    echo json_encode(['error' => 'trainerId, participantId, and muted are required']);
    exit;
}

error_log("🔇 setMuteState: trainerId=$trainerId, participantId=$participantId, muted=" . ($shouldMute ? 'true' : 'false') . ", reason=$reason");

// 1. Update database state
$dbSuccess = TrainingDB::setMuteState($trainerId, $participantId, $shouldMute, $callSid, $reason);

if (!$dbSuccess) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update mute state in database']);
    exit;
}

// 2. Call Twilio API to enforce the mute
$twilioSuccess = false;
$twilioError = null;

// Try to get callSid from database if not provided
if (!$callSid) {
    $participant = TrainingDB::getParticipant($trainerId, $participantId);
    if ($participant && $participant->call_sid) {
        $callSid = $participant->call_sid;
    }

    // Also check mute_state table
    if (!$callSid) {
        $muteState = TrainingDB::getMuteState($trainerId, $participantId);
        if ($muteState && $muteState->call_sid) {
            $callSid = $muteState->call_sid;
        }
    }
}

if ($callSid) {
    try {
        // Get Twilio credentials (Twilio SDK loaded at top of file)
        global $accountSid, $authToken;

        if (!empty($accountSid) && !empty($authToken)) {
            $client = new Client($accountSid, $authToken);

            // Look up conference by FriendlyName (trainer ID)
            $conferences = $client->conferences->read([
                'friendlyName' => $trainerId,
                'status' => 'in-progress'
            ]);

            if (!empty($conferences)) {
                $conferenceSid = $conferences[0]->sid;

                // Update participant mute status via Twilio API
                try {
                    $client->conferences($conferenceSid)
                        ->participants($callSid)
                        ->update(['muted' => $shouldMute]);

                    $twilioSuccess = true;

                    // Log the mute action
                    $event = $shouldMute ? 'app_mute' : 'app_unmute';
                    dataQuery(
                        "INSERT INTO TwilioStatusLog
                         (CallSid, ConferenceSid, FriendlyName, StatusCallbackEvent, CallStatus, RawRequest)
                         VALUES (?, ?, ?, ?, 'in-progress', ?)",
                        [
                            $callSid,
                            $conferenceSid,
                            $trainerId,
                            $event,
                            json_encode([
                                'source' => 'setMuteState.php',
                                'participantId' => $participantId,
                                'reason' => $reason,
                                'initiator' => $currentUserId
                            ])
                        ]
                    );

                    error_log("🔇 setMuteState: Twilio API updated - $participantId " . ($shouldMute ? 'muted' : 'unmuted'));

                } catch (Exception $e) {
                    // Participant might not be in conference anymore
                    $twilioError = $e->getMessage();
                    error_log("🔇 setMuteState: Twilio API error - " . $e->getMessage());

                    // Check if it's a "not found" error (participant left)
                    if (strpos($e->getMessage(), '404') !== false || strpos($e->getMessage(), 'not found') !== false) {
                        // Clear the callSid since participant is no longer in conference
                        TrainingDB::setMuteState($trainerId, $participantId, $shouldMute, null, $reason);
                    }
                }
            } else {
                $twilioError = 'Conference not found or not in progress';
                error_log("🔇 setMuteState: Conference '$trainerId' not found");
            }
        } else {
            $twilioError = 'Twilio credentials not configured';
        }
    } catch (Exception $e) {
        $twilioError = $e->getMessage();
        error_log("🔇 setMuteState: Exception - " . $e->getMessage());
    }
} else {
    $twilioError = 'No CallSid available for participant';
    error_log("🔇 setMuteState: No CallSid for $participantId - database updated but Twilio not called");
}

// 3. Broadcast mute state change to other participants
SignalQueue::broadcastMuteState($trainerId, $currentUserId, $participantId, $shouldMute, $reason);

// 4. Log the event
TrainingDB::logEvent($trainerId, $shouldMute ? 'participant_muted' : 'participant_unmuted', [
    'participantId' => $participantId,
    'reason' => $reason,
    'twilioSuccess' => $twilioSuccess,
    'twilioError' => $twilioError
], $currentUserId);

// Return response
echo json_encode([
    'success' => true,
    'trainerId' => $trainerId,
    'participantId' => $participantId,
    'muted' => $shouldMute,
    'reason' => $reason,
    'twilioUpdated' => $twilioSuccess,
    'twilioError' => $twilioError
]);
