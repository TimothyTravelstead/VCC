<?php
// Create special debug log function
function debugLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents(__DIR__ . '/POSTCALLLOG_DEBUG.log', $logMessage, FILE_APPEND | LOCK_EX);
}

debugLog("===== POSTCALLLOG START =====");
debugLog("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
debugLog("REQUEST DATA: " . print_r($_REQUEST, true));

// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours
require_once('../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

debugLog("SESSION DATA: " . print_r($_SESSION, true));

set_include_path('/opt/homebrew/bin/pear');
header('Access-Control-Allow-Origin: https://www.volunteerlogin.org');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

require_once 'vendor/autoload.php';

$VolunteerID = $_SESSION['UserID'];

// NOTE: Session remains open until after cleanup code at end of script
// This ensures session variable changes (clearing chat1End, etc.) are persisted
$callSid = $_REQUEST['callSid'];

debugLog("VOLUNTEER ID: " . ($VolunteerID ? $VolunteerID : "NULL"));
debugLog("CALL SID: " . ($callSid ? $callSid : "NULL"));

$sendSuicideEmail = false;
$County = null;
$Senior = null;

function errorLog($message, $originalQuery, $originalResult, $Type, $callSid, $sendEmail = false) {
    $sessionData = json_encode($_SESSION);
    $requestData = json_encode($_REQUEST);
    $volunteerFromSession = $_SESSION['UserID'] ?? null;
    $volunteerFromRequest = $_REQUEST['volunteerID'] ?? null;
    $cleanedOriginalQuery = json_encode($originalQuery) ?? null;

    if($volunteerFromRequest) {
        $volunteerForInsert = $volunteerFromRequest;
    } elseif($volunteerFromSession) {
        $volunteerForInsert = $volunteerFromSession;
    } else {
        $volunteerForInsert = 'Unknown';
    }

    $query = "INSERT INTO ErrorLog VALUES (DEFAULT, now(), 'postCallLog.php', ?, ?, ?, ?, ?, ?, ?, ?)";
    $params = [$callSid, $volunteerForInsert, $message, $sessionData, $requestData, $cleanedOriginalQuery, $originalResult, $Type];

    $result = dataQuery($query, $params);
    if($result === false) {
        die("Volunteer postCallLog.php error Log Routine - Could not update the errorLog");
    }

    // Send email notification to Tim if this is a critical error
    if($sendEmail) {
        $emailSubject = "CRITICAL: Call Log Save Failure";
        $emailBody = "<h2>Call Log Save Failure Alert</h2>";
        $emailBody .= "<p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
        $emailBody .= "<p><strong>Volunteer:</strong> " . htmlspecialchars($volunteerForInsert) . "</p>";
        $emailBody .= "<p><strong>Call SID:</strong> " . htmlspecialchars($callSid) . "</p>";
        $emailBody .= "<p><strong>Error:</strong> " . htmlspecialchars($message) . "</p>";
        $emailBody .= "<p><strong>Error Details:</strong> " . htmlspecialchars($originalResult) . "</p>";
        $emailBody .= "<hr>";
        $emailBody .= "<p><strong>Request Data:</strong></p>";
        $emailBody .= "<pre>" . htmlspecialchars($requestData) . "</pre>";

        sendEmail('Tim@LGBTHotline.org', $emailBody, $emailSubject);
    }
}

function sendEmail($to, $message, $subject = "SUICIDE CALL LOGGED") {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'netsol-smtp-oxcs.hostingplatform.com';
    $mail->Port = 587;
    $mail->SMTPSecure = 'tls';
    $mail->SMTPAuth = true;

    $mail->Username = 'database@glbthotline.org';
    $mail->Password = 'LGBTNHC11!!!';

    $mail->SetFrom('database@glbthotline.org', 'LGBT National Help Center');
    $mail->addReplyTo('database@glbthotline.org', 'Tanya');
    $mail->addAddress($to, '');
    $mail->Subject = $subject;
    $mail->msgHTML($message);

    if (!$mail->send()) {
        echo 'Mailer Error: ' . $mail->ErrorInfo;
        return false;
    } else {
        return true;
    }
}

$message = 'Pre-Call Log Save Start';
errorLog($message, $queryPost, 'Start', 'Start', $callSid);

if(!$VolunteerID && $_REQUEST['UserID']) {
    $VolunteerID = $_REQUEST['UserID'];
    $_SESSION['UserID'] = $VolunteerID;
} elseif(!$VolunteerID && $_REQUEST['volunteerID']) {
    $VolunteerID = $_REQUEST['UserID'];
    $_SESSION['volunteerID'] = $VolunteerID;
} elseif (!$VolunteerID) {
    $query = "SELECT Volunteer FROM CallRouting WHERE CallSid = ?";
    $result = dataQuery($query, [$callSid]);
    if ($result) {
        $VolunteerID = strtolower($result[0]->Volunteer);
        $_SESSION['UserID'] = $VolunteerID;
    }
}

$message = 'Pre-Call Log Save Mid 1';
errorLog($message, $queryPost, 'Mid 1', 'Mid 1', $callSid);

if(!$VolunteerID) {
    $VolunteerID = "ERROR";
}

$Terminate = $_REQUEST['terminate'];
$ZipCode = $_REQUEST['zipCode'];
$City = $_REQUEST['city'];
$State = $_REQUEST['state'];
// Initialize EndTime - will be set from session variables or REQUEST later
// Only use current time as fallback if no other source available
$EndTime = null;

if (!$Terminate) {
    $Terminate = 'SAVE';
}

$SageAge = "";
$SageGotNumber = "";
$Age = "";
$GotNumber = "";
$Hotline = "";
$CallLogNotes = "";

$query = "UPDATE Volunteers SET Ringing = NULL, OnCall = 0 WHERE UserName = ?";
$result = dataQuery($query, [$VolunteerID]);
if($result === false) {
    $message = "Error updating volunteer status";
    errorLog($message, $query, 'ERROR', 'ERROR', $callSid);
    die("Volunteer postCallLog.php Routine - Could not update volunteer status in the database");
}

$query = "UPDATE Volunteers SET Ringing = NULL, OnCall = 0 WHERE LoggedOn = 4 AND TraineeID = ?";
$result = dataQuery($query, [$VolunteerID]);
if($result === false) {
    $message = "Error updating trainee status";
    errorLog($message, $query, 'ERROR', 'ERROR', $callSid);
    die("Volunteer postCallLog.php Routine - Could not update volunteer trainee status in the database");
}

// **REFRESH CACHE FOR POLLING CLIENTS AFTER CALL END**
try {
    require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
    $publisher = new VCCFeedPublisher();
    $publisher->refreshUserListCache();
} catch (Exception $e) {
    error_log("VCCFeedPublisher error on postCallLog: " . $e->getMessage());
}

$message = 'Pre-Call Log Save Mid 2';
errorLog($message, $queryPost, 'Mid 2', 'Mid 2', $callSid);

// These are the actual database column names for checkbox fields
// Only include checkbox fields that actually exist in the database
$checkboxDatabaseFields = [
    'COMEOUT', 'AIDS_HIV', 'TGENDER', 'RELATION', 'PARENT', 'SUICIDE',
    'RUNAWAY', 'VIOLENCE', 'SELF_EST', 'OTHER',
    'AIDS', 'BARS', 'BISEXUAL', 'BOOKSTORE', 'BUSINESS', 'COMMUNITY',
    'COUNSELING', 'CRISIS', 'CULTURAL', 'FUNDRAISE', 'HEALTH', 'HOTEL',
    'HOTLINE', 'LEGAL', 'LESBIAN', 'MEDIA', 'POLITICAL', 'PROFESSIONAL',
    'RECOVERY', 'RELIGION', 'RESTAURANT', 'SENIOR', 'SOCIAL', 'SPORTS',
    'STUDENT', 'SUPPORT', 'TRANSGENDER', 'YOUTH'
];

// Hardcoded database field names - no need to query database every time
$fieldName = [
    'recordid' => true,
    'desk' => true,
    'firstname' => true,
    'lastname' => true,
    'volunteer' => true,
    'date' => true,
    'starttime' => true,
    'endtime' => true,
    'time' => true,
    'gender' => true,
    'age' => true,
    'volunteerrating' => true,
    'info' => true,
    'hotline' => true,
    'bisexual' => true,
    'community' => true,
    'support' => true,
    'social' => true,
    'youth' => true,
    'business' => true,
    'religion' => true,
    'health' => true,
    'aids' => true,
    'media' => true,
    'bookstore' => true,
    'crisis' => true,
    'legal' => true,
    'recovery' => true,
    'sports' => true,
    'student' => true,
    'bars' => true,
    'restaurant' => true,
    'alumni' => true,
    'cultural' => true,
    'employee' => true,
    'professional' => true,
    'political' => true,
    'fundraise' => true,
    'hotel' => true,
    'terminate' => true,
    'got_number' => true,
    'note' => true,
    'area' => true,
    'state' => true,
    'city' => true,
    'zip' => true,
    'comeout' => true,
    'relation' => true,
    'suicide' => true,
    'runaway' => true,
    'violence' => true,
    'parent' => true,
    'aids_hiv' => true,
    'self_est' => true,
    'systime' => true,
    'other' => true,
    'glbtnhc_program' => true,
    'transgender' => true,
    'sexinfo' => true,
    'tgender' => true,
    'lesbian' => true,
    'endstatus' => true,
    'callhistorycountry' => true,
    'calllognotes' => true,
    'callsid' => true,
    'ethnicity' => true,
    'senior_housing' => true,
    'senior_meals' => true,
    'senior_medical' => true,
    'senior_legal' => true,
    'senior_transportation' => true,
    'senior_social' => true,
    'senior_support' => true,
    'senior_other' => true,
    'senior_none' => true,
    'internet_google' => true,
    'internet_facebook' => true,
    'internet_twitter' => true,
    'internet_instagram' => true,
    'internet_other' => true,
    'internet_unknown' => true,
    'counseling' => true,
    'hotline' => true,
    'senior' => true,
];

debugLog("USING HARDCODED DATABASE FIELD NAMES - NO DATABASE QUERY NEEDED");

$fieldlist = "Volunteer,Date";
$valuelist = "?,CURDATE()";
$params = [$VolunteerID];

// Process ALL checkbox fields - all are now uppercase from JavaScript
debugLog("===== PROCESSING CHECKBOX FIELDS =====");
foreach ($checkboxDatabaseFields as $dbField) {
    // Get value from request using exact uppercase field name
    $value = $_REQUEST[$dbField] ?? '0';
    debugLog("CHECKBOX: " . $dbField . " = '" . $value . "'");
    
    // Add field to INSERT statement
    $fieldlist .= "," . $dbField;
    $valuelist .= ",?";
    
    // Set value to 1 or 0 (never NULL)
    if ($value == "1" || $value == 1) {
        $params[] = "1";
        debugLog("ADDED CHECKBOX: " . $dbField . " = '1' (CHECKED)");
    } else {
        $params[] = "0";
        debugLog("ADDED CHECKBOX: " . $dbField . " = '0' (UNCHECKED)");
    }
}
debugLog("CHECKBOX PROCESSING COMPLETE. CURRENT FIELDLIST: " . $fieldlist);
debugLog("CURRENT PARAMS COUNT: " . count($params));

debugLog("===== PROCESSING REQUEST FIELDS =====");
foreach ($_REQUEST as $name => $value) {
    debugLog("REQUEST FIELD: " . $name . " = '" . $value . "'");
    switch($name) {
        case "callLogID":
            if($value == 1) {
                $StartTime = $_SESSION['chat1Start'] ?? null;
                $EndTime = $_SESSION['chat1End'] ?? null;
            } elseif ($value == 2) {
                $StartTime = $_SESSION['chat2Start'] ?? null;
                $EndTime = $_SESSION['chat2End'] ?? null;
            } elseif ($value == 3) {
                $StartTime = $_SESSION['callStart'] ?? null;
                $EndTime = $_SESSION['callEnd'] ?? null;
            }
            debugLog("Retrieved from session - callLogID=$value, StartTime=$StartTime, EndTime=$EndTime");
            break;
        case "startTime":
            $StartTime = $value;
            debugLog("StartTime set from REQUEST: $StartTime");
            break;
        case "endTime":
            if($value) {
                $EndTime = $value;
                debugLog("EndTime set from REQUEST: $EndTime");
            }
            break;
        case "TERMINATE":
            break;
        case "PHPSESSID":
            break;
        case "GOT_NUMBER":
            $GotNumber = urldecode($value);
            $GotNumber = html_entity_decode($GotNumber, ENT_QUOTES, 'UTF-8');
            debugLog("PROCESSED GOT_NUMBER: '" . $GotNumber . "'");
            break;
        case "SAGE_GOT_NUMBER":
            $SageGotNumber = urldecode($value);
            $SageGotNumber = html_entity_decode($SageGotNumber, ENT_QUOTES, 'UTF-8');
            break;
        case "AGE":
            $Age = urldecode($value);
            $Age = html_entity_decode($Age, ENT_QUOTES, 'UTF-8');
            break;
        case "sageAge":
            $SageAge = urldecode($value);
            $SageAge = html_entity_decode($SageAge, ENT_QUOTES, 'UTF-8');
            break;
        case "VolunteerRating":
            // This field is handled in the general processing loop below
            break;
        case "GLBTNHC_Program":
            $Hotline = urldecode($value);
            $Hotline = html_entity_decode($Hotline, ENT_QUOTES, 'UTF-8');
            break;
        case "CallLogNotes":
            $CallLogNotes = urldecode($value);  // Decode URL encoding first
            $CallLogNotes = html_entity_decode($CallLogNotes, ENT_QUOTES, 'UTF-8');  // Then decode HTML entities
            debugLog("PROCESSED CallLogNotes: '" . $CallLogNotes . "'");
            break;
        case "SUICIDE":
            if($value == 1) {
                $sendSuicideEmail = true;
            }
            break;
    }

    // Skip fields that are handled elsewhere or are not database fields
    if ($name != "AGE" &&              // Handled specially for SAGE vs regular
        $name != "sageAge" &&          // Handled specially for SAGE
        $name != "GOT_NUMBER" &&       // Handled specially for SAGE vs regular
        $name != "SAGE_GOT_NUMBER" &&  // Handled specially for SAGE
        $name != "CallLogNotes" &&     // Added manually to INSERT later
        $name != "PHPSESSID" && 
        $name != 'callLogID' && 
        $name != 'zipCode' && 
        $name != "city" && 
        $name != "state" && 
        $name != "terminate" &&
        $name != "callSid" &&
        $name != "startTime" &&
        $name != "endTime" &&
        $name != "TERMINATE" &&
        !in_array($name, $checkboxDatabaseFields) &&  // Skip checkbox fields - already processed above
        isset($fieldName[strtolower($name)])) {
            
            // Regular fields - add as usual with HTML decoding
            $decodedValue = urldecode($value);
            $decodedValue = html_entity_decode($decodedValue, ENT_QUOTES, 'UTF-8');
            $fieldlist .= "," . $name;
            $valuelist .= ",?";
            $params[] = $decodedValue;
            debugLog("ADDED GENERAL FIELD: " . $name . " = '" . $decodedValue . "'");
    }
}

$message = 'Pre-Call Log Save Mid 3';
errorLog($message, $queryPost, 'Mid 3', 'Mid 3', $callSid);

if($Hotline == "SAGE") {
    $fieldlist .= ",Age,GOT_NUMBER";
    $valuelist .= ",?,?";
    $params[] = $SageAge;
    $params[] = $SageGotNumber;
} else {
    $fieldlist .= ",Age,GOT_NUMBER";
    $valuelist .= ",?,?";
    $params[] = $Age;
    $params[] = $GotNumber;
}

$message = 'Pre-Call Log Save Mid 4';
errorLog($message, $queryPost, 'Mid 4', 'Mid 4', $callSid);

$query = "SELECT FirstName, LastName FROM Volunteers WHERE UserName = ?";
$volunteerInfo = dataQuery($query, [$VolunteerID]);
if ($volunteerInfo) {
    $FirstName = $volunteerInfo[0]->FirstName;
    $LastName = $volunteerInfo[0]->LastName;
}

$Desk = "9";

$fieldlist .= ", FirstName, LastName";
$valuelist .= ", ?, ?";
$params = array_merge($params, [$FirstName, $LastName]);

$message = 'Pre-Call Log Save Mid 5';
errorLog($message, $queryPost, 'Mid 5', 'Mid 5', $callSid);

// Try to get start time from session, CallerHistory, or Posts (for chats)
if(!$StartTime || $StartTime == '0000-00-00 00:00:00') {
    if($callSid) {
        // First try CallerHistory (for phone calls)
        $query = "SELECT callStart FROM CallerHistory WHERE callSid = ? LIMIT 1";
        $result = dataQuery($query, [$callSid]);
        if ($result && count($result) > 0) {
            $StartTime = $result[0]->callStart;
            debugLog("Found StartTime in CallerHistory: " . $StartTime);
        } else {
            // Try Posts table (for chats)
            $query = "SELECT TS_Post FROM Posts WHERE ThreadID = ? ORDER BY TS_Post ASC LIMIT 1";
            $result = dataQuery($query, [$callSid]);
            if ($result && count($result) > 0) {
                $StartTime = $result[0]->TS_Post;
                debugLog("Found StartTime in Posts: " . $StartTime);
            }
        }
    }
}

// Validate StartTime - if still empty or invalid, use current time minus 1 minute
if(!$StartTime || $StartTime == '0000-00-00 00:00:00') {
    $StartTime = date('Y-m-d H:i:s', strtotime('-1 minute'));
    debugLog("WARNING: No valid StartTime found, using 1 minute ago: " . $StartTime);
}

// Validate EndTime - if empty, invalid, or BEFORE StartTime, use current time as fallback
$startTimestamp = strtotime($StartTime);
$endTimestamp = $EndTime ? strtotime($EndTime) : false;

if (!$EndTime || $EndTime == '0000-00-00 00:00:00') {
    $EndTime = date('Y-m-d H:i:s');
    $endTimestamp = strtotime($EndTime);
    debugLog("WARNING: No valid EndTime found, using current time: " . $EndTime);
} elseif ($endTimestamp && $endTimestamp < $startTimestamp) {
    // EndTime is before StartTime - this is the bug we're fixing
    // Use current time as fallback (chat likely ended when volunteer submitted the log)
    debugLog("WARNING: EndTime ($EndTime) is before StartTime ($StartTime) - stale session value detected");
    $EndTime = date('Y-m-d H:i:s');
    $endTimestamp = strtotime($EndTime);
    debugLog("FIXED: Using current time as EndTime: " . $EndTime);
} else {
    debugLog("Using EndTime: " . $EndTime);
}

// Calculate time duration - validate it's reasonable (< 24 hours)
$durationSeconds = $endTimestamp - $startTimestamp;

// If duration is still invalid (> 24 hours), use NULL for Time field
if ($durationSeconds > 86400) {
    debugLog("WARNING: Duration exceeds 24 hours: " . $durationSeconds . " seconds. StartTime: " . $StartTime . ", EndTime: " . $EndTime);
    // Use NULL for Time field instead of TIMEDIFF
    $fieldlist .= ", Desk, Zip, City, State, Area, StartTime, EndTime, Time, CallLogNotes, CallSid";
    $valuelist .= ", ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?";
    $params = array_merge($params, [
        $Desk,
        $ZipCode,
        $City,
        $State,
        $County,
        $StartTime,
        $EndTime,
        $CallLogNotes,
        $callSid
    ]);
} else {
    // Duration is reasonable, use TIMEDIFF
    debugLog("Valid time duration: " . $durationSeconds . " seconds (" . gmdate("H:i:s", $durationSeconds) . ")");
    $fieldlist .= ", Desk, Zip, City, State, Area, StartTime, EndTime, Time, CallLogNotes, CallSid";
    $valuelist .= ", ?, ?, ?, ?, ?, ?, ?, TIMEDIFF(?, ?), ?, ?";
    $params = array_merge($params, [
        $Desk,
        $ZipCode,
        $City,
        $State,
        $County,
        $StartTime,
        $EndTime,
        $EndTime,
        $StartTime,
        $CallLogNotes,
        $callSid
    ]);
}

debugLog("===== BUILDING FINAL INSERT QUERY =====");
debugLog("FINAL FIELDLIST: " . $fieldlist);
debugLog("FINAL VALUELIST: " . $valuelist);
debugLog("FINAL PARAMS COUNT: " . count($params));
debugLog("FINAL PARAMS: " . print_r($params, true));

$query = "INSERT INTO CallLog ($fieldlist) VALUES ($valuelist)";
debugLog("FINAL QUERY: " . $query);

debugLog("===== EXECUTING DATABASE INSERT =====");
$result = dataQuery($query, $params);
debugLog("DATAQUERY RESULT: " . print_r($result, true));

if($result === false || (is_array($result) && isset($result['error']))) {
    debugLog("===== DATABASE INSERT FAILED =====");
    $message = "Error inserting call log - INSERT statement failed";
    $errorDetails = is_array($result) ? $result['message'] : 'Unknown error';
    debugLog("ERROR DETAILS: " . $errorDetails);
    errorLog($message, $query, $errorDetails, 'ERROR', $callSid, true);
    die("Volunteer postCallLog.php Routine - Could not insert into call log");
} else {
    debugLog("===== DATABASE INSERT SUCCESS =====");
    debugLog("Insert successful! Result: " . print_r($result, true));
}

// VERIFICATION: Confirm the call log was actually saved to prevent silent failures
debugLog("===== VERIFYING CALL LOG WAS SAVED =====");
$verifyQuery = "SELECT RecordID FROM CallLog WHERE CallSid = ? AND VOLUNTEER = ? ORDER BY RecordID DESC LIMIT 1";
$verifyResult = dataQuery($verifyQuery, [$callSid, $VolunteerID]);

if(!$verifyResult || (is_array($verifyResult) && isset($verifyResult['error'])) || count($verifyResult) === 0) {
    debugLog("===== VERIFICATION FAILED - CALL LOG NOT FOUND IN DATABASE =====");
    $message = "CRITICAL: Call log INSERT reported success but record not found in database";
    $errorDetails = "INSERT query appeared to succeed but verification query found no matching record. This indicates a silent failure.";
    debugLog("VERIFICATION ERROR: " . $errorDetails);
    errorLog($message, $query, $errorDetails, 'VERIFICATION_FAILURE', $callSid, true);
    die("Volunteer postCallLog.php Routine - Call log verification failed");
} else {
    $recordID = $verifyResult[0]->RecordID;
    debugLog("===== VERIFICATION SUCCESS - Call Log saved with RecordID: " . $recordID . " =====");
}

if($sendSuicideEmail) {
    $query = "SELECT StartTime, CONCAT(FirstName, ' ', LastName) as Name, Time, CallLogNotes 
              FROM CallLog 
              WHERE SUICIDE = 1 AND VOLUNTEER = ? 
              ORDER BY RecordID DESC LIMIT 1";
    
    $result = dataQuery($query, [$VolunteerID]);
    if($result) {
        $callData = $result[0];
        
        $MessageText = "<p>A suicide call has just been logged.</p>";
        $MessageText .= "<br /><strong>Volunteer:</strong> " . $callData->Name;
        $MessageText .= "<br /><br /><br /><strong>Start of Call:</strong> " . $callData->StartTime;
        $MessageText .= "<br /><br /><br /><strong>Length of Call:</strong> " . $callData->Time;
        $MessageText .= "<br /><br /><br /><strong>Call Notes:</strong> " . htmlspecialchars(urldecode($callData->CallLogNotes));

        $emails = [
            "aaron@lgbthotline.org",
            "tatiana@lgbthotline.org",
            "matt@lgbthotline.org",
            "brad@lgbthotline.org",
            "travelstead@mac.com"
        ];

        foreach($emails as $email) {
            $messageSent = sendEmail($email, $MessageText);
            if(!$messageSent) {
                errorLog("Failed to send suicide email to $email", $query, 'ERROR SENDING SUICIDE EMAIL', 'ERROR', $callSid);
            }
        }
    }
}

// Clear end time session variables after successful save
// (Start time variables are cleared when call ends in volunteerPosts.php)
if (isset($_SESSION['chat1End'])) {
    unset($_SESSION['chat1End']);
    debugLog("Cleared chat1End from session");
}
if (isset($_SESSION['chat2End'])) {
    unset($_SESSION['chat2End']);
    debugLog("Cleared chat2End from session");
}
if (isset($_SESSION['callEnd'])) {
    unset($_SESSION['callEnd']);
    debugLog("Cleared callEnd from session");
}

if (isset($_SESSION['chat1Start'])) {
    unset($_SESSION['chat1Start']);
    debugLog("Cleared chat1Start from session (backup cleanup)");
}
if (isset($_SESSION['chat2Start'])) {
    unset($_SESSION['chat2Start']);
    debugLog("Cleared chat2Start from session (backup cleanup)");
}
if (isset($_SESSION['callStart'])) {
    unset($_SESSION['callStart']);
    debugLog("Cleared callStart from session (backup cleanup)");
}

// Now close the session to persist all changes (including cleared variables)
session_write_close();
debugLog("Session closed - all cleanup changes persisted");

debugLog("===== POSTCALLLOG COMPLETED SUCCESSFULLY =====");
echo "OK";

debugLog("===== POSTCALLLOG SCRIPT END =====");
