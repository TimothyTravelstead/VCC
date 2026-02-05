<?php

require_once('../../../private_html/db_login.php');
session_start();
set_time_limit(10800);
ignore_user_abort(true);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../vendor/autoload.php';
require_once 'bulkFormatEmail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

date_default_timezone_set('Etc/UTC');

$VolunteerID = 'BulkEmail';
$_ENV["done"] = false;

/**
 * Handles shutdown process and sends status email if process was interrupted
 */
function shutdown($bulkSetID, $resourcesIncluded, $sendCount, $errorCount, $noEmailCount) {
    if ($_ENV["done"]) {
        return;
    }

    $query = "SELECT COUNT(resourceID) as unsent_count 
              FROM bulkLog 
              WHERE bulkSetID = ? AND Status = 'Queued'";
              
    $result = dataQuery($query, [$bulkSetID]);
    
    if (!$result || !is_array($result)) {
        return;
    }

    $unsent = $result[0]->unsent_count;
    if ($unsent > 0) {
        $messageText = generateShutdownMessage($resourcesIncluded, $sendCount, $errorCount, $noEmailCount, $unsent);
        sendEmail(
            "tim@lgbthotline.org",
            $messageText,
            "BULK EMAIL SHUTDOWN",
            null,
            null,
            null
        );

        echo json_encode([
            'resultsSummary' => (string)$resourcesIncluded,
            'sendCount' => (string)$sendCount,
            'errorCount' => (string)$errorCount,
            'noEmailCount' => (string)$noEmailCount,
            'status' => "Ongoing"
        ]);
    }
}

/**
 * Sends an email using PHPMailer
 */
function sendEmail($to, $message, $email_subject, $bulkSetID = null, $VolunteerID = null, $idnum = null) {
    echo "Sending email to: " . $to;
    
    try {
        $mail = configureMailer();
        
        if (!$mail->validateAddress($to)) {
            if ($bulkSetID) {
                updateBulkLogStatus($bulkSetID, $idnum, 'INVALID EMAIL ADDRESS');
            }
            return false;
        }

        $mail->addAddress($to, '');
        $mail->Subject = $email_subject;
        $mail->msgHTML($message);
        $mail->addBCC('brad@lgbthotline.org');

        if (!$mail->send()) {
            handleSendError($bulkSetID, $idnum, $mail->ErrorInfo);
            return false;
        }

        if ($bulkSetID) {
            logSuccessfulSend($bulkSetID, $idnum, $VolunteerID);
        }
        
        return true;

    } catch (Exception $e) {
        handleSendException($bulkSetID, $idnum, $e);
        return false;
    } finally {
        cleanupMailer($mail);
    }
}

/**
 * Configures PHPMailer instance with standard settings
 */
function configureMailer() {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'smtp.dreamhost.com';
    $mail->Port = 587;
    $mail->SMTPSecure = 'tls';
    $mail->SMTPAuth = true;
    
    $mail->Username = 'update@lgbthotlineresources.org';
    $mail->Password = 'LGBTNHC181!';
    $mail->SetFrom('update@lgbthotlineresources.org', 'LGBT National Help Center');
    $mail->addReplyTo('update@lgbthotlineresources.org', 'Tanya');
    
    return $mail;
}

/**
 * Updates bulk log status for errors
 */
function updateBulkLogStatus($bulkSetID, $idnum, $status) {
    $query = "UPDATE bulkLog 
              SET Status = ? 
              WHERE bulkSetID = ? AND resourceID = ?";
    
    return dataQuery($query, [$status, $bulkSetID, $idnum]);
}

/**
 * Logs successful email send
 */
function logSuccessfulSend($bulkSetID, $idnum, $VolunteerID) {
    // Log the edit
    $query1 = "INSERT INTO resourceEditLog (UserName, resourceIDNUM, Action) 
               VALUES (?, ?, 'Email')";
    dataQuery($query1, [$VolunteerID, $idnum]);
    
    // Update bulk log status
    $query2 = "UPDATE bulkLog 
               SET Status = 'Sent' 
               WHERE bulkSetID = ? AND resourceID = ?";
    dataQuery($query2, [$bulkSetID, $idnum]);
}

/**
 * Gets queued email records
 */
