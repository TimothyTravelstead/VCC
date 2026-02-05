<?php
// ═══════════════════════════════════════════════════════════════
// ERROR EMAIL SENDER
// Sends detailed error reports including console logs to admin
// ═══════════════════════════════════════════════════════════════

// Include database connection FIRST to set session configuration
require_once('../private_html/db_login.php');

// Start session
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

// Release session lock immediately - we only need to read session data
session_write_close();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://www.volunteerlogin.org');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

date_default_timezone_set('Etc/UTC');

require_once 'vendor/autoload.php';

// Get POST data
$inputData = file_get_contents('php://input');
$data = json_decode($inputData, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
    exit;
}

// Extract error details
$title = $data['title'] ?? 'Database Error';
$errorMessage = $data['errorMessage'] ?? 'Unknown error';
$errorDetails = $data['errorDetails'] ?? '';
$volunteer = $data['volunteer'] ?? 'Unknown';
$callSid = $data['callSid'] ?? 'Unknown';
$requestData = $data['requestData'] ?? '{}';
$consoleLogs = $data['consoleLogs'] ?? 'No console logs available';
$timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
$url = $data['url'] ?? '';
$stackTrace = $data['stackTrace'] ?? '';

// Build comprehensive email body
$emailBody = '<html><head><style>
body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
.header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
.header h1 { margin: 0; font-size: 24px; }
.section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3545; border-radius: 4px; }
.section h2 { margin-top: 0; color: #dc3545; font-size: 18px; }
.data { background: white; padding: 10px; font-family: "Courier New", monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word; border: 1px solid #dee2e6; border-radius: 4px; }
.info-line { margin: 5px 0; }
.label { font-weight: bold; color: #495057; }
</style></head><body>';

$emailBody .= '<div class="header"><h1>🚨 ' . htmlspecialchars($title) . '</h1></div>';

$emailBody .= '<div class="section">';
$emailBody .= '<h2>📋 Error Summary</h2>';
$emailBody .= '<div class="info-line"><span class="label">Time:</span> ' . htmlspecialchars($timestamp) . '</div>';
$emailBody .= '<div class="info-line"><span class="label">Volunteer:</span> ' . htmlspecialchars($volunteer) . '</div>';
$emailBody .= '<div class="info-line"><span class="label">Call SID:</span> ' . htmlspecialchars($callSid) . '</div>';
if ($url) {
    $emailBody .= '<div class="info-line"><span class="label">URL:</span> ' . htmlspecialchars($url) . '</div>';
}
$emailBody .= '</div>';

$emailBody .= '<div class="section">';
$emailBody .= '<h2>🔴 Error Message</h2>';
$emailBody .= '<div class="data">' . htmlspecialchars($errorMessage) . '</div>';
$emailBody .= '</div>';

if ($errorDetails) {
    $emailBody .= '<div class="section">';
    $emailBody .= '<h2>📄 Error Details</h2>';
    $emailBody .= '<div class="data">' . htmlspecialchars($errorDetails) . '</div>';
    $emailBody .= '</div>';
}

if ($stackTrace) {
    $emailBody .= '<div class="section">';
    $emailBody .= '<h2>📋 Stack Trace</h2>';
    $emailBody .= '<div class="data">' . htmlspecialchars($stackTrace) . '</div>';
    $emailBody .= '</div>';
}

$emailBody .= '<div class="section">';
$emailBody .= '<h2>📦 Request Data</h2>';
// Pretty print JSON if it's valid JSON, otherwise show as-is
$requestDataDecoded = json_decode($requestData, true);
if ($requestDataDecoded) {
    $emailBody .= '<div class="data">' . htmlspecialchars(json_encode($requestDataDecoded, JSON_PRETTY_PRINT)) . '</div>';
} else {
    $emailBody .= '<div class="data">' . htmlspecialchars($requestData) . '</div>';
}
$emailBody .= '</div>';

$emailBody .= '<div class="section">';
$emailBody .= '<h2>📟 JavaScript Console Logs</h2>';
$emailBody .= '<div class="data">' . htmlspecialchars($consoleLogs) . '</div>';
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

        $mail->SetFrom('database@glbthotline.org', 'LGBT National Help Center - Error Reporter');
        $mail->addReplyTo('database@glbthotline.org', 'LGBT National Help Center');
        $mail->addAddress($to, '');
        $mail->Subject = $subject;
        $mail->msgHTML($htmlBody);

        if (!$mail->send()) {
            error_log('Failed to send error email: ' . $mail->ErrorInfo);
            return false;
        }
        return true;
    } catch (Exception $e) {
        error_log('Exception sending error email: ' . $e->getMessage());
        return false;
    }
}

// Send the email
$emailSubject = 'CRITICAL: ' . $title . ' - ' . $volunteer;
$sent = sendEmail('Tim@LGBTHotline.org', $emailSubject, $emailBody);

// Return response
if ($sent) {
    echo json_encode(['status' => 'success', 'message' => 'Error email sent successfully']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to send error email']);
}
?>