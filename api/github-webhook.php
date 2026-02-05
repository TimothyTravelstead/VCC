<?php
/**
 * GitHub Webhook - Auto-deploy on push to main
 *
 * GitHub sends a POST request here when code is pushed.
 * This script verifies the signature and runs git pull.
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

// Log the deployment
$logFile = __DIR__ . '/../deploy.log';
$timestamp = date('Y-m-d H:i:s');
$pusher = $data['pusher']['name'] ?? 'unknown';
$commitMsg = $data['head_commit']['message'] ?? 'no message';

file_put_contents($logFile, "[$timestamp] Deploy triggered by $pusher: $commitMsg\n", FILE_APPEND);

// Change to the repo directory and pull
$repoDir = dirname(__DIR__);
$output = [];
$returnCode = 0;

// Run git pull
exec("cd " . escapeshellarg($repoDir) . " && git pull origin main 2>&1", $output, $returnCode);

$result = implode("\n", $output);
file_put_contents($logFile, "[$timestamp] Result (code $returnCode): $result\n\n", FILE_APPEND);

if ($returnCode === 0) {
    http_response_code(200);
    echo "Deploy successful: $result";
} else {
    http_response_code(500);
    echo "Deploy failed: $result";
}
