<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours
if (!file_exists('../private_html/db_login.php')) {
    throw new Exception("Database configuration file not found");
}
require_once('../private_html/db_login.php');

// Twilio webhooks are server-to-server requests - no session needed
// Do NOT use requireAuth() here as Twilio has no user session

// Buffer all output
ob_start();

try {

    // Verify WebAddress is set from db_login.php
    if (!isset($WebAddress)) {
        throw new Exception("WebAddress not configured in database settings");
    }

    // Get Twilio variables with null coalescing
    $From = $_REQUEST['From'] ?? 'Not Set';
    $Length = $_REQUEST["CallDuration"] ?? 'Not Set';
    $CallSid = $_REQUEST["CallSid"] ?? 'Not Set';
    $CallStatus = "unset";  // Override incoming status
    $FromCity = $_REQUEST["FromCity"] ?? 'Not Set';
    $FromCountry = $_REQUEST["FromCountry"] ?? 'Not Set';
    $FromState = $_REQUEST["FromState"] ?? 'Not Set';
    $FromZip = $_REQUEST["FromZip"] ?? 'Not Set';
    $To = $_REQUEST["To"] ?? 'Not Set';
    $ToCity = $_REQUEST["ToCity"] ?? 'Not Set';
    $ToCountry = $_REQUEST["ToCountry"] ?? 'Not Set';
    $ToState = $_REQUEST["ToState"] ?? 'Not Set';
    $ToZip = $_REQUEST["ToZip"] ?? 'Not Set';

    // Clear any previous output
    ob_clean();

    // Prevent Twilio from caching TwiML responses
    header('Cache-Control: no-cache, no-store, must-revalidate, private');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Set content type for XML response
    header('Content-Type: text/xml');

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

    $volunteers = dataQuery($query);

    if ($volunteers === false) {
        throw new Exception("Database query failed: Unable to retrieve volunteers");
    }


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

        case "+18002467743":
        case "+14159928403":
            $Hotline = "Youth";
            break;

        case "+18886885428":
            $Hotline = "OUT";
            break;

        default:
            $Hotline = "Youth";
            break;
    }
    
    // Count volunteers by hotline type (including both normal and training participants)
    $YouthVolunteers = 0;
    $SageVolunteers = 0;
    $availableVolunteers = [];
    $availableTrainingParticipants = [];

    foreach ($volunteers as $volunteer) {
        $availableVolunteers[] = [
            'username' => $volunteer->username,
            'preferred_hotline' => $volunteer->PreferredHotline
        ];

        if ($volunteer->PreferredHotline == "Youth") {
            $YouthVolunteers++;
        } elseif ($volunteer->PreferredHotline == "SENIOR") {
            $SageVolunteers++;
        }
    }

    // Start XML output
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<Response>\n";

    // Check if we have appropriate volunteers available
    if (($Hotline == "Youth" && $YouthVolunteers > 0) || 
        ($Hotline == "SENIOR" && $SageVolunteers > 0)) {
        
        // Play appropriate greeting
        $greetingFile = match($Hotline) {
            "GLNH" => "Open_GLNH_Initial_Greeting2.mp3",
            "SENIOR" => "Open_Senior_Initial_Greeting2.mp3",
            "Youth" => "Open-youth-initial-greeting2.mp3",
            "GLSB-NY" => "Open_GLSB-NY_initial-greeting2.mp3",
            "OUT" => "open_connecting_coming_out_hotline2.mp3",
            default => "Open_GLNH_Initial_Greeting2.mp3"
        };

        // Generate audio URL
        $audioPath = $WebAddress . "/Audio/" . $greetingFile;

        // Output with proper XML encoding
        echo "    <Play>" . htmlspecialchars($audioPath, ENT_XML1, 'UTF-8') . "</Play>\n";

        // Set up dial options
        $dialPath = $WebAddress . "/dialAll.php?vccStatus=secondRing";
        $statusCallback = $WebAddress . "/twilioStatus.php";
        $statusCallbackEvents = "initiated ringing answered completed";
        echo "    <Dial timeout='15' action='" . htmlspecialchars($dialPath) . "' method='POST'>\n";
        echo "        <Client statusCallback='" . htmlspecialchars($statusCallback) . "' statusCallbackEvent='" . $statusCallbackEvents . "'>Ringer</Client>\n";

        // Ring normal volunteers via their Twilio clients
        foreach ($availableVolunteers as $volunteer) {
            if ($volunteer['preferred_hotline'] == $Hotline) {
                echo "        <Client statusCallback='" . htmlspecialchars($statusCallback) . "' statusCallbackEvent='" . $statusCallbackEvents . "'>" . htmlspecialchars($volunteer['username']) . "</Client>\n";

                // Update volunteer status
                $updateQuery = "UPDATE Volunteers
                          SET Ringing = ?,
                              HotlineName = ?,
                              CallCity = ?,
                              CallState = ?,
                              CallZip = ?,
                              IncomingCallSid = ?
                          WHERE UserName = ?";

                $updateResult = dataQuery($updateQuery, [
                    $Hotline . " - " . $From,
                    $Hotline,
                    $FromCity,
                    $FromState,
                    $FromZip,
                    $CallSid,
                    $volunteer['username']
                ]);

                // Set Redis ringing state for fast-poll
                try {
                    require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
                    $publisher = new VCCFeedPublisher();
                    $publisher->setRinging($volunteer['username'], $CallSid, [
                        'hotline' => $Hotline,
                        'from' => $From,
                        'city' => $FromCity,
                        'state' => $FromState
                    ]);
                } catch (Exception $e) {
                    error_log("VCCFeedPublisher setRinging error: " . $e->getMessage());
                }
            }
        }

        echo "    </Dial>\n";

        // Refresh Redis user list cache for polling clients
        try {
            require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
            $publisher = new VCCFeedPublisher();
            $publisher->refreshUserListCache();
        } catch (Exception $e) {
            error_log("VCCFeedPublisher refreshUserListCache error in dialHotline: " . $e->getMessage());
        }

        // Handle training participants separately
        // They cannot be ringed via <Client> tags (Device busy)
        // Instead, update their status so they see the call and can answer via UI
        // When they click Answer, twilioRedirect.php will route caller to their conference
        foreach ($availableTrainingParticipants as $participant) {
            if ($participant['preferred_hotline'] == $Hotline) {
                $username = $participant['username'];
                $loggedOnStatus = $participant['logged_on'];

                // For trainees (LoggedOn=6), find their trainer
                $targetConference = $username;
                if ($loggedOnStatus == 6) {
                    $findTrainerQuery = "SELECT UserName FROM volunteers
                                       WHERE FIND_IN_SET(?, TraineeID) > 0
                                       AND LoggedOn = 4";
                    $trainerResult = dataQuery($findTrainerQuery, [$username]);
                    if ($trainerResult && count($trainerResult) > 0) {
                        $targetConference = $trainerResult[0]->UserName;
                    }
                }

                // Update training participant's status to show incoming call
                $updateQuery = "UPDATE Volunteers
                          SET Ringing = ?,
                              HotlineName = ?,
                              CallCity = ?,
                              CallState = ?,
                              CallZip = ?,
                              IncomingCallSid = ?
                          WHERE UserName = ?";

                $updateResult = dataQuery($updateQuery, [
                    $Hotline . " - " . $From,
                    $Hotline,
                    $FromCity,
                    $FromState,
                    $FromZip,
                    $CallSid,
                    $username
                ]);

                // Set Redis ringing state for fast-poll (training participants)
                try {
                    require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
                    $publisher = new VCCFeedPublisher();
                    $publisher->setRinging($username, $CallSid, [
                        'hotline' => $Hotline,
                        'from' => $From,
                        'city' => $FromCity,
                        'state' => $FromState,
                        'training' => true
                    ]);
                } catch (Exception $e) {
                    error_log("VCCFeedPublisher setRinging error (training): " . $e->getMessage());
                }
            }
        }
    } else {
        // Redirect if no appropriate volunteers available
        $redirectPath = $WebAddress . "/dialAll.php?vccStatus=firstRing";
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
    echo "</Response>\n";
}

// Flush and end output buffer
ob_end_flush();