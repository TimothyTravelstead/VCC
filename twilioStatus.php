<?php
/**
 * Twilio Status Callback Webhook
 *
 * Logs all Twilio call and conference status events for debugging.
 * This is particularly useful for debugging training session muting issues.
 *
 * IMPORTANT: This is a Twilio webhook (server-to-server request).
 * - NO session_start() - Twilio has no user session
 * - NO requireAuth() - would always fail
 *
 * Configure in Twilio Console:
 * - Phone Numbers → Status Callback URL: https://www.volunteerlogin.org/twilioStatus.php
 * - Conference TwiML → statusCallback attribute
 */

// Include database connection
require_once('../private_html/db_login.php');

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate, private');
header('Pragma: no-cache');
header('Expires: 0');

// Set content type for TwiML response
header('Content-Type: text/xml');

try {
    // Extract all Twilio parameters with null coalescing
    // Core Identifiers
    $callSid = $_REQUEST['CallSid'] ?? null;
    $accountSid = $_REQUEST['AccountSid'] ?? null;
    $parentCallSid = $_REQUEST['ParentCallSid'] ?? null;

    // Conference Identifiers (critical for training debugging)
    $conferenceSid = $_REQUEST['ConferenceSid'] ?? null;
    $friendlyName = $_REQUEST['FriendlyName'] ?? null;

    // Status
    $callStatus = $_REQUEST['CallStatus'] ?? null;
    $statusCallbackEvent = $_REQUEST['StatusCallbackEvent'] ?? null;

    // Direction & Phone Numbers
    $direction = $_REQUEST['Direction'] ?? null;
    $from = $_REQUEST['From'] ?? null;
    $to = $_REQUEST['To'] ?? null;

    // Duration & Sequence
    $callDuration = isset($_REQUEST['CallDuration']) ? (int)$_REQUEST['CallDuration'] : null;
    $sequenceNumber = isset($_REQUEST['SequenceNumber']) ? (int)$_REQUEST['SequenceNumber'] : null;

    // Geographic - From
    $fromCity = $_REQUEST['FromCity'] ?? null;
    $fromState = $_REQUEST['FromState'] ?? null;
    $fromZip = $_REQUEST['FromZip'] ?? null;
    $fromCountry = $_REQUEST['FromCountry'] ?? null;

    // Geographic - To
    $toCity = $_REQUEST['ToCity'] ?? null;
    $toState = $_REQUEST['ToState'] ?? null;
    $toZip = $_REQUEST['ToZip'] ?? null;
    $toCountry = $_REQUEST['ToCountry'] ?? null;

    // Conference Participant State (booleans)
    $muted = isset($_REQUEST['Muted']) ? ($_REQUEST['Muted'] === 'true' ? 1 : 0) : null;
    $hold = isset($_REQUEST['Hold']) ? ($_REQUEST['Hold'] === 'true' ? 1 : 0) : null;
    $coaching = isset($_REQUEST['Coaching']) ? ($_REQUEST['Coaching'] === 'true' ? 1 : 0) : null;
    $endConferenceOnExit = isset($_REQUEST['EndConferenceOnExit']) ? ($_REQUEST['EndConferenceOnExit'] === 'true' ? 1 : 0) : null;
    $startConferenceOnEnter = isset($_REQUEST['StartConferenceOnEnter']) ? ($_REQUEST['StartConferenceOnEnter'] === 'true' ? 1 : 0) : null;

    // Metadata
    $twilioTimestamp = $_REQUEST['Timestamp'] ?? null;
    $apiVersion = $_REQUEST['ApiVersion'] ?? null;

    // Store raw request for any parameters we may have missed
    $rawRequest = json_encode($_REQUEST);

    // Only log if we have a CallSid (required field)
    if ($callSid) {
        $query = "INSERT INTO TwilioStatusLog (
            CallSid, AccountSid, ParentCallSid,
            ConferenceSid, FriendlyName,
            CallStatus, StatusCallbackEvent,
            Direction, FromNumber, ToNumber,
            CallDuration, SequenceNumber,
            FromCity, FromState, FromZip, FromCountry,
            ToCity, ToState, ToZip, ToCountry,
            Muted, Hold, Coaching, EndConferenceOnExit, StartConferenceOnEnter,
            TwilioTimestamp, ApiVersion, RawRequest
        ) VALUES (
            ?, ?, ?,
            ?, ?,
            ?, ?,
            ?, ?, ?,
            ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?
        )";

        $params = [
            $callSid, $accountSid, $parentCallSid,
            $conferenceSid, $friendlyName,
            $callStatus, $statusCallbackEvent,
            $direction, $from, $to,
            $callDuration, $sequenceNumber,
            $fromCity, $fromState, $fromZip, $fromCountry,
            $toCity, $toState, $toZip, $toCountry,
            $muted, $hold, $coaching, $endConferenceOnExit, $startConferenceOnEnter,
            $twilioTimestamp, $apiVersion, $rawRequest
        ];

        $result = dataQuery($query, $params);

        if (is_array($result) && isset($result['error'])) {
            error_log("TwilioStatusLog insert error: " . $result['message']);
        }
    }

} catch (Exception $e) {
    // Log error but don't fail - we always want to return 200 to Twilio
    error_log("twilioStatus.php error: " . $e->getMessage());
}

// Always return HTTP 200 with empty TwiML to prevent Twilio retry storms
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<Response></Response>';
