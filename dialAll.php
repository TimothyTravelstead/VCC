<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours
if (!file_exists('../private_html/db_login.php')) {
    throw new Exception("Database configuration file not found");
}
require_once('../private_html/db_login.php');

// Twilio webhooks are server-to-server requests - no session needed
// Do NOT use requireAuth() here as Twilio has no user session

// Force error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Buffer all output
ob_start();

try {

    // Verify WebAddress is set from db_login.php
    if (!isset($WebAddress)) {
        throw new Exception("WebAddress not configured in database settings");
    }

    // Twilio Received Variables with null coalescing and debug info collection
    $debug_info = [];
    $From = $_REQUEST['From'] ?? 'Not Set';
    $Length = $_REQUEST["DialCallDuration"] ?? 'Not Set';
    $CallSid = $_REQUEST["CallSid"] ?? 'Not Set';
    $CallStatus = $_REQUEST["DialCallStatus"] ?? 'Not Set';
    $FromCity = $_REQUEST["FromCity"] ?? 'Not Set';
    $FromCountry = $_REQUEST["FromCountry"] ?? 'Not Set';
    $FromState = $_REQUEST["FromState"] ?? 'Not Set';
    $FromZip = $_REQUEST["FromZip"] ?? 'Not Set';
    $To = $_REQUEST["To"] ?? 'Not Set';
    $ToCity = $_REQUEST["ToCity"] ?? 'Not Set';
    $ToCountry = $_REQUEST["ToCountry"] ?? 'Not Set';
    $ToState = $_REQUEST["ToState"] ?? 'Not Set';
    $ToZip = $_REQUEST["ToZip"] ?? 'Not Set';
    $FinalCallReport = false;
    $clientCaller = explode(":", $From);
    $VccCallStatus = $_REQUEST["vccStatus"] ?? 'Not Set';

    $debug_info[] = "Call From: $From, To: $To, Status: $VccCallStatus";

    // Determine Hotline based on To number
    switch ($To) {
        case "+18888434564":
        case "+14159929723":
            $Hotline = "GLNH";
            break;
        
        case "+16463621511":
        case "+12129890999":
            $Hotline = "GLSB-NY";
            break;

        case "+14153550999":
            $Hotline = "local";
            break;

        case "+18882347243":
        case "+14159929741":
            $Hotline = "SENIOR";
            break;

        case "+18886885428":
            $Hotline = "OUT";
            break;

        case "+18002467743":
        case "+14159928403":
        default:
            $Hotline = "Youth";
            break;
    }

    $debug_info[] = "Determined Hotline: $Hotline";

    // Clear routing data for non-active calls
    $query = "UPDATE Volunteers 
              SET Ringing = NULL,
              HotlineName = NULL,
              CallCity = NULL,
              CallState = NULL,
              CallZip = NULL,
              IncomingCallSid = NULL 
              WHERE IncomingCallSid = ? 
              AND oncall = 0";
    $clearResult = dataQuery($query, [$CallSid]);
    if ($clearResult === false) {
        throw new Exception("Failed to clear routing data");
    }

    // Check if call has been answered
    $query = "SELECT Volunteer FROM CallRouting WHERE CallSid = ?";
    $result = dataQuery($query, [$CallSid]);
    if ($result === false) {
        throw new Exception("Failed to check call routing status");
    }
    $answeredCallUserId = !empty($result) ? $result[0]->Volunteer : null;
    $debug_info[] = "Call answered status: " . ($answeredCallUserId ? "Answered by $answeredCallUserId" : "Not answered");

    // Clear any previous output and set headers
    ob_clean();

    // Prevent Twilio from caching TwiML responses
    header('Cache-Control: no-cache, no-store, must-revalidate, private');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Type: text/xml');

    // Start XML response
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<Response>\n";


    if (!$answeredCallUserId) {
        // CALLCONTROL FIX: Get available volunteers who can receive calls
        // Only volunteers in CallControl table with can_receive_calls = 1 are eligible
        // This automatically handles training control (only controller can receive calls)
        $query = "SELECT v.username, v.PreferredHotline
                  FROM Volunteers v
                  INNER JOIN CallControl cc ON v.UserName = cc.user_id
                  WHERE cc.can_receive_calls = 1
                  AND v.OnCall = 0
                  AND (v.Active1 IS NULL OR v.Active1 = 'Blocked')
                  AND (v.Active2 IS NULL OR v.Active2 = 'Blocked')
                  AND v.ChatInvite IS NULL
                  AND v.Ringing IS NULL
                  AND v.ChatOnly = 0";
        $availableVolunteers = dataQuery($query);

        if ($availableVolunteers === false) {
            throw new Exception("Failed to query available volunteers");
        }

        // Handle initial greeting based on VccCallStatus
        if ($VccCallStatus == "firstRing") {
            switch ($Hotline) {
                case "GLNH":
                    $greetingFile = "Open_GLNH_Initial_Greeting2.mp3";
                    break;
                case "SENIOR":
                    $greetingFile = "Open_Senior_Initial_Greeting2.mp3";
                    break;
                case "Youth":
                    $greetingFile = "Open-youth-initial-greeting2.mp3";
                    break;
                case "GLSB-NY":
                    $greetingFile = "Open_GLSB-NY_initial-greeting2.mp3";
                    break;
                case "OUT":
                    $greetingFile = "open_connecting_coming_out_hotline2.mp3";
                    break;
                default:
                    $greetingFile = "Open_GLNH_Initial_Greeting2.mp3";
                    break;
            }

            // Generate audio URL
            $audioPath = $WebAddress . "/Audio/" . $greetingFile;

            // Output with proper XML encoding
            echo "    <Play>" . htmlspecialchars($audioPath, ENT_XML1, 'UTF-8') . "</Play>\n";
        } else {
            // Update call status for second ring
            $query = "UPDATE CallRouting SET callStatus = 'secondRing' WHERE CallSid = ?";
            $updateResult = dataQuery($query, [$CallSid]);
            if ($updateResult === false) {
                throw new Exception("Failed to update call status to secondRing");
            }
        }

        // Set up dial options
        $dialPath = $WebAddress . "/unAnsweredCall.php";
        $statusCallback = $WebAddress . "/twilioStatus.php";
        $statusCallbackEvents = "initiated ringing answered completed";
        echo "    <Dial action='" . htmlspecialchars($dialPath) . "' method='POST'>\n";

        // Ring normal volunteers via their Twilio clients
        foreach ($availableVolunteers as $volunteer) {
            echo "        <Client statusCallback='" . htmlspecialchars($statusCallback) . "' statusCallbackEvent='" . $statusCallbackEvents . "'>" . htmlspecialchars($volunteer->username) . "</Client>\n";

            // Update volunteer status
            $query = "UPDATE Volunteers
                      SET Ringing = ?,
                          HotlineName = ?,
                          CallCity = ?,
                          CallState = ?,
                          CallZip = ?,
                          IncomingCallSid = ?
                      WHERE UserName = ?";
            $params = [
                $Hotline . " - " . $From,
                $Hotline,
                $FromCity,
                $FromState,
                $FromZip,
                $CallSid,
                $volunteer->username
            ];
            $updateResult = dataQuery($query, $params);
            if ($updateResult === false) {
            }

            // Set Redis ringing state for fast-poll
            try {
                require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
                $publisher = new VCCFeedPublisher();
                $publisher->setRinging($volunteer->username, $CallSid, [
                    'hotline' => $Hotline,
                    'from' => $From,
                    'city' => $FromCity,
                    'state' => $FromState
                ]);
            } catch (Exception $e) {
                error_log("VCCFeedPublisher setRinging error in dialAll: " . $e->getMessage());
            }
        }

        echo "    </Dial>\n";

        // Refresh Redis user list cache for polling clients
        try {
            require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
            $publisher = new VCCFeedPublisher();
            $publisher->refreshUserListCache();
        } catch (Exception $e) {
            error_log("VCCFeedPublisher refreshUserListCache error in dialAll: " . $e->getMessage());
        }
    } else {
        // Redirect if call already answered
        $redirectPath = $WebAddress . "/unAnsweredCall.php";
        echo "    <Redirect method='POST'>" . htmlspecialchars($redirectPath) . "</Redirect>\n";
    }
    
    echo "</Response>\n";

} catch (Exception $e) {
    // Clear any previous output
    ob_clean();
    
    // Return error as valid TwiML
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<Response>\n";
    echo "    <Say>Error encountered: " . htmlspecialchars($e->getMessage()) . "</Say>\n";
    echo "    <Say>Debug information:</Say>\n";
    if (!empty($debug_info)) {
        foreach ($debug_info as $info) {
            echo "    <Say>" . htmlspecialchars($info) . "</Say>\n";
        }
    }
    echo "    <Say>Call details: To: " . htmlspecialchars($To) . ", From: " . 
         htmlspecialchars($From) . ", Status: " . htmlspecialchars($VccCallStatus) . "</Say>\n";
    echo "</Response>\n";
}

// Flush and end output buffer
ob_end_flush();