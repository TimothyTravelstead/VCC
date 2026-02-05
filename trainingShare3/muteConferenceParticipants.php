<?php
// Mute conference participants using Twilio REST API
require_once '../vendor/autoload.php';
require_once '../../private_html/db_login.php';

use Twilio\Rest\Client;

/**
 * Log application-initiated mute/unmute action to TwilioStatusLog
 * This creates a record with StatusCallbackEvent = 'app_mute' or 'app_unmute'
 * to distinguish from Twilio-reported 'mute' events
 */
function logMuteAction($conferenceId, $conferenceSid, $callSid, $action, $initiator = 'training_system') {
    $event = ($action === 'mute') ? 'app_mute' : 'app_unmute';

    $query = "INSERT INTO TwilioStatusLog (
        CallSid, ConferenceSid, FriendlyName,
        StatusCallbackEvent, CallStatus,
        RawRequest
    ) VALUES (?, ?, ?, ?, 'in-progress', ?)";

    $rawRequest = json_encode([
        'source' => 'muteConferenceParticipants.php',
        'initiator' => $initiator,
        'action' => $action,
        'conferenceId' => $conferenceId
    ]);

    $params = [$callSid, $conferenceSid, $conferenceId, $event, $rawRequest];

    try {
        $result = dataQuery($query, $params);
        if (is_array($result) && isset($result['error'])) {
            error_log("logMuteAction error: " . $result['message']);
        }
    } catch (Exception $e) {
        error_log("logMuteAction exception: " . $e->getMessage());
    }
}

header('Content-Type: application/json');

// Load environment variables from .env file
if (file_exists('../../private_html/.env')) {
    $lines = file('../../private_html/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($name, $value) = explode('=', $line, 2);
            putenv(trim($name) . '=' . trim($value));
        }
    }
}

// Get Twilio credentials
$accountSid = getenv('TWILIO_ACCOUNT_SID');
$authToken = getenv('TWILIO_AUTH_TOKEN');

