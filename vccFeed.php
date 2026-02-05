<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day volunteer sessions
require_once('../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// CRITICAL: Capture session data immediately and release lock to prevent blocking other requests
// This must happen BEFORE any file I/O, debug logging, or other slow operations
$sessionId = session_id();
$sessionDataRaw = $_SESSION; // Capture entire session array while we have the lock

// Release session lock IMMEDIATELY (prevents blocking other requests from this user)
session_write_close();

// CRITICAL: Ensure script terminates when client disconnects
// This is the primary defense against orphaned PHP-FPM processes
ignore_user_abort(false);

// PHP timeout: 45 seconds (safely above the 30-second loop duration)
// Shorter timeout = faster cleanup of orphaned processes
set_time_limit(45);
$count = 0;

// Server Sent Events Headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
// Disable Nginx proxy buffering - enables faster disconnect detection
header('X-Accel-Buffering: no');

echo "retry: 3000\n";  // Client reconnects after 3 seconds (was 1 second)

// **CUSTOM SESSION LOADING** - Read session data from private file to bypass permission issues
// This happens AFTER session lock release, so file I/O doesn't block other requests
$sessionData = [];
$customSessionDebug = "";
try {
    $customSessionFile = '../private_html/session_' . $sessionId . '.json';
    if (file_exists($customSessionFile)) {
        $customSessionContent = file_get_contents($customSessionFile);
        $customSessionData = json_decode($customSessionContent, true);

        if ($customSessionData && isset($customSessionData['data'])) {
            $sessionData = $customSessionData['data'];
            $customSessionDebug .= "Custom session file loaded successfully: $customSessionFile\n";
            $customSessionDebug .= "Custom session timestamp: " . date('Y-m-d H:i:s', $customSessionData['timestamp']) . "\n";
        } else {
            $customSessionDebug .= "Custom session file exists but invalid format: $customSessionFile\n";
        }
    } else {
        $customSessionDebug .= "Custom session file not found: $customSessionFile\n";
        // Fallback to standard session (captured before lock release)
        $sessionData = $sessionDataRaw;
    }
} catch (Exception $e) {
    $customSessionDebug .= "Error loading custom session: " . $e->getMessage() . "\n";
    // Fallback to standard session (captured before lock release)
    $sessionData = $sessionDataRaw;
}

// Extract commonly used variables for convenience
$VolunteerID = $sessionData['UserID'] ?? null;
$AdminUser = $sessionData['AdminUser'] ?? null;
$UserName = $sessionData['UserName'] ?? null;
$FullName = $sessionData['FullName'] ?? null;
$Admin = $sessionData['Admin'] ?? null;
$ChatOnlyFlag = $sessionData['ChatOnlyFlag'] ?? null;
$Trainee = $sessionData['Trainee'] ?? null;
$Desk = $sessionData['Desk'] ?? null;

// DEBUG: Optional debug logging (disabled by default for production performance)
// To enable: Create file /tmp/VCCFEED_DEBUG_MODE or set environment variable
if (file_exists('/tmp/VCCFEED_DEBUG_MODE') || getenv('VCCFEED_DEBUG') === 'true') {
    try {
        $immediateDebug = "\n" . date('Y-m-d H:i:s') . " - ===== VCCFEED.PHP SESSION CHECK =====\n";
        $immediateDebug .= "Session ID: " . $sessionId . "\n";
        $immediateDebug .= "Session lock released immediately after session_start() to prevent blocking\n";
        $immediateDebug .= "Session data captured:\n";
        $immediateDebug .= print_r($sessionData, true);
        $immediateDebug .= "Session save path: " . session_save_path() . "\n";
        $immediateDebug .= "Session name: " . session_name() . "\n";
        $immediateDebug .= "HTTP Cookie header: " . ($_SERVER['HTTP_COOKIE'] ?? 'NOT SET') . "\n";
        $immediateDebug .= "\nCUSTOM SESSION DEBUG:\n" . $customSessionDebug;
        $immediateDebug .= "Parsed VolunteerID: " . var_export($VolunteerID, true) . "\n";
        $immediateDebug .= "Parsed AdminUser: " . var_export($AdminUser, true) . "\n";
        $immediateDebug .= "================================\n";
        file_put_contents('session_debug.txt', $immediateDebug, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        // Ignore debug logging errors
    }
}

// TEST ASSIGN IF NOT OTHERWISE
$Reset = $_REQUEST['reset'];

// AUTHENTICATION CHECK: Reject unauthenticated requests to prevent resource exhaustion
// A valid user must have either a VolunteerID (regular user) or AdminUser (admin user)
if (empty($VolunteerID) && empty($AdminUser)) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: text/event-stream');
    echo "event: error\n";
    echo "data: {\"error\": \"Unauthorized - please log in\"}\n\n";
    flush();
    exit();
}

// EventSource loop: 15 iterations × 2 seconds = 30 seconds max runtime
// Shorter connections = faster cleanup of orphaned processes
// Client will automatically reconnect after 30 seconds
while($count < 15) {
    // Enhanced connection abort detection
    if(connection_aborted()) {
        exit();
    }

    // Additional check: verify connection status
    if(connection_status() != CONNECTION_NORMAL) {
        exit();
    }

    // DATABASE CHECK: Verify user is still logged in (every iteration)
    // This catches cases where user logged out but browser tab still has EventSource running
    if ($VolunteerID) {
        $authCheckQuery = "SELECT LoggedOn FROM volunteers WHERE UserName = ? LIMIT 1";
        $authCheckResult = dataQuery($authCheckQuery, [$VolunteerID]);
        if (!$authCheckResult || empty($authCheckResult) || $authCheckResult[0]->LoggedOn == 0) {
            // User is no longer logged in - terminate this EventSource connection
            header('Content-Type: text/event-stream');
            echo "event: logout\n";
            echo "data: {\"message\": \"Session ended - user logged out\"}\n\n";
            flush();
            exit();
        }
    }

    // USER LIST DATA
    $query = "SELECT 
				  UserID, 
				  firstname, 
				  lastname, 
				  shift, 
				  Volunteers.office, 
				  Volunteers.desk, 
				  oncall, 
				  Active1, 
				  Active2, 
				  UserName, 
				  ringing, 
				  ChatOnly, 
				  LoggedOn, 
				  IncomingCallSid, 
				  TraineeID, 
				  Muted, 
				  (
					SELECT 
					  callObject 
					FROM 
					  CallRouting 
					WHERE 
					  CallSid = Volunteers.IncomingCallSid 
					ORDER BY 
					  ID DESC 
					LIMIT 
					  1
				  ) as callObject, 
				  (
					SELECT 
					  callStatus 
					FROM 
					  CallRouting 
					WHERE 
					  CallSid = Volunteers.IncomingCallSid 
					ORDER BY 
					  ID DESC 
					LIMIT 
					  1
				  ) as callStatus, 
				  CASE WHEN LoggedOn = 4 THEN UserName ELSE (
					SELECT 
					  V3.UserName 
					FROM 
					  Volunteers V3 
					WHERE 
					  V3.LoggedOn = 4 
					  AND (
						FIND_IN_SET(
						  Volunteers.UserName, V3.TraineeID
						) > 0 
						OR V3.TraineeID LIKE CONCAT('%', Volunteers.UserName, '%')
					  )
				  ) END as TrainerID,
				  (
					SELECT
					  COUNT(*)
					FROM
					  Volunteers V_Trainee
					WHERE
					  Volunteers.LoggedOn = 4
					  AND FIND_IN_SET(V_Trainee.UserName, Volunteers.TraineeID) > 0
					  AND V_Trainee.oncall = 1
				  ) as traineeOnCall,
				  chatInvite, 
				  groupChatMonitor, 
				  SkypeID as pronouns 
				FROM
				  Volunteers
				WHERE 
				  (
					LoggedOn = 1 
					OR LoggedOn = 2 
					OR LoggedOn = 4 
					OR LoggedOn = 6 
					OR LoggedOn = 7 
					OR LoggedOn = 8 
					OR LoggedOn = 9
				  ) 
				ORDER BY 
				  Shift, 
				  lastname";

    $result = dataQuery($query);
    $Users = [];

    if ($result) {
        foreach ($result as $row) {
            $SingleUser = [
                'idnum' => $row->UserID,
                'FirstName' => $row->firstname,
                'LastName' => $row->lastname,
                'Shift' => $row->shift,
                'Office' => $row->office,
                'Desk' => $row->desk,
                'OnCall' => $row->oncall,
                'Chat1' => $row->Active1,
                'Chat2' => $row->Active2,
                'UserName' => $row->UserName,
                'ringing' => substr($row->ringing, 0, 7),
                'adminRinging' => $row->ringing,
                'ChatOnly' => $row->ChatOnly,
                'AdminLoggedOn' => $row->LoggedOn,
                'IncomingCallSid' => $row->IncomingCallSid,
                'TraineeID' => $row->TraineeID,
                'Muted' => $row->Muted,
                'CallObject' => $row->callObject,
                'CallStatus' => $row->callStatus,
                'TrainerID' => $row->TrainerID,
                'traineeOnCall' => $row->traineeOnCall,
                'groupChatMonitor' => $row->groupChatMonitor,
                'pronouns' => $row->pronouns,
                'isItMe' => (strtolower($row->UserName) == strtolower($VolunteerID))
            ];

            // Log state for trainers/trainees to debug race condition
            if ($row->LoggedOn == 4 || $row->LoggedOn == 6) {
                $roleText = ($row->LoggedOn == 4) ? 'TRAINER' : 'TRAINEE';
                error_log("📊 vccFeed.php: $roleText " . $row->UserName .
                    " - IncomingCallSid: " . ($row->IncomingCallSid ?? 'NULL') .
                    ", OnCall: " . $row->oncall .
                    ", Muted: " . ($row->Muted ?? 'NULL') .
                    ", callObject: " . ($row->callObject ? 'PRESENT' : 'NULL'));
            }

            if ($SingleUser['Muted']) {
                error_log("⚠️ vccFeed.php: User " . $SingleUser['UserName'] . " has Muted=" . $SingleUser['Muted'] . " - Setting CallObject to null (OBSOLETE FIELD)");
                $SingleUser['CallObject'] = null;
            }

            // Process Shift
            $SingleUser['Shift'] = match($SingleUser['Shift']) {
                0 => "Closed",
                1 => "1st",
                2 => "2nd",
                3 => "3rd",
                4 => "4th",
                default => "Closed"
            };

            // Process Desk/CallerType
            if ($SingleUser['Desk'] == 0) {
                $SingleUser['CallerType'] = "Both";
            } elseif ($SingleUser['Desk'] == 1) {
                $SingleUser['CallerType'] = "Chat";
                $SingleUser['ChatOnly'] = 1;
            } elseif ($SingleUser['Desk'] == 2) {
                $SingleUser['CallerType'] = "Call";
                $SingleUser['ChatOnly'] = 0;
            }

            // Process OnCall Status
            if ($SingleUser['ChatOnly'] == 1) {
                $SingleUser['OnCall'] = "Chat Only";
                $SingleUser['Desk'] = "Chat Only";
            } elseif ($SingleUser['OnCall'] == 1) {
                $SingleUser['OnCall'] = "YES";
            } elseif ($SingleUser['AdminLoggedOn'] == 4 && $SingleUser['traineeOnCall'] > 0) {
                // Trainer with trainee(s) on a call - show as busy
                $SingleUser['OnCall'] = "YES";
            } elseif ($SingleUser['ringing'] == null) {
                $SingleUser['OnCall'] = " ";
            } else {
                $SingleUser['OnCall'] = $SingleUser['ringing'];
            }

            // Process Chat Status
            if (($SingleUser['Chat1'] != null && $SingleUser['Chat1'] != "Blocked") &&
                ($SingleUser['Chat2'] != null && $SingleUser['Chat2'] != "Blocked")) {
                $SingleUser['Chat'] = "YES - 2";
            } elseif (($SingleUser['Chat1'] != null && $SingleUser['Chat1'] != "Blocked") ||
                     ($SingleUser['Chat2'] != null && $SingleUser['Chat2'] != "Blocked")) {
                $SingleUser['Chat'] = "YES - 1";
            } else {
                $SingleUser['Chat'] = " ";
            }

            if ($SingleUser['groupChatMonitor'] == 1 && $SingleUser['AdminLoggedOn'] == 8) {
                $SingleUser['Chat'] = "Group Chat";
                $SingleUser['Desk'] = "Group Chat";
            }

            $Users[] = $SingleUser;
        }
    }

    echo "event: userList\n";
    echo "data: " . json_encode($Users) . "\n\n";
    ob_flush();
    flush();

    // CHAT MESSAGE EVENT SUBSECTION
    // Message tracking uses EventSource's Last-Event-ID mechanism (not session):
    // - Browser sends Last-Event-ID header on reconnect with last received message ID
    // - Database delivery flags (volunteerDelivered/callerDelivered) prevent duplicates
    // - The "id:" field in event output below enables this automatic tracking
    $MessageNumber = $_SERVER["HTTP_LAST_EVENT_ID"] ?? 0;

    // Get active chat rooms
    $query = "SELECT Active1, Active2 FROM Volunteers WHERE UserName = ?";
    $result = dataQuery($query, [$VolunteerID]);
    
    $room1 = $result[0]->Active1 ?? "Empty";
    $room2 = $result[0]->Active2 ?? "Empty";

    // Get chat messages (include ChatTime for capturing when chatter ends chat)
    $query = "SELECT Status, Name, Text, MessageNumber, CallerID,
              callerDelivered, volunteerDelivered, ChatTime
              FROM Chat
              WHERE (CallerID = ? OR CallerID = ?)
              AND (volunteerDelivered < 2 OR callerDelivered < 2)
              ORDER BY MessageNumber ASC";

    $result = dataQuery($query, [$room1, $room2]);

    if ($result) {
        foreach ($result as $row) {
            $singleMessage = [
                'id' => $row->MessageNumber,
                'room' => ($row->CallerID == $room1) ? 1 : 2,
                'name' => $row->Name,
                'text' => $row->Text,
                'status' => $row->Status,
                'callerID' => $row->CallerID,
                'callerDelivered' => $row->callerDelivered,
                'volunteerDelivered' => $row->volunteerDelivered
            ];

            echo "event: chatMessage\n";
            echo "id: " . $row->MessageNumber . "\n";
            echo "data: " . json_encode($singleMessage) . "\n\n";
            ob_flush();
            flush();

            if ($singleMessage['status'] != 2) {
                // Include the timestamp when the chat ended (for accurate call log duration)
                $singleMessage['chatEndTime'] = $row->ChatTime;
                echo "event: chatEnd\n";
                echo "id: " . $row->MessageNumber . "\n";
                echo "data: " . json_encode($singleMessage) . "\n\n";
                ob_flush();
                flush();
            }
        }
    }

    // CHAT CALLER TYPING EVENT SUBSECTION
    $query = "SELECT callerID, callerTyping, volunteerTyping 
              FROM chatStatus 
              WHERE callerID = ? OR callerID = ?";
    
    $result = dataQuery($query, [$room1, $room2]);

    if ($result) {
        foreach ($result as $row) {
            $typingMessage = [
                'room' => ($row->callerID == $room1) ? 1 : 2,
                'callerTyping' => $row->callerTyping,
                'volunteerTyping' => $row->volunteerTyping
            ];

            echo "event: typingStatus\n";
            echo "data: " . json_encode($typingMessage) . "\n\n";
            ob_flush();
            flush();
        }
    }

    // LOGGEDON STATUS, ROOM STATUS, ChatInvite AND IM EVENT SUBSECTION
    $query = "SELECT LoggedOn, Active1, Active2, ChatInvite, 
              IMSender, InstantMessage, oneChatOnly 
              FROM Volunteers 
              WHERE UserName = ?";
    
    $result = dataQuery($query, [$VolunteerID]);

    // DEBUG: Log query results
    try {
        $debugLog = date('Y-m-d H:i:s') . " - VolunteerID from session: " . var_export($VolunteerID, true) . "\n";
        $debugLog .= date('Y-m-d H:i:s') . " - Query result count: " . ($result ? count($result) : 'null') . "\n";
        if ($result && count($result) > 0) {
            $debugLog .= date('Y-m-d H:i:s') . " - LoggedOn value: " . var_export($result[0]->LoggedOn, true) . "\n";
        }
        file_put_contents('debug_admin_logoff.txt', $debugLog, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) { /* ignore */ }

    if ($result && count($result) > 0) {
        $volunteer = $result[0];
        $LoggedOn = $volunteer->LoggedOn;
        $ChatID1 = $volunteer->Active1;
        $ChatID2 = $volunteer->Active2;
        $ChatInvite = $volunteer->ChatInvite;
        $InstantMessage = $volunteer->InstantMessage;
        $oneChatOnly = $volunteer->oneChatOnly;

        echo "event: logoff\n";
        echo "data: " . $LoggedOn . "\n\n";
        
        // DEBUG: Log what we're sending
        try {
            $debugLog = date('Y-m-d H:i:s') . " - Sending logoff event with data: " . $LoggedOn . "\n";
            file_put_contents('debug_admin_logoff.txt', $debugLog, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) { /* ignore */ }
        
        ob_flush();
        flush();

        // ChatInvite Event
        $Invite = ['room' => 0];
        
        if ($ChatInvite) {
            $queryA = "SELECT callerBrowser, callerBrowserVersion, 
                      callerComputerType, callerOS 
                      FROM chatStatus 
                      WHERE callerID = ?";
            
            $resultA = dataQuery($queryA, [$ChatInvite]);

            if ($resultA && count($resultA) > 0) {
                $callerInfo = $resultA[0];
                $Invite = [
                    'browser' => $callerInfo->callerBrowser,
                    'browserVersion' => $callerInfo->callerBrowserVersion,
                    'ComputerType' => $callerInfo->callerComputerType,
                    'callerOS' => $callerInfo->callerOS,
                    'roomid' => $ChatInvite,
                    'room' => !$ChatID1 ? 1 : (!$ChatID2 ? 2 : 0)
                ];
            }
        }

        $Invite['groupChatTransferMessage'] = $InstantMessage;

        echo "event: chatInvite\n";
        echo "data: " . json_encode($Invite) . "\n\n";
        ob_flush();
        flush();

        // Handle one chat only logic
        if ($oneChatOnly == 1) {
            if (!$ChatID2 && $ChatID1 != "Blocked") {
                $ChatID2 = 'Blocked';
            } else if (!$ChatID1 && $ChatID2 != "Blocked") {
                $ChatID1 = 'Blocked';
            } else if ($ChatID1 == "Blocked" && !$ChatID2) {
                $ChatID1 = 'Open';
                $ChatID2 = 'Blocked';
            }
        }

        // Room status events
        foreach ([1 => $ChatID1, 2 => $ChatID2] as $i => $Room) {
            $event = match(true) {
                $Room == "Blocked" => "chatBlocked",
                $Room == null => "chatOpen",
                default => "chatActive"
            };
            
            echo "event: $event\n";
            echo "data: $i\n\n";
            ob_flush();
            flush();
        }
    } else {
        // DEBUG: Log when no result found
        try {
            $debugLog = date('Y-m-d H:i:s') . " - No result found for VolunteerID: " . var_export($VolunteerID, true) . "\n";
            $debugLog .= date('Y-m-d H:i:s') . " - Sending default logoff event with data: 0\n";
            file_put_contents('debug_admin_logoff.txt', $debugLog, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) { /* ignore */ }
        
        echo "event: logoff\n";
        echo "data: 0\n\n";
        ob_flush();
        flush();
    }

    // NewIM Section
    $imQuery = $AdminUser 
        ? "SELECT imTo, imFrom, Text, MessageNumber, ToDelivered, FromDelivered 
           FROM VolunteerIM 
           WHERE (imTo = ? OR imFrom = ? OR imTo = 'Admin' 
                 OR imFrom = 'Admin' OR imTo = 'All') 
           AND (ToDelivered < 2 OR FromDelivered < 2) 
           ORDER BY MessageNumber ASC"
        : "SELECT imTo, imFrom, Text, MessageNumber, ToDelivered, FromDelivered 
           FROM VolunteerIM 
           WHERE ((imTo = ? OR imFrom = ? OR imTo = 'All') 
                 OR (imTo = 'Admin' AND imFrom = ?) 
                 OR (imFrom = 'Admin' AND imTo = ?))
           AND (ToDelivered < 2 OR FromDelivered < 2) 
           ORDER BY MessageNumber ASC";

    $params = $AdminUser 
        ? [$VolunteerID, $VolunteerID]
        : [$VolunteerID, $VolunteerID, $VolunteerID, $VolunteerID];

    $result = dataQuery($imQuery, $params);

    if ($result) {
        foreach ($result as $row) {
            $singleMessage = [
                'id' => $row->MessageNumber,
                'to' => $row->imTo,
                'from' => $row->imFrom,
                'text' => $row->Text,
                'toDelivered' => $row->ToDelivered,
                'fromDelivered' => $row->FromDelivered
            ];

            // Message tracking handled by database delivery flags (toDelivered/fromDelivered)
            // No session tracking needed - duplicate prevention is database-driven

            echo "event: IM\n";
            echo "data: " . json_encode($singleMessage) . "\n\n";
            ob_flush();
            flush();
        }
    }

    // SCREEN SHARING SIGNALS REMOVED - Now handled by dedicated signaling server
    
    ob_flush();
    flush();
    
    // Sleep with connection status checks (faster disconnect detection)
    // Check every 0.5 seconds instead of sleeping for 2 seconds straight
    for ($sleepLoop = 0; $sleepLoop < 4; $sleepLoop++) {
        usleep(500000); // 0.5 seconds
        if (connection_aborted() || connection_status() != CONNECTION_NORMAL) {
            exit();
        }
    }
    $count++;
}

// No need to close connection as PDO handles this automatically
?>
