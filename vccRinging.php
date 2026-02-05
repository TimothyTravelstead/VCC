<?php
/**
 * vccRinging.php - Ultra-fast ringing check endpoint
 *
 * Polled every 500ms for near-instant incoming call notification.
 * Returns only ringing state - completes in <10ms.
 *
 * @author Claude Code
 * @date December 20, 2025
 */

require_once('../private_html/db_login.php');
session_start();

// Capture session data and ID before releasing lock
$sessionId = session_id();
$sessionDataRaw = $_SESSION;

session_write_close(); // Release session lock immediately

// Custom session loading (matches vccFeed.php logic)
$sessionData = [];
try {
    $customSessionFile = '../private_html/session_' . $sessionId . '.json';
    if (file_exists($customSessionFile)) {
        $customSessionContent = file_get_contents($customSessionFile);
        $customSessionData = json_decode($customSessionContent, true);
        if ($customSessionData && isset($customSessionData['data'])) {
            $sessionData = $customSessionData['data'];
        } else {
            $sessionData = $sessionDataRaw;
        }
    } else {
        $sessionData = $sessionDataRaw;
    }
} catch (Exception $e) {
    $sessionData = $sessionDataRaw;
}

// Authenticate using loaded session data
$userID = $sessionData['UserID'] ?? null;
if (!$userID) {
    http_response_code(401);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379, 0.5); // 500ms timeout for fast response

    // Check ringing state for this user
    $redisKey = "vcc:ringing:{$userID}";
    $ringingJson = $redis->get($redisKey);
    $redis->close();

    if ($ringingJson) {
        // Include debug info in response
        $ringingData = json_decode($ringingJson, true);
        $ringingData['_debug'] = ['userID' => $userID, 'key' => $redisKey];
        echo json_encode($ringingData);
    } else {
        echo json_encode(['ringing' => false, '_debug' => ['userID' => $userID, 'key' => $redisKey, 'found' => false]]);
    }
} catch (Exception $e) {
    // Fallback to database on Redis failure
    error_log("vccRinging.php: Redis error, falling back to database - " . $e->getMessage());

    $result = dataQuery(
        "SELECT ringing, IncomingCallSid FROM volunteers WHERE UserName = ?",
        [$userID]
    );

    if ($result && count($result) > 0 && $result[0]->ringing) {
        echo json_encode([
            'ringing' => true,
            'callSid' => $result[0]->IncomingCallSid,
            'source' => 'database'
        ]);
    } else {
        echo '{"ringing":false}';
    }
}