if (empty($accountSid) || empty($authToken)) {
    http_response_code(500);
    echo json_encode(['error' => 'Twilio credentials not configured']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$conferenceId = $input['conferenceId'] ?? '';
$action = $input['action'] ?? '';
$activeController = $input['activeController'] ?? '';

if (empty($conferenceId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Conference ID required']);
    exit;
}

error_log("INFO: muteConferenceParticipants - conferenceId: '$conferenceId', action: '$action', callSid: " . ($input['callSid'] ?? 'none'));

try {
    $client = new Client($accountSid, $authToken);

    // Look up conference by FriendlyName to get the actual SID
    // The Twilio REST API requires the Conference SID, not FriendlyName
    $conferenceSid = $conferenceId; // Default if already a SID

    if (strpos($conferenceId, 'CF') !== 0) {
        // Not a SID, look up by FriendlyName
        $conferences = $client->conferences->read([
            'friendlyName' => $conferenceId,
            'status' => 'in-progress'
        ]);

        if (empty($conferences)) {
            // Conference not found or not in progress - return gracefully
            echo json_encode([
                'success' => true,
                'message' => 'Conference not active',
                'conferenceId' => $conferenceId,
                'status' => 'not_in_progress'
            ]);
            exit;
        }

        $conferenceSid = $conferences[0]->sid;
        error_log("INFO: muteConferenceParticipants - Resolved FriendlyName '$conferenceId' to SID '$conferenceSid'");
    }

    if ($action === 'mute_trainees') {
        // Legacy action - Get all participants in the conference
        $participants = $client->conferences($conferenceSid)->participants->read();

        $mutedCount = 0;
        foreach ($participants as $participant) {
            // Don't mute the moderator (first participant or one with startConferenceOnEnter)
            // We'll identify moderator by checking if they started the conference
            $participantDetails = $client->conferences($conferenceSid)
                                         ->participants($participant->callSid)
                                         ->fetch();

            // Skip if this participant is the moderator (trainer)
            // In practice, we might need to track this differently
            if (!$participant->startConferenceOnEnter) {
                // Mute this participant
                $client->conferences($conferenceSid)
                       ->participants($participant->callSid)
                       ->update(['muted' => true]);
                // Log each mute action
                logMuteAction($conferenceId, $conferenceSid, $participant->callSid, 'mute', 'mute_trainees');
                $mutedCount++;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Muted $mutedCount participants",
            'conferenceId' => $conferenceId,
            'conferenceSid' => $conferenceSid
        ]);

    } elseif ($action === 'unmute_trainees') {
        // Unmute all participants (used when external call ends)
        $participants = $client->conferences($conferenceSid)->participants->read();

        $unmutedCount = 0;
        foreach ($participants as $participant) {
            // Unmute all participants
            $client->conferences($conferenceSid)
                   ->participants($participant->callSid)
                   ->update(['muted' => false]);
            // Log each unmute action
            logMuteAction($conferenceId, $conferenceSid, $participant->callSid, 'unmute', 'unmute_trainees');
            $unmutedCount++;
        }

        echo json_encode([
            'success' => true,
            'message' => "Unmuted $unmutedCount participants",
            'conferenceId' => $conferenceId,
            'conferenceSid' => $conferenceSid
        ]);

    } elseif ($action === 'mute_others') {
        // New action - Mute all participants except the active controller
        $participants = $client->conferences($conferenceSid)->participants->read();

        $mutedCount = 0;
        foreach ($participants as $participant) {
            // Get the participant's caller ID or identifier
            // This is a simplified approach - in practice you might need to track
            // participant identities more carefully
            $shouldMute = true;

            // Don't mute the active controller
            // Note: This is a simplified implementation. You might need to
            // implement better participant tracking based on your system
            if ($participant->startConferenceOnEnter) {
                // This might be the conference moderator, check if it's the active controller
                // For now, we'll assume the active controller shouldn't be muted
                $shouldMute = false;
            }

            if ($shouldMute) {
                // Mute this participant
                $client->conferences($conferenceSid)
                       ->participants($participant->callSid)
                       ->update(['muted' => true]);
                // Log each mute action
                logMuteAction($conferenceId, $conferenceSid, $participant->callSid, 'mute', $activeController ?: 'mute_others');
                $mutedCount++;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Muted $mutedCount other participants, kept active controller unmuted",
            'conferenceId' => $conferenceId,
            'conferenceSid' => $conferenceSid,
            'activeController' => $activeController
        ]);
        
    } elseif ($action === 'mute_participant') {
        // Mute a specific participant by their CallSid
        $callSid = $input['callSid'] ?? '';
        if (empty($callSid)) {
            http_response_code(400);
            echo json_encode(['error' => 'CallSid required for mute_participant action']);
            exit;
        }

        try {
            $client->conferences($conferenceSid)
                   ->participants($callSid)
                   ->update(['muted' => true]);

            // Log application-initiated mute to database
            logMuteAction($conferenceId, $conferenceSid, $callSid, 'mute', $activeController ?: 'training_system');

            echo json_encode([
                'success' => true,
                'message' => 'Participant muted',
                'conferenceId' => $conferenceId,
                'conferenceSid' => $conferenceSid,
                'callSid' => $callSid,
                'muted' => true
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to mute participant: ' . $e->getMessage()]);
        }

    } elseif ($action === 'unmute_participant') {
        // Unmute a specific participant by their CallSid
        $callSid = $input['callSid'] ?? '';
        if (empty($callSid)) {
            http_response_code(400);
            echo json_encode(['error' => 'CallSid required for unmute_participant action']);
            exit;
        }

        try {
            $client->conferences($conferenceSid)
                   ->participants($callSid)
                   ->update(['muted' => false]);

            // Log application-initiated unmute to database
            logMuteAction($conferenceId, $conferenceSid, $callSid, 'unmute', $activeController ?: 'training_system');

            echo json_encode([
                'success' => true,
                'message' => 'Participant unmuted',
                'conferenceId' => $conferenceId,
                'conferenceSid' => $conferenceSid,
                'callSid' => $callSid,
                'muted' => false
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to unmute participant: ' . $e->getMessage()]);
        }

    } elseif ($action === 'get_participants') {
        // Get all participants in a conference with their mute status
        $participants = $client->conferences($conferenceSid)->participants->read();

        $participantList = [];
        foreach ($participants as $participant) {
            $participantList[] = [
                'callSid' => $participant->callSid,
                'muted' => $participant->muted,
                'hold' => $participant->hold,
                'startConferenceOnEnter' => $participant->startConferenceOnEnter,
                'endConferenceOnExit' => $participant->endConferenceOnExit
            ];
        }

        echo json_encode([
            'success' => true,
            'conferenceId' => $conferenceId,
            'conferenceSid' => $conferenceSid,
            'participants' => $participantList
        ]);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to mute participants: ' . $e->getMessage()]);
}
?>