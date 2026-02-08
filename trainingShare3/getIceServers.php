<?php
/**
 * getIceServers.php - Session-authenticated endpoint returning ICE server configuration
 * TURN credentials are loaded from private_html (outside web root) so they
 * are never exposed in client-side source code.
 */

require_once('../../private_html/db_login.php');
session_start();

// Require authenticated training session (trainer or trainee)
$loggedOn = $_SESSION['LoggedOn'] ?? 0;
if (!in_array($loggedOn, [4, 6])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized - training session required']);
    exit;
}

session_write_close();

header('Content-Type: application/json');
header('Cache-Control: no-store');

// STUN servers (public, no credentials needed)
$iceServers = [
    ['urls' => 'stun:stun.stunprotocol.org:3478'],
    ['urls' => 'stun:stun.l.google.com:19302'],
    ['urls' => 'stun:stun1.l.google.com:19302'],
    ['urls' => 'stun:stun2.l.google.com:19302'],
];

// Add TURN servers from private config
require_once('../../private_html/turn_config.php');
foreach ($turnConfig as $turn) {
    $iceServers[] = $turn;
}

echo json_encode([
    'success' => true,
    'iceServers' => $iceServers
]);
