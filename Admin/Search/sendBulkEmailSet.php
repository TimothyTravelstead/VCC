<?php

require_once('../../../private_html/db_login.php');
session_start();
set_time_limit(180);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

date_default_timezone_set('Etc/UTC');

require_once '../../vendor/autoload.php';
require_once 'formatemail.php';

$done = false;

function shutdown($bulkSetID, $resourcesIncluded, $sendCount, $errorCount, $noEmailCount, $done) {
    if (!$done) {
        // Update remaining items to Queued status
        $query = "UPDATE bulkLog 
                  SET Status = 'Queued' 
                  WHERE bulkSetID = :bulkSetID 
                  AND Status = 'Selected'";
        dataQuery($query, [':bulkSetID' => $bulkSetID]);

        // Count remaining unsent items
        $query = "SELECT COUNT(resourceID) as unsent 
                  FROM bulkLog 
                  WHERE bulkSetID = :bulkSetID 
                  AND Status = 'Queued'";
        $result = dataQuery($query, [':bulkSetID' => $bulkSetID]);
        
        if ($result && $result[0]->unsent > 0) {
            $unsent = $result[0]->unsent;
            $messageText = "<p>Bulk Emails Stopped In Progress.</p>
                           <p>STILL TO BE SENT: {$unsent}</p>";

            sendEmail("tim@lgbthotline.org", $messageText, "BULK EMAIL SHUTDOWN", null, null, null);
            
            $resultsSummary = [
                'resultsSummary' => (string)$resourcesIncluded,
                'sendCount' => (string)$sendCount,
                'errorCount' => (string)$errorCount,
                'noEmailCount' => (string)$noEmailCount,
                'status' => "Ongoing"
            ];

            echo json_encode($resultsSummary);
        }
    }
}

function sendEmail($to, $message, $email_subject, $bulkSetID = null, $VolunteerID = null, $idnum = null) {
    $mail = new PHPMailer(true);
    
    try {
        // Configure PHPMailer
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = 'smtp.office365.com';
        $mail->Port = 587;
        $mail->SMTPSecure = 'tls';
        $mail->SMTPAuth = true;
        $mail->Username = 'database@lgbthotline.org';
        $mail->Password = 'WoodWood11!!';
        
        $mail->SetFrom('database@lgbthotline.org', 'LGBT National Help Center');
        $mail->addReplyTo('database@lgbthotline.org', 'Tanya');
        $mail->addAddress($to);
        $mail->Subject = $email_subject;
        $mail->msgHTML($message);

        if (!$mail->send()) {
            if ($bulkSetID) {
                $query = "UPDATE bulkLog 
                         SET Status = :error 
                         WHERE bulkSetID = :bulkSetID 
                         AND resourceID = :resourceID";
                $params = [
                    ':error' => 'ERROR: ' . $mail->ErrorInfo,
                    ':bulkSetID' => $bulkSetID,
                    ':resourceID' => $idnum
                ];
                dataQuery($query, $params);
            }
            return false;
        }

        if ($bulkSetID) {
            // Log successful email send
            $queries = [
                "INSERT INTO resourceEditLog (UserName, resourceIDNUM, Action) 
                 VALUES (:volunteerID, :resourceID, 'Email')",
                "UPDATE bulkLog 
                 SET Status = 'Sent' 
                 WHERE bulkSetID = :bulkSetID 
                 AND resourceID = :resourceID"
            ];
            
            foreach ($queries as $query) {
                $params = [
                    ':volunteerID' => $VolunteerID,
                    ':resourceID' => $idnum,
                    ':bulkSetID' => $bulkSetID
                ];
                dataQuery($query, $params);
            }
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Initialize counters
$resourcesIncluded = $sendCount = $errorCount = $noEmailCount = 0;
$done = false;

register_shutdown_function('shutdown', $bulkSetID, $resourcesIncluded, $sendCount, $errorCount, $noEmailCount, $done);

// Get resources to process
$query = "SELECT r.IDNUM as resourceID, r.edate 
          FROM bulkLog b 
          LEFT JOIN Resource r ON (b.ResourceID = r.IDNUM) 
          WHERE b.bulkSetID = :bulkSetID 
          AND b.Status = 'Selected' 
          ORDER BY r.edate ASC";

$results = dataQuery($query, [':bulkSetID' => $bulkSetID]);

if ($results) {
    foreach ($results as $row) {
        $resourcesIncluded++;
        $idnum = $row->resourceID;

        list($to, $subject, $message) = formatMessage($idnum);

        if (empty(trim($to))) {
            $query = "UPDATE bulkLog 
                     SET Status = 'No Email Address' 
                     WHERE bulkSetID = :bulkSetID 
                     AND resourceID = :resourceID";
            dataQuery($query, [
                ':bulkSetID' => $bulkSetID,
                ':resourceID' => $idnum
            ]);
            $noEmailCount++;
        } else {
            $sent = sendEmail($to, $message, $subject, $bulkSetID, $VolunteerID, $idnum);
            $sent ? $sendCount++ : $errorCount++;
            set_time_limit(30);
        }
    }
}

// Update bulk set status
$query = "UPDATE bulkSets 
          SET ResourcesIncluded = :included,
              ResourcesEmailed = :emailed,
              ResourcesErrors = :errors,
              Status = 'Finished'
          WHERE id = :bulkSetID";

dataQuery($query, [
    ':included' => $resourcesIncluded,
    ':emailed' => $sendCount,
    ':errors' => $errorCount,
    ':bulkSetID' => $bulkSetID
]);

// Generate summary report
$summaryMessage = "<p>Bulk Emails Have Just Been Sent.</p>
                  <p>Open Records Processed: {$resourcesIncluded}</p>
                  <p>Emails Sent: {$sendCount}</p>
                  <p>Records Without Email: {$noEmailCount}</p>
                  <p>Errors: {$errorCount}</p>";

// Get detailed results
$query = "SELECT 
            r.edate as Date, 
            b.resourceID, 
            r.Name, 
            r.emailAddress, 
            b.Status 
          FROM bulkLog b 
          LEFT JOIN Resource r ON (b.ResourceID = r.IDNUM) 
          WHERE b.bulkSetID = :bulkSetID 
          ORDER BY r.edate ASC";

$results = dataQuery($query, [':bulkSetID' => $bulkSetID]);

$table = "<table><style>th, td { width: 150px; text-align:center; border-bottom: 1px dotted black; }</style>
          <tr><th>DATE</th><th>IDNUM</th><th>NAME</th><th>EMAIL ADDRESS</th><th>STATUS</th></tr>";

foreach ($results as $row) {
    if ($row->Status !== "Selected") {
        $table .= sprintf(
            "<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>",
            htmlspecialchars($row->Date),
            htmlspecialchars($row->resourceID),
            htmlspecialchars($row->Name),
            htmlspecialchars($row->emailAddress),
            htmlspecialchars($row->Status)
        );
    }
}
$table .= "</table>";

$finalMessage = $summaryMessage . "<div>" . $table . "</div>";

// Send summary emails
sendEmail("tim@lgbthotline.org", $finalMessage, 'BULK EMAIL SENT');
sendEmail("brad@lgbthotline.org", $finalMessage, 'BULK EMAIL SENT');

// Return results
$resultsSummary = [
    'resultsSummary' => (string)$resourcesIncluded,
    'sendCount' => (string)$sendCount,
    'errorCount' => (string)$errorCount,
    'noEmailCount' => (string)$noEmailCount,
    'status' => "Final"
];

echo json_encode($resultsSummary);
$done = true;
exit();
?>
