<?php
/**
 * Get Twilio Status and Error details for a specific CallSid
 *
 * Used by the Admin Call Monitor UI to display call details in a modal
 */

require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock
session_write_close();

header('Content-Type: application/json');

$callSid = $_REQUEST['CallSid'] ?? null;

if (!$callSid) {
    echo json_encode(['error' => 'CallSid is required']);
    exit;
}

$response = [
    'callSid' => $callSid,
    'statusEvents' => [],
    'errors' => [],
    'warnings' => [],
    'hasErrors' => false,
    'hasWarnings' => false
];

// Helper function to determine if an error is a carrier warning (SHAKEN/STIR related)
function isCarrierWarning($errorCode, $errorMessage, $payload) {
    // SHAKEN/STIR error codes
    $warningCodes = ['32020', '32021'];
    if (in_array($errorCode, $warningCodes)) {
        return true;
    }

    // Check for SHAKEN/STIR keywords in message or payload
    $keywords = ['SHAKEN', 'STIR', 'PASSporT', 'PPT'];
    $searchText = strtoupper($errorMessage . ' ' . json_encode($payload));
    foreach ($keywords as $keyword) {
        if (strpos($searchText, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

// Get status events for this call
$statusQuery = "SELECT
    timestamp,
    CallStatus,
    StatusCallbackEvent,
    Direction,
    ConferenceSid,
    FriendlyName,
    Muted,
    Hold,
    CallDuration,
    SequenceNumber
FROM TwilioStatusLog
WHERE CallSid = ?
ORDER BY timestamp ASC, SequenceNumber ASC";

$statusResults = dataQuery($statusQuery, [$callSid]);

if ($statusResults && !isset($statusResults['error'])) {
    foreach ($statusResults as $row) {
        $response['statusEvents'][] = [
            'timestamp' => $row->timestamp,
            'status' => $row->CallStatus,
            'event' => $row->StatusCallbackEvent,
            'direction' => $row->Direction,
            'conferenceSid' => $row->ConferenceSid,
            'friendlyName' => $row->FriendlyName,
            'muted' => $row->Muted,
            'hold' => $row->Hold,
            'duration' => $row->CallDuration,
            'sequence' => $row->SequenceNumber
        ];
    }
}

// Get errors related to this call
// Search in Payload JSON for the CallSid
$errorQuery = "SELECT
    received_at,
    Sid,
    Level,
    Payload
FROM TwilioErrorLog
WHERE JSON_SEARCH(Payload, 'one', ?) IS NOT NULL
   OR JSON_EXTRACT(Payload, '$.call_sid') = ?
   OR JSON_EXTRACT(Payload, '$.CallSid') = ?
ORDER BY received_at ASC";

$errorResults = dataQuery($errorQuery, [$callSid, $callSid, $callSid]);

if ($errorResults && !isset($errorResults['error'])) {
    foreach ($errorResults as $row) {
        $payload = json_decode($row->Payload, true);

        // Extract error message - check multiple possible locations
        $errorMessage = null;
        if (isset($payload['more_info'])) {
            if (is_array($payload['more_info'])) {
                $errorMessage = $payload['more_info']['parserMessage']
                    ?? $payload['more_info']['Msg']
                    ?? $payload['more_info']['message']
                    ?? json_encode($payload['more_info']);
            } else {
                $errorMessage = $payload['more_info'];
            }
        } elseif (isset($payload['error_message'])) {
            $errorMessage = $payload['error_message'];
        } elseif (isset($payload['message'])) {
            $errorMessage = $payload['message'];
        }

        $errorCode = $payload['error_code'] ?? $payload['ErrorCode'] ?? null;

        $entry = [
            'timestamp' => $row->received_at,
            'sid' => $row->Sid,
            'level' => $row->Level,
            'errorCode' => $errorCode,
            'errorMessage' => $errorMessage,
            'payload' => $payload
        ];

        // Categorize as warning or error
        if (isCarrierWarning($errorCode, $errorMessage ?? '', $payload)) {
            $response['warnings'][] = $entry;
            $response['hasWarnings'] = true;
        } else {
            $response['errors'][] = $entry;
            $response['hasErrors'] = true;
        }
    }
}

echo json_encode($response);
