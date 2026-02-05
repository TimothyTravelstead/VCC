<?php
/**
 * Twilio Debugger/Error Webhook
 *
 * Logs all Twilio Debugger events (errors and warnings) for monitoring.
 *
 * IMPORTANT: This is a Twilio webhook (server-to-server request).
 * - NO session_start() - Twilio has no user session
 * - NO requireAuth() - would always fail
 *
 * Configure in Twilio Console:
 * - Console → Monitor → Errors → Webhook
 * - URL: https://www.volunteerlogin.org/twilioErrors.php
 *
 * Expected payload:
 * - AccountSid: Account that generated the event
 * - Sid: Unique identifier of the Debugger event
 * - ParentAccountSid: Parent account (if subaccount)
 * - Timestamp: Time of occurrence
 * - Level: "Error" or "Warning"
 * - PayloadType: "application/json"
 * - Payload: JSON data specific to the event
 */

// Include database connection
require_once('../private_html/db_login.php');

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate, private');
header('Pragma: no-cache');
header('Expires: 0');

// Return 200 OK
header('Content-Type: text/plain');

try {
    // Extract Twilio Debugger parameters
    $accountSid = $_REQUEST['AccountSid'] ?? null;
    $sid = $_REQUEST['Sid'] ?? null;
    $parentAccountSid = $_REQUEST['ParentAccountSid'] ?? null;
    $timestamp = $_REQUEST['Timestamp'] ?? null;
    $level = $_REQUEST['Level'] ?? null;
    $payloadType = $_REQUEST['PayloadType'] ?? null;

    // Payload comes as JSON string
    $payload = $_REQUEST['Payload'] ?? null;

    // Validate payload is valid JSON if present
    if ($payload !== null) {
        $decodedPayload = json_decode($payload);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Not valid JSON, wrap it
            $payload = json_encode(['raw' => $payload]);
        }
    }

    // Store raw request
    $rawRequest = json_encode($_REQUEST);

    // Only log if we have a Sid (indicates valid Debugger event)
    if ($sid) {
        $query = "INSERT INTO TwilioErrorLog (
            AccountSid, Sid, ParentAccountSid,
            Timestamp, Level, PayloadType,
            Payload, RawRequest
        ) VALUES (
            ?, ?, ?,
            ?, ?, ?,
            ?, ?
        )";

        $params = [
            $accountSid, $sid, $parentAccountSid,
            $timestamp, $level, $payloadType,
            $payload, $rawRequest
        ];

        $result = dataQuery($query, $params);

        if (is_array($result) && isset($result['error'])) {
            error_log("TwilioErrorLog insert error: " . $result['message']);
        }
    }

} catch (Exception $e) {
    // Log error but don't fail - always return 200 to Twilio
    error_log("twilioErrors.php error: " . $e->getMessage());
}

// Return 200 OK
http_response_code(200);
echo "OK";
