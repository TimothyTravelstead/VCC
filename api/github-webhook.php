<?php
/**
 * GitHub Webhook - Auto-deploy on push to main
 *
 * GitHub sends a POST request here when code is pushed.
 * Since exec() is blocked in PHP-FPM by the server security layer,
 * this script validates the signature and writes a trigger file.
 * A cron job (deploy-cron.sh) checks for the trigger and runs git commands.
 */

// Webhook secret - must match what's configured in GitHub
$secret = '4a4568941280a2a4b875f4ea99e7df08ac6983a0';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Get the payload
$payload = file_get_contents('php://input');
if (empty($payload)) {
    http_response_code(400);
    exit('No payload');
}

// Verify GitHub signature
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if (empty($signature)) {
    http_response_code(403);
    exit('No signature');
}

$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

// Parse the payload
$data = json_decode($payload, true);

// Only deploy on pushes to main branch
$ref = $data['ref'] ?? '';
if ($ref !== 'refs/heads/main') {
    http_response_code(200);
    exit('Not main branch, skipping');
}

// Extract info for logging
$pusher = $data['pusher']['name'] ?? 'unknown';
$commitMsg = $data['head_commit']['message'] ?? 'no message';
$commitSummary = strtok($commitMsg, "\n");

// Write trigger file for the cron job to pick up
$triggerFile = dirname(__DIR__) . '/.deploy_trigger';
$triggerData = json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'pusher' => $pusher,
    'commit' => $commitSummary
]);

if (file_put_contents($triggerFile, $triggerData) === false) {
    http_response_code(500);
    exit('Failed to write deploy trigger');
}

// Log that webhook was received
$logFile = dirname(__DIR__) . '/deploy.log';
$timestamp = date('Y-m-d H:i:s');
file_put_contents(
    $logFile,
    "[$timestamp] Webhook received from $pusher: $commitSummary (waiting for cron)\n",
    FILE_APPEND
);

http_response_code(200);
echo "Deploy queued for: $commitSummary";