function getQueuedEmails() {
    $query = "SELECT 
                resourceID,
                bulkSets.ID as BulkSetID,
                bulkSets.Date,
                bulkSets.Status as BulkSetStatus,
                bulkLog.Status as BulkLogStatus,
                Action 
              FROM bulkLog 
              LEFT JOIN Resource ON (bulkLog.ResourceID = resource.IDNUM) 
              LEFT JOIN BulkSets ON (BulkSets.id = bulkLog.BulkSetID) 
              LEFT JOIN ResourceEditLog ON (
                resourceEditLog.ResourceIDNUM = resourceID 
                AND Action = 'Email'
              )
              WHERE bulkLog.Status = 'Queued' 
              AND bulkSets.Status != 'Finished' 
              AND (ActionDate IS NULL OR ActionDate < CURDATE())
              GROUP BY ResourceID, resource.edate, BulkSetID
              ORDER BY bulkSets.id ASC, resource.edate ASC";
              
    return dataQuery($query);
}

/**
 * Main processing function
 */
function main() {
    $stats = [
        'resourcesIncluded' => 0,
        'sendCount' => 0,
        'errorCount' => 0,
        'noEmailCount' => 0,
        'bulkSetID' => 0,
        'priorBulkSetID' => 0
    ];
    
    register_shutdown_function(
        'shutdown',
        $stats['bulkSetID'],
        $stats['resourcesIncluded'],
        $stats['sendCount'],
        $stats['errorCount'],
        $stats['noEmailCount']
    );

    $result = getQueuedEmails();
    if (!$result || !is_array($result)) {
        $_ENV["done"] = true;
        return;
    }

    $progressCount = 0;
    foreach ($result as $row) {
        // Random delay between emails (5-10 minutes)
        sleep(rand(300, 600));

        $stats['resourcesIncluded']++;
        $stats['bulkSetID'] = $row->BulkSetID;
        
        // Check if we need to finish previous bulk set
        if ($stats['priorBulkSetID'] != '0' && 
            $stats['bulkSetID'] && 
            $stats['bulkSetID'] != 0 && 
            $stats['priorBulkSetID'] != $stats['bulkSetID']) {
            
            setFinished(
                $stats['priorBulkSetID'],
                $row->Date,
                $stats['resourcesIncluded'],
                $stats['sendCount'],
                $stats['errorCount'],
                $stats['noEmailCount']
            );
            
            // Reset counters
            resetStats($stats);
            $stats['priorBulkSetID'] = $stats['bulkSetID'];
        }

        // Format and send email
        list($to, $subject, $message) = formatMessage($row->resourceID);
        
        if (!$to || trim($to) == "") {
            handleNoEmailAddress($stats, $row);
            continue;
        }

        if (sendEmail($to, $message, $subject, $stats['bulkSetID'], $GLOBALS['VolunteerID'], $row->resourceID)) {
            $stats['sendCount']++;
            $progressCount++;
            
            // Send update every 25 emails
            if ($progressCount > 24) {
                sendUpdate(
                    $stats['bulkSetID'],
                    $row->Date,
                    $stats['resourcesIncluded'],
                    $stats['sendCount'],
                    $stats['errorCount'],
                    $stats['noEmailCount']
                );
                $progressCount = 0;
            }
        } else {
            $stats['errorCount']++;
        }
    }

    $_ENV["done"] = true;
    setFinished(
        $stats['bulkSetID'],
        $row->Date,
        $stats['resourcesIncluded'],
        $stats['sendCount'],
        $stats['errorCount'],
        $stats['noEmailCount']
    );
}

// Process lock file handling
$lockFile = "bulkCron.lock";
$fp = fopen($lockFile, "w+");

// Check if lock file is stale (over 8 hours old)
if (file_exists($lockFile)) {
    $lastModified = filemtime($lockFile);
    $ageInHours = (time() - $lastModified) / 3600;
    
    if ($ageInHours > 8) {
        echo "Lock file is stale, deleting and proceeding.\n";
        unlink($lockFile);
        $fp = fopen($lockFile, "w+");
    }
}

if (flock($fp, LOCK_EX | LOCK_NB)) {
    touch($lockFile);
    main();
    flock($fp, LOCK_UN);
    fclose($fp);
} else {
    echo "Another process is already running.";
}
?>
