<?php
header('Access-Control-Allow-Origin: https://www.volunteerlogin.org');

require_once '../../vendor/autoload.php';
require_once('../../../private_html/db_login.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Set timezone
date_default_timezone_set('Etc/UTC');

// Get session and request data with validation
session_start();
$VolunteerID = $_SESSION["UserID"] ?? '';
$to = filter_var($_REQUEST["To"] ?? '', FILTER_SANITIZE_EMAIL);
$idnum = filter_var($_REQUEST["idnum"] ?? '', FILTER_SANITIZE_STRING);
$email_subject = filter_var($_REQUEST["Subject"] ?? '', FILTER_SANITIZE_STRING);
$message = $_REQUEST["Message"] ?? ''; // HTML content, will be handled by PHPMailer

// Validate required fields
if (empty($to) || empty($idnum) || empty($email_subject) || empty($message)) {
    http_response_code(400);
    echo "Missing required fields";
    exit;
}

try {
    $mail = new PHPMailer(true);
    
    // Server settings
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'smtp.dreamhost.com';
    $mail->Port = 587;
    $mail->SMTPSecure = 'tls';
    $mail->SMTPAuth = true;
    
    // Authentication
    $mail->Username = 'update@lgbthotlineresources.org';
    $mail->Password = 'LGBTNHC181!';
    
    // Sender settings
    $mail->setFrom('update@lgbthotlineresources.org', 'LGBT National Help Center');
    $mail->addReplyTo('update@lgbthotlineresources.org', 'Tanya');
    
    // Recipients
    $mail->addAddress($to);
    $mail->addBCC('travelstead@mac.com');
    
    // Content
    $mail->Subject = $email_subject;
    $mail->msgHTML($message);
    
    // Send email
    if (!$mail->send()) {
        throw new Exception($mail->ErrorInfo);
    }
    
    // Log successful email
    $query = "INSERT INTO resourceEditLog 
              (UserName, resourceIDNUM, Action) 
              VALUES 
              (:volunteerID, :resourceID, 'Email')";
              
    $params = [
        ':volunteerID' => $VolunteerID,
        ':resourceID' => $idnum
    ];
    
    $result = dataQuery($query, $params);
    
    if ($result === false) {
        throw new Exception("Failed to log email in database");
    }
    
    echo "OK";
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Mailer Error: ' . $e->getMessage();
    
    // Optionally log the error
    error_log("Email send failed: " . $e->getMessage());
}
?>
