<?php
// ═══════════════════════════════════════════════════════════════
// GROUP CHAT ERROR EMAIL SENDER
// Sends error reports from GroupChat to admin
// Does NOT require VCC authentication (callers aren't authenticated)
// ═══════════════════════════════════════════════════════════════

// Rate limiting to prevent abuse
session_start();
$currentTime = time();
$rateLimitKey = 'groupchat_error_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'first_request' => $currentTime];
}

// Allow max 5 error reports per minute per IP
$timeWindow = 60; // seconds
if ($currentTime - $_SESSION[$rateLimitKey]['first_request'] > $timeWindow) {
    // Reset the window
    $_SESSION[$rateLimitKey] = ['count' => 1, 'first_request' => $currentTime];
} else {
    $_SESSION[$rateLimitKey]['count']++;
    if ($_SESSION[$rateLimitKey]['count'] > 5) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Too many requests']);
        exit;
    }
}

session_write_close();

header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

date_default_timezone_set('America/Los_Angeles');

require_once '../vendor/autoload.php';

// Get POST data
$inputData = file_get_contents('php://input');
$data = json_decode($inputData, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
    exit;
}

// Extract error details - sanitize all inputs
$subject = isset($data['subject']) ? substr(strip_tags($data['subject']), 0, 200) : 'GroupChat Error';
$body = isset($data['body']) ? $data['body'] : '';

// Parse body if it's JSON
$errorDetails = is_string($body) ? json_decode($body, true) : $body;
if (!$errorDetails) {
    $errorDetails = ['raw' => substr($body, 0, 5000)];
}

// Build comprehensive email body
$emailBody = '<html><head><style>
body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
.header { background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
.header h1 { margin: 0; font-size: 24px; }
.section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #6f42c1; border-radius: 4px; }
.section h2 { margin-top: 0; color: #6f42c1; font-size: 18px; }
.data { background: white; padding: 10px; font-family: "Courier New", monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word; border: 1px solid #dee2e6; border-radius: 4px; max-height: 300px; overflow-y: auto; }
.info-line { margin: 5px 0; }
.label { font-weight: bold; color: #495057; }
.moderator-badge { background: #28a745; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
.caller-badge { background: #17a2b8; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
</style></head><body>';

$emailBody .= '<div class="header"><h1>💬 GroupChat Error Report</h1></div>';

$emailBody .= '<div class="section">';
$emailBody .= '<h2>📋 Error Summary</h2>';
$emailBody .= '<div class="info-line"><span class="label">Context:</span> ' . htmlspecialchars($errorDetails['context'] ?? 'Unknown') . '</div>';
$emailBody .= '<div class="info-line"><span class="label">Time:</span> ' . htmlspecialchars($errorDetails['timestamp'] ?? date('Y-m-d H:i:s')) . '</div>';
$emailBody .= '<div class="info-line"><span class="label">User:</span> ' . htmlspecialchars($errorDetails['currentUser'] ?? 'Unknown') . '</div>';

$isModerator = isset($errorDetails['isModerator']) && $errorDetails['isModerator'];
$emailBody .= '<div class="info-line"><span class="label">User Type:</span> ';
$emailBody .= $isModerator ? '<span class="moderator-badge">Moderator</span>' : '<span class="caller-badge">Caller</span>';
$emailBody .= '</div>';

$emailBody .= '<div class="info-line"><span class="label">URL:</span> ' . htmlspecialchars($errorDetails['url'] ?? 'Unknown') . '</div>';
$emailBody .= '<div class="info-line"><span class="label">IP:</span> ' . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . '</div>';
$emailBody .= '</div>';

$emailBody .= '<div class="section">';
$emailBody .= '<h2>🔴 Error Message</h2>';
$emailBody .= '<div class="data">' . htmlspecialchars($errorDetails['error'] ?? 'No error message') . '</div>';
$emailBody .= '</div>';

$emailBody .= '<div class="section">';
$emailBody .= '<h2>🖥️ Client Info</h2>';
$emailBody .= '<div class="data">' . htmlspecialchars($errorDetails['userAgent'] ?? 'Unknown') . '</div>';
$emailBody .= '</div>';

$emailBody .= '</body></html>';

// Send email using PHPMailer
function sendEmail($to, $subject, $htmlBody) {
    try {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = 'netsol-smtp-oxcs.hostingplatform.com';
        $mail->Port = 587;
        $mail->SMTPSecure = 'tls';
        $mail->SMTPAuth = true;

        $mail->Username = 'database@glbthotline.org';
        $mail->Password = 'LGBTNHC11!!!';

        $mail->SetFrom('database@glbthotline.org', 'LGBT National Help Center - GroupChat');
        $mail->addReplyTo('database@glbthotline.org', 'LGBT National Help Center');
        $mail->addAddress($to, '');
        $mail->Subject = $subject;
        $mail->msgHTML($htmlBody);

        if (!$mail->send()) {
            error_log('Failed to send GroupChat error email: ' . $mail->ErrorInfo);
            return false;
        }
        return true;
    } catch (Exception $e) {
        error_log('Exception sending GroupChat error email: ' . $e->getMessage());
        return false;
    }
}

// Send the email
$emailSubject = 'GroupChat Error: ' . ($errorDetails['context'] ?? 'Unknown');
$sent = sendEmail('Tim@LGBTHotline.org', $emailSubject, $emailBody);

// Return response
if ($sent) {
    echo json_encode(['status' => 'success', 'message' => 'Error report sent']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to send error report']);
}
?>
