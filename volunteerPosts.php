<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours
require_once('../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

$PostType = $_REQUEST['postType'] ?? null;
$Action = $_REQUEST['action'] ?? null;
$preText = $_REQUEST['text'] ?? null;
$Text = $preText ?? null;
$VolunteerID = $_SESSION['UserID'] ?? null;

// ============================================
// CAPTURE ALL SESSION DATA BEFORE POTENTIALLY RELEASING LOCK
// This prevents session blocking for cases that don't need writes
// ============================================
$sessionId = session_id();  // Capture for exitProgram before any potential close

// Capture timing-related session vars (needed by endChat, chatterEndedChat, endCall, exitProgram)
$capturedSession = [
    'chat1Start' => $_SESSION['chat1Start'] ?? null,
    'chat1End' => $_SESSION['chat1End'] ?? null,
    'chat1ID' => $_SESSION['chat1ID'] ?? null,
    'chat2Start' => $_SESSION['chat2Start'] ?? null,
    'chat2End' => $_SESSION['chat2End'] ?? null,
    'chat2ID' => $_SESSION['chat2ID'] ?? null,
    'callStart' => $_SESSION['callStart'] ?? null,
    'callEnd' => $_SESSION['callEnd'] ?? null,
    'volunteer_session_id' => $_SESSION['volunteer_session_id'] ?? null,
];

// ============================================
// CONDITIONAL SESSION LOCK RELEASE
// Release lock early for cases that don't need to write to session
// This prevents blocking other AJAX requests from the same user
// ============================================
$casesNeedingSessionWrite = [
    'chatInvite',      // Accept action writes chat start times
    'endChat',         // Writes chat end times
    'chatterEndedChat', // Writes chat end times
    'startCall',       // Writes call start time
    'endCall',         // Writes call/chat end times, unsets vars
    'exitProgram',     // Clears session auth on intentional exit
    'clearSession',    // Clears session auth on cascade logout
];

if (!in_array($PostType, $casesNeedingSessionWrite)) {
    session_write_close();  // Release lock early for read-only cases
}

function prepare_text_for_textarea($string) {
    // Replace problematic sequences (e.g., misencoded apostrophes)
    $replacements = [
        'â' => "'", // Fix apostrophe
        'â' => '-', // Fix dash
        'â' => '"', // Fix left double quote
        'â' => '"', // Fix right double quote
    ];

    // Fix encoding issues
    $string = strtr($string, $replacements);

    // Replace <br> tags with \n
    $string = preg_replace('/<br\s*\/?>/i', "\n", $string);

    // Ensure all remaining line breaks are consistent
    $string = str_replace(["\r\n", "\r"], "\n", $string);

    return $string;
}

// GET BASIC INFORMATION NEEDED TO PROCESS ANY TYPE OF ACTION
$query = "SELECT Active1, Active2, ChatInvite FROM Volunteers WHERE UserName = :volunteerID";
$params = ['volunteerID' => $VolunteerID];
$results = dataQuery($query, $params);

if (!$results && $PostType != "messageStatusUpdate" && $PostType != "typingStatusUpdate") {
    // Uncomment to enforce strict error handling for missing volunteers:
    // die("ERROR - No Such Volunteer: " . $VolunteerID);
}

if (!empty($results)) {
    foreach ($results as $result_row) {
        $Active1 = $result_row->Active1 ?? null;
        $Active2 = $result_row->Active2 ?? null;
        $ChatInvite = $result_row->ChatInvite ?? null;
    }
}

	switch($PostType) {
	
		case 'chatInvite':
      if($Action == "Accept") {
          if (!$Active1) {
              $query2 = "UPDATE Volunteers
                         SET Active1 = ?
                         WHERE UserName = ?";
              $result2 = dataQuery($query2, [$ChatInvite, $VolunteerID]);

              // Clear any stale end time from previous chat before setting new start time
              // This prevents the bug where old chat1End values persist and cause negative durations
              if (isset($_SESSION['chat1End'])) {
                  unset($_SESSION['chat1End']);
              }
              $_SESSION['chat1Start'] = date('Y-m-d H:i:s');
              $_SESSION['chat1ID'] = $ChatInvite;

          } elseif (!$Active2) {
              $query2 = "UPDATE Volunteers
                         SET Active2 = ?
                         WHERE UserName = ?";
              $result2 = dataQuery($query2, [$ChatInvite, $VolunteerID]);

              // Clear any stale end time from previous chat before setting new start time
              if (isset($_SESSION['chat2End'])) {
                  unset($_SESSION['chat2End']);
              }
              $_SESSION['chat2Start'] = date('Y-m-d H:i:s');
              $_SESSION['chat2ID'] = $ChatInvite;

          } else {
              die("ERROR - Neither Room Open");
          }
      
          // Clear chat invite for all volunteers who received it
          $query3 = "UPDATE Volunteers 
                     SET ChatInvite = NULL 
                     WHERE ChatInvite = ?";
          $result3 = dataQuery($query3, [$ChatInvite]);
      
          // Insert welcome message
          $query4 = "INSERT INTO Chat
                     VALUES (now(), ?, 2, 'Volunteer',
                            (SELECT text FROM SystemMessages WHERE Message = 'Welcome'),
                            null, 0, 0)";
          $result4 = dataQuery($query4, [$ChatInvite]);

          // Refresh Redis cache for polling clients
          try {
              require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
              $publisher = new VCCFeedPublisher();
              $publisher->refreshUserListCache();
          } catch (Exception $e) {
              error_log("VCCFeedPublisher error on chatInvite Accept: " . $e->getMessage());
          }

          break;
      } elseif ($Action == "Reject") {  
  
        // Record Rejected ChatInvite and ChatOnly Status Before Deleting
        $query = "SELECT ChatInvite, ChatOnly 
                 FROM Volunteers 
                 WHERE UserName = ?";
        $result = dataQuery($query, [$VolunteerID]);
    
        if (!$result || count($result) == 0) {
            die("ERROR - No Such Volunteer: " . $VolunteerID);
        }
    
        $ChatInvite = $result[0]->ChatInvite;
        $ChatOnlyVolunteer = $result[0]->ChatOnly;
    
        // Delete ChatInvite
        $query2 = "UPDATE Volunteers 
                  SET ChatInvite = NULL, InstantMessage = NULL 
                  WHERE UserName = ?";
        $result2 = dataQuery($query2, [$VolunteerID]);
    
        // Determine if Hotline is Open
        $query2A = "SELECT dayofweek 
                    FROM Hours 
                    WHERE (dayofweek = DATE_FORMAT(curdate(),'%w') + 1) 
                    AND (start < curtime() AND end > curtime())";
        $result2A = dataQuery($query2A);
        $open = $result2A[0]->dayofweek ?? null;
    
        // If Volunteer is Last ChatOnly, Send Invitations to All Other Eligible Volunteers
        if ($ChatOnlyVolunteer == 1) {
            $query10 = "SELECT Active1, Active2, ChatInvite 
                       FROM Volunteers 
                       WHERE ChatOnly = 1 
                       AND (ChatInvite = ? OR Active1 = ? OR Active2 = ?)";
            $result10 = dataQuery($query10, [$ChatInvite, $ChatInvite, $ChatInvite]);
    
            if (!$result10 || count($result10) == 0) {
                // Common WHERE clauses
                $findLoggedon = "WHERE ChatOnly != 1 
                                AND (Hours.dayofweek = DATE_FORMAT(curdate(),'%w') + 1) 
                                AND (end > curtime()) 
                                AND loggedon = 1";
                
                $findEligible = $findLoggedon . " AND OnCall = 0 
                                AND (Active1 is null OR Active2 is null) 
                                AND ChatInvite is null";
                
                $findChatting = $findLoggedon . " AND OnCall = 0 
                               AND ((Active1 is not null AND Active1 <> 'Blocked') 
                               OR (Active2 is not null and Active2 <> 'Blocked') 
                               OR ChatInvite is not null)";
                
                $findAvailableChatting = $findLoggedon . " AND OnCall = 0 
                                        AND ((Active1 is not null AND Active1 <> 'Blocked' AND Active2 is null) 
                                        OR (Active2 is not null AND Active2 <> 'Blocked' AND Active1 is null)) 
                                        AND ChatInvite is null";
    
                // Determine if Hotline is Open (reusing previous result)
                
                // Chat Invite Counts Routine
                $query2 = "SELECT count(username) as count 
                          FROM Volunteers 
                          JOIN Hours ON (volunteers.shift = Hours.shift) " 
                          . $findLoggedon;
                $result2 = dataQuery($query2);
                $loggedon = $result2[0]->count ?? 0;
    
                $potential = match(true) {
                    $loggedon < 2 => 0,
                    $loggedon < 4 => 1,
                    default => 2
                };
    
                $query3 = "SELECT count(username) as count 
                          FROM Volunteers 
                          JOIN Hours ON (volunteers.shift = Hours.shift) " 
                          . $findEligible;
                $result3 = dataQuery($query3);
                $eligible = $result3[0]->count ?? 0;
    
                $query4 = "SELECT count(username) as count 
                          FROM Volunteers 
                          JOIN Hours ON (volunteers.shift = Hours.shift) " 
                          . $findChatting;
                $result4 = dataQuery($query4);
                $chatting = $result4[0]->count ?? 0;
    
                $query5 = "SELECT count(username) as count 
                          FROM Volunteers 
                          JOIN Hours ON (volunteers.shift = Hours.shift) " 
                          . $findAvailableChatting;
                $result5 = dataQuery($query5);
                $availableChatting = $result5[0]->count ?? 0;
    
                // Determine status and take appropriate action
                $status = match(true) {
                    !$open => "closed",
                    $loggedon < 2 => "busy",
                    $chatting < $potential && $eligible > 0 => "allEligible",
                    $chatting == $potential && $availableChatting > 0 => "allAvailableChatting",
                    default => "busy"
                };
    
                if ($status == "allEligible") {
                    $query6 = "UPDATE Volunteers 
                              JOIN Hours ON (volunteers.shift = Hours.shift) 
                              SET volunteers.ChatInvite = ? " 
                              . $findEligible;
                    $result6 = dataQuery($query6, [$ChatInvite]);
                } elseif ($status == "allAvailableChatting") {
                    $query7 = "UPDATE Volunteers 
                              JOIN Hours ON (volunteers.shift = Hours.shift) 
                              SET volunteers.ChatInvite = ? " 
                              . $findAvailableChatting;
                    $result7 = dataQuery($query7, [$ChatInvite]);
                }
            }
          }
  


				// ---------------- END NEW INSERT FOR Chat Only

      
      // Check for ChatInvite At Other Volunteers
          $query3 = "SELECT Active1, Active2, ChatInvite 
                     FROM Volunteers 
                     WHERE ChatInvite = ? 
                     OR Active1 = ? 
                     OR Active2 = ?";
          $result3 = dataQuery($query3, [$ChatInvite, $ChatInvite, $ChatInvite]);
      
          // If No Other Volunteers Connected to the Chat, Send Caller a Busy Message
          if (!$result3 || count($result3) == 0) {
              $query4 = "INSERT INTO Chat 
                         VALUES (DEFAULT, ?, 7, '', 
                         (SELECT text FROM SystemMessages WHERE Message = 'Reject'), 
                         null, null, null)";
              $result4 = dataQuery($query4, [$ChatInvite]);
          }
      } else {
          echo "unknown";
      }
      break;		
		
  case 'postMessage':
      if ($Action == 1) {
          $callerID = $Active1;
      } elseif ($Action == 2) {
          $callerID = $Active2;
      }
      
      if (!$callerID) {
          die("You cannot post to an inactive chatroom.");
      }
      
      $query = "INSERT INTO Chat 
                VALUES (now(), ?, '2', 'Volunteer', ?, null, 0, 0)";
      $result = dataQuery($query, [$callerID, $Text]);
      
      if($result === false) {
          die("Volunteer Post Message Routine - Could not insert message into database");
      }
      break;
  
  case 'messageStatusUpdate':
      $messageNumber = $Action;
      $deliveredValue = null;
      $column = null;
      
      // Map status updates to column and value
      switch($Text) {
          case 'Caller-delivered':
              $column = 'callerDelivered';
              $deliveredValue = 1;
              break;
          case 'Caller-confirmed':
              $column = 'callerDelivered';
              $deliveredValue = 2;
              break;
          case 'Volunteer-delivered':
              $column = 'volunteerDelivered';
              $deliveredValue = 1;
              break;
          case 'Volunteer-confirmed':
              $column = 'volunteerDelivered';
              $deliveredValue = 2;
              break;
          default:
              break;
      }
      
      if ($column && $deliveredValue !== null) {
          $queryUpdate = "UPDATE Chat 
                         SET $column = ? 
                         WHERE MessageNumber = ?";
          $result = dataQuery($queryUpdate, [$deliveredValue, $messageNumber]);
          
          if($result === false) {
              die("Volunteer Message Status Update Routine - Could not update the database");
          }
      }
      break;
  
  case 'typingStatusUpdate':
      $typingValue = null;
      
      switch($Text) {
          case 'Caller-typing':
              $typingValue = 1;
              break;
          case 'Caller-not':
              $typingValue = 0;
              break;
      }
      
      if ($typingValue !== null) {
          $queryUpdate = "UPDATE chatStatus 
                         SET callerTyping = ? 
                         WHERE callerID = ?";
          $result = dataQuery($queryUpdate, [$typingValue, $Action]);
          
          if($result === false) {
              die("Volunteer Message Status Update Routine - Could not update the database");
          }
      }
      break;
  
  case 'cannedChatText':
      $query = "SELECT menu, text 
               FROM ChatText 
               ORDER BY OrderNumber";
      $result = dataQuery($query);
      
      $cannedText = [];
      if ($result) {
          foreach($result as $index => $row) {
              $singleMessage = [
                  'menuItem' => $row->menu,
                  'text' => prepare_text_for_textarea($row->text)
              ];
              $cannedText[$index + 1] = $singleMessage;
          }
      }
      
      echo json_encode($cannedText);
      break;
        
    case 'infoCenter':
        echo file_get_contents("./InfoCenter/" . $Action . ".html", "r");
        break;

    case 'infoCenterUpload':
        $filename = $Action;
        file_put_contents("InfoCenter/" . $filename, $Text);
        echo $Text;
        break;

    case 'infoCenterDeleteItem':
        $filename = "InfoCenter/" . $Action . ".html";
        echo $filename;
        unlink($filename);
        break;

    case 'endChat':
        if ($Action == 1) {
            $callerID = $Active1;
            $room = "Active1";
            // Save end time for chat1 when volunteer clicks END CHAT
            // Use captured session data for isset check, but write to live $_SESSION
            if (!empty($capturedSession['chat1Start'])) {
                $_SESSION['chat1End'] = date('Y-m-d H:i:s');
            }
        } elseif ($Action == 2) {
            $callerID = $Active2;
            $room = "Active2";
            // Save end time for chat2 when volunteer clicks END CHAT
            // Use captured session data for isset check, but write to live $_SESSION
            if (!empty($capturedSession['chat2Start'])) {
                $_SESSION['chat2End'] = date('Y-m-d H:i:s');
            }
        }
        $query = "INSERT INTO Chat VALUES (NOW(), :callerID, '5', 'Volunteer',
                  (SELECT Text FROM SystemMessages WHERE Status = 5), NULL, 0, 0)";
        $params = ['callerID' => $callerID];
        $result = dataQuery($query, $params);

        if (!$result) {
            die("Volunteer EndChat Routine - Could not query the database.");
        }

        $query2 = "UPDATE Volunteers SET Ringing = 'Logging' WHERE UserName = :volunteerID";
        $params2 = ['volunteerID' => $VolunteerID];
        dataQuery($query2, $params2);

        // Refresh Redis cache for polling clients
        try {
            require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
            $publisher = new VCCFeedPublisher();
            $publisher->refreshUserListCache();
        } catch (Exception $e) {
            error_log("VCCFeedPublisher error on endChat: " . $e->getMessage());
        }
        break;

    case 'clearChat':
        $room = $Action == 1 ? "Active1" : "Active2";
        $query = "UPDATE Volunteers SET " . $room . " = NULL WHERE UserName = :volunteerID";
        $params = ['volunteerID' => $VolunteerID];
        dataQuery($query, $params);

        // Refresh Redis cache for polling clients
        try {
            require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
            $publisher = new VCCFeedPublisher();
            $publisher->refreshUserListCache();
        } catch (Exception $e) {
            error_log("VCCFeedPublisher error on clearChat: " . $e->getMessage());
        }
        break;

    case 'chatterEndedChat':
        // Called when the chatter ends the chat (closes browser, disconnects, etc.)
        // This captures the actual end time from the Chat table, not form submission time
        $room = $_REQUEST['room'] ?? null;
        $endTime = $_REQUEST['endTime'] ?? null;

        if ($room && $endTime) {
            // Use captured session data for isset checks, but write to live $_SESSION
            if ($room == 1 && !empty($capturedSession['chat1Start'])) {
                $_SESSION['chat1End'] = $endTime;
            } elseif ($room == 2 && !empty($capturedSession['chat2Start'])) {
                $_SESSION['chat2End'] = $endTime;
            }
        }
        echo "OK";
        break;

    case 'postIM':
        $query2 = $Action === "admin" 
            ? "INSERT INTO VolunteerIM VALUES (NOW(), 'Admin', :volunteerID, :text, NULL, 0, 0)"
            : "INSERT INTO VolunteerIM VALUES (NOW(), :action, :volunteerID, :text, NULL, 0, 0)";
        $params2 = [
            'action' => $Action,
            'volunteerID' => $VolunteerID,
            'text' => $Text
        ];
        dataQuery($query2, $params2);
        break;

    case 'IMReceived':
        $column = $Text === 'to' ? 'toDelivered' : ($Text === 'from' ? 'fromDelivered' : null);
        if ($column) {
            $query = "UPDATE VolunteerIM SET " . $column . " = '2' WHERE MessageNumber = :action";
            $params = ['action' => $Action];
            dataQuery($query, $params);
        }
        break;

    case 'oneChatOnly':
        $Action = ($Action === null || $Action === "null") ? 0 : $Action;
        $query = "UPDATE Volunteers SET oneChatOnly = :action WHERE UserName = :volunteerID";
        $params = ['action' => $Action, 'volunteerID' => $VolunteerID];
        $result = dataQuery($query, $params);

        if ($result === false) {
            die("OneChatOnly - The Query failed to execute: ".$result);
        }
        echo "OK";
        break;
        
    case 'callerHistory':
        $callHistory = [];
        $singleCall = [];
        $Hotline = [];
        $subHotline = "";

        $query = "SELECT HotlineName, CallCity, CallState, CallZip FROM Volunteers WHERE UserName = :volunteerID";
        $params = ['volunteerID' => $VolunteerID];
        $results = dataQuery($query, $params);

        foreach ($results as $result_row) {
            $subHotline = $result_row->HotlineName;
            $City = $result_row->CallCity;
            $State = $result_row->CallState;
            $Zip = $result_row->CallZip;
        }

        $Hotline['id'] = $subHotline;
        switch ($Hotline['id']) {
            case 'GLNH':
                $Hotline['shortName'] = "LGBT National Hotline"; 
                $Hotline['longName'] = "LGBTQ National Hotline";
                break;
            case 'GLSB-NY':
                $Hotline['shortName'] = "LGBT Switchboard NY"; 
                $Hotline['longName'] = "LGBTQ Switchboard of New York";
                break;
            case 'Youth':
                $Hotline['shortName'] = "Youth Talkline"; 
                $Hotline['longName'] = "LGBTQ National Youth Talkline";
                break;
            case 'SENIOR':
                $Hotline['shortName'] = "LGBT Senior Hotline"; 
                $Hotline['longName'] = "LGBTQ National Senior Hotline";
                break;
            case 'OUT':
                $Hotline['shortName'] = "LGBT Coming Out Hotline"; 
                $Hotline['longName'] = "LGBTQ Nat'l Coming Out Support Hotline";
                break;
        }

        $Hotline['city'] = $City;
        $Hotline['state'] = $State;
        $Hotline['zip'] = $Zip;
        $callHistory[0] = $Hotline;

        $query = "SELECT 
                CONCAT(DATE_FORMAT(Date, '%m'), '/', DATE_FORMAT(Date, '%d'), '/', DATE_FORMAT(Date, '%y')) AS 'Call Date',
                TIME_FORMAT(Time, '%r') AS 'Call Time',
                Hotline,
                Length,
                Category,
                (SELECT Gender FROM CallLog WHERE CallLog.CallSid = CallerHistory.CALLSID) AS Gender,
                (SELECT Age FROM CallLog WHERE CallLog.CallSid = CallerHistory.CALLSID) AS Age,
                (SELECT CallLogNotes FROM CallLog WHERE CallLog.CallSid = CallerHistory.CALLSID) AS CallLogNotes
            FROM CallerHistory
            WHERE CallerID = :action AND Date > DATE_SUB(NOW(), INTERVAL 3 MONTH)
              AND (Category = 'Conversation' OR Category = 'Hang Up on Volunteer')
            ORDER BY Date DESC, Time DESC, Hotline ASC";
        $params = ['action' => $Action];
        $results = dataQuery($query, $params);

        if (!$results) {
            $callHistory[1] = "No Call History";
        } else {
            $Count = 1;
            foreach ($results as $result_row) {
                $singleCall['date'] = $result_row->{"Call Date"};
                $singleCall['time'] = $result_row->{"Call Time"};
                $singleCall['hotline'] = $result_row->Hotline;
                $singleCall['length'] = $result_row->Length;
                $singleCall['category'] = $result_row->Category;
                $singleCall['gender'] = $result_row->Gender ?: " ";
                $singleCall['age'] = $result_row->Age ?: " ";
                $singleCall['callLogNotes'] = $result_row->CallLogNotes ?: " ";

                if ($singleCall['gender'] == "All Othe" || $singleCall['gender'] == "All Others" || $singleCall['gender'] == "All%20Othe") {
                    $singleCall['gender'] = "Non-Binary";
                }
                if ($singleCall['category'] != "Conversation") {
                    $singleCall['category'] = "-";
                }

                $callHistory[$Count] = $singleCall;
                $Count++;
            }
        }
        echo json_encode($callHistory);
        break;

    case 'startCall':
        $_SESSION['callStart'] = date('Y-m-d H:i:s');

        // Set OnCall=1 for the volunteer starting the call
        $query = "UPDATE Volunteers SET OnCall = 1 WHERE UserName = ?";
        dataQuery($query, [$VolunteerID]);

        // Handle training session participants - set OnCall for all
        // (fixes bug: TraineeID = :volunteerID doesn't work for comma-separated lists)
        $trainingCheck = "SELECT LoggedOn, TraineeID FROM volunteers WHERE UserName = ?";
        $trainingResult = dataQuery($trainingCheck, [$VolunteerID]);

        if ($trainingResult && count($trainingResult) > 0) {
            $loggedOnStatus = $trainingResult[0]->LoggedOn;
            $traineeID = $trainingResult[0]->TraineeID;

            if ($loggedOnStatus == 4 && !empty($traineeID)) {
                // Trainer started call - mark all trainees as OnCall
                $traineeIds = array_map('trim', explode(',', $traineeID));
                if (count($traineeIds) > 0) {
                    $placeholders = implode(',', array_fill(0, count($traineeIds), '?'));
                    dataQuery("UPDATE Volunteers SET OnCall = 1 WHERE UserName IN ($placeholders)", $traineeIds);
                }
            } elseif ($loggedOnStatus == 6) {
                // Trainee started call - find and mark trainer as OnCall
                $findTrainer = "SELECT UserName FROM volunteers
                               WHERE FIND_IN_SET(?, TraineeID) > 0 AND LoggedOn = 4";
                $trainerResult = dataQuery($findTrainer, [$VolunteerID]);
                if ($trainerResult && count($trainerResult) > 0) {
                    $trainerId = $trainerResult[0]->UserName;
                    dataQuery("UPDATE Volunteers SET OnCall = 1 WHERE UserName = ?", [$trainerId]);
                }
            }
        }

        // Refresh Redis cache for polling clients
        try {
            require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
            $publisher = new VCCFeedPublisher();
            $publisher->refreshUserListCache();
        } catch (Exception $e) {
            error_log("VCCFeedPublisher error on startCall: " . $e->getMessage());
        }
        break;

    case 'endCall':
        // First, reset the volunteer who ended the call
        $query = "UPDATE Volunteers SET OnCall = 0, Ringing = NULL, HotlineName = NULL, CallCity = NULL,
                  CallState = NULL, CallZip = NULL, IncomingCallSid = NULL, Active1 = NULL, Active2 = NULL
                  WHERE UserName = ?";
        dataQuery($query, [$VolunteerID]);

        // Handle training session participants - clear OnCall for all
        // (fixes bug: TraineeID = :volunteerID doesn't work for comma-separated lists)
        $trainingCheck = "SELECT LoggedOn, TraineeID FROM volunteers WHERE UserName = ?";
        $trainingResult = dataQuery($trainingCheck, [$VolunteerID]);

        if ($trainingResult && count($trainingResult) > 0) {
            $loggedOnStatus = $trainingResult[0]->LoggedOn;
            $traineeID = $trainingResult[0]->TraineeID;

            if ($loggedOnStatus == 4 && !empty($traineeID)) {
                // Trainer ended call - clear OnCall for all trainees
                $traineeIds = array_map('trim', explode(',', $traineeID));
                if (count($traineeIds) > 0) {
                    $placeholders = implode(',', array_fill(0, count($traineeIds), '?'));
                    dataQuery("UPDATE Volunteers SET OnCall = 0 WHERE UserName IN ($placeholders)", $traineeIds);
                }
            } elseif ($loggedOnStatus == 6) {
                // Trainee ended call - find and clear trainer's OnCall, plus other trainees
                $findTrainer = "SELECT UserName, TraineeID FROM volunteers
                               WHERE FIND_IN_SET(?, TraineeID) > 0 AND LoggedOn = 4";
                $trainerResult = dataQuery($findTrainer, [$VolunteerID]);
                if ($trainerResult && count($trainerResult) > 0) {
                    $trainerId = $trainerResult[0]->UserName;
                    $allTrainees = $trainerResult[0]->TraineeID;

                    // Clear trainer's OnCall
                    dataQuery("UPDATE Volunteers SET OnCall = 0 WHERE UserName = ?", [$trainerId]);

                    // Clear all other trainees in this session
                    $traineeIds = array_map('trim', explode(',', $allTrainees));
                    $otherTrainees = array_filter($traineeIds, fn($t) => $t !== $VolunteerID);
                    if (count($otherTrainees) > 0) {
                        $placeholders = implode(',', array_fill(0, count($otherTrainees), '?'));
                        dataQuery("UPDATE Volunteers SET OnCall = 0 WHERE UserName IN ($placeholders)",
                                 array_values($otherTrainees));
                    }
                }
            }
        }

        // Refresh Redis cache for polling clients
        try {
            require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
            $publisher = new VCCFeedPublisher();
            $publisher->refreshUserListCache();
        } catch (Exception $e) {
            error_log("VCCFeedPublisher error on endCall: " . $e->getMessage());
        }

        // Save end times to session before clearing (for accurate call log timestamps)
        // Use captured session data for isset checks, but write to live $_SESSION
        if (!empty($capturedSession['chat1Start'])) {
            $_SESSION['chat1End'] = date('Y-m-d H:i:s');
        }
        if (!empty($capturedSession['chat2Start'])) {
            $_SESSION['chat2End'] = date('Y-m-d H:i:s');
        }
        if (!empty($capturedSession['callStart'])) {
            $_SESSION['callEnd'] = date('Y-m-d H:i:s');
        }

        // Clear session variables for chats and calls
        unset($_SESSION['chat1Start']);
        unset($_SESSION['chat1ID']);
        unset($_SESSION['chat2Start']);
        unset($_SESSION['chat2ID']);
        unset($_SESSION['callStart']);
        break;

    case 'exitCalendar':
        // Exit from Calendar Only mode - only reset LoggedOn if it's 10
        if ($VolunteerID) {
            $query = "UPDATE volunteers SET LoggedOn = 0 WHERE UserName = ? AND LoggedOn = 10";
            dataQuery($query, [$VolunteerID]);
        }
        echo json_encode(['status' => 'ok']);
        break;

    case 'exitProgram':
        if(!$VolunteerID) {
            $VolunteerID = $Action;
        }

        // Check if this is an intentional exit (Exit button) vs sendBeacon (refresh/close)
        // sendBeacon fires on EVERY page unload including refresh
        // We only do cleanup for intentional exits - refresh is handled by session persistence
        $isIntentionalExit = isset($_REQUEST['intentional']) && $_REQUEST['intentional'] === 'true';

        if (!$isIntentionalExit) {
            // sendBeacon from page refresh or browser close
            // Don't change LoggedOn status - session will persist for refresh
            // For browser close, user will appear online until session expires (8 hours)
            // or until heartbeat/activity check marks them offline
            error_log("exitProgram: sendBeacon (not intentional) by $VolunteerID - no database changes");
            echo "OK";
            break;
        }

        // === INTENTIONAL EXIT (Exit button clicked) ===
        error_log("exitProgram: Intentional exit by $VolunteerID - performing full cleanup");

        // Use pre-captured $sessionId from top of script (captured before any session_write_close)
        // $sessionId is already set at line 22
        $sessionFile = session_save_path() . '/sess_' . $sessionId;
        $customJsonFile = dirname(__FILE__) . '/../private_html/session_' . $sessionId . '.json';

		// Get user info before deletion for media server notification
		$query = "SELECT LoggedOn, TraineeID FROM volunteers WHERE UserName = ?";
		$userInfo = dataQuery($query, [$VolunteerID]);

		$isTrainer = false;
		$isTrainee = false;
		$roomName = null;

		if (!empty($userInfo)) {
			$loggedOnStatus = $userInfo[0]->LoggedOn;
			$traineeID = $userInfo[0]->TraineeID;

			if ($loggedOnStatus == 4) { // Trainer
				$isTrainer = true;
				$roomName = $VolunteerID; // Trainer ID is room name
			} elseif ($loggedOnStatus == 6) { // Trainee
				$isTrainee = true;
				// Find trainer for this trainee
				$trainerQuery = "SELECT UserName FROM volunteers WHERE FIND_IN_SET(?, TraineeID) > 0";
				$trainerResult = dataQuery($trainerQuery, [$VolunteerID]);
				if (!empty($trainerResult)) {
					$roomName = $trainerResult[0]->UserName;
				}
			}
		}

        // Clean up SessionBridge session if exists
        // Use pre-captured session data from $capturedSession array
        if (!empty($capturedSession['volunteer_session_id'])) {
            try {
                require_once 'SessionBridge.php';
                $bridge = new SessionBridge();
                $bridge->endSession($capturedSession['volunteer_session_id'], 'user_exit');
            } catch (Exception $e) {
                // Don't fail exit if SessionBridge cleanup fails
                error_log("SessionBridge cleanup failed during voluntary exit: " . $e->getMessage());
            }
        }

        // Log volunteer exit
        $queryLog = "INSERT INTO volunteerlog VALUES (null, ?, now(), 0, 0)";
        $result = dataQuery($queryLog, [$VolunteerID]);

        // Update volunteer status
        $query = "UPDATE volunteers SET
            LoggedOn = 0,
            Active1 = NULL,
            Active2 = NULL,
            OnCall = 0,
            ChatInvite = NULL,
            Ringing = NULL,
            TraineeID = NULL,
            Muted = 0,
            IncomingCallSid = NULL
            WHERE UserName = ?";
        $result = dataQuery($query, [$VolunteerID]);

        // **PUBLISH LOGOUT EVENT AND REFRESH CACHE FOR POLLING CLIENTS**
        try {
            require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
            $publisher = new VCCFeedPublisher();
            $publisher->publishUserListChange('logout', [
                'username' => $VolunteerID,
                'timestamp' => time()
            ]);
            $publisher->refreshUserListCache();
        } catch (Exception $e) {
            error_log("VCCFeedPublisher error on logout: " . $e->getMessage());
        }

        // Remove from CallControl table
        $deleteControl = "DELETE FROM CallControl WHERE user_id = ?";
        dataQuery($deleteControl, [$VolunteerID]);

        // Clean up training_session_control table
        if ($isTrainer) {
            // Trainer exiting: Delete their control record entirely
            $deleteTrainingControl = "DELETE FROM training_session_control WHERE trainer_id = ?";
            dataQuery($deleteTrainingControl, [$VolunteerID]);
            error_log("🎓 exitProgram: Trainer '$VolunteerID' logged out - deleted training_session_control record");

            // Fully log out all trainees assigned to this trainer
            // ($traineeID was captured earlier before TraineeID was set to NULL)
            if (!empty($traineeID)) {
                $traineeIds = array_map('trim', explode(',', $traineeID));
                foreach ($traineeIds as $traineeIdToExit) {
                    if (empty($traineeIdToExit)) continue;

                    // Log trainee exit
                    dataQuery("INSERT INTO volunteerlog VALUES (null, ?, now(), 0, 0)", [$traineeIdToExit]);

                    // Fully log out the trainee (mirrors the main exitProgram cleanup)
                    dataQuery("UPDATE volunteers SET LoggedOn = 0, Active1 = NULL, Active2 = NULL, OnCall = 0, ChatInvite = NULL, Ringing = NULL, Muted = 0, IncomingCallSid = NULL WHERE UserName = ?", [$traineeIdToExit]);

                    // Remove from CallControl
                    dataQuery("DELETE FROM CallControl WHERE user_id = ?", [$traineeIdToExit]);

                    // Delete IMs
                    dataQuery("DELETE FROM VolunteerIM WHERE imTo = ? OR imFrom = ?", [$traineeIdToExit, $traineeIdToExit]);

                    // Send DB signal to notify trainee's browser that they've been logged out
                    require_once(__DIR__ . '/trainingShare3/lib/TrainingDB.php');
                    require_once(__DIR__ . '/trainingShare3/lib/SignalQueue.php');
                    SignalQueue::sendToParticipant($VolunteerID, $VolunteerID, $traineeIdToExit, 'trainer-exited', [
                        'trainerId' => $VolunteerID,
                        'message' => 'Your trainer has signed off. You have been logged out.'
                    ]);

                    error_log("🎓 exitProgram: Logged out trainee '$traineeIdToExit' (trainer '$VolunteerID' exited)");
                }

                // Publish logout events for all trainees
                try {
                    require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
                    $publisher = new VCCFeedPublisher();
                    foreach ($traineeIds as $traineeIdToExit) {
                        if (empty($traineeIdToExit)) continue;
                        $publisher->publishUserListChange('logout', [
                            'username' => $traineeIdToExit,
                            'timestamp' => time(),
                            'reason' => 'trainer_exited'
                        ]);
                    }
                    $publisher->refreshUserListCache();
                } catch (Exception $e) {
                    error_log("VCCFeedPublisher error on trainee logout: " . $e->getMessage());
                }
            }

            // Clean up training_rooms and training_participants
            require_once(__DIR__ . '/trainingShare3/lib/TrainingDB.php');
            TrainingDB::cleanupSession($VolunteerID);
            error_log("🎓 exitProgram: Cleaned up training session tables for trainer '$VolunteerID'");

        } elseif ($isTrainee && $roomName) {
            // Trainee exiting: Check if they had control, transfer back to trainer
            $checkControl = "SELECT active_controller FROM training_session_control WHERE trainer_id = ?";
            $controlResult = dataQuery($checkControl, [$roomName]);

            if ($controlResult && count($controlResult) > 0 && $controlResult[0]->active_controller === $VolunteerID) {
                // Trainee had control - transfer back to trainer
                $transferControl = "UPDATE training_session_control
                                   SET active_controller = trainer_id, controller_role = 'trainer'
                                   WHERE trainer_id = ?";
                dataQuery($transferControl, [$roomName]);
                error_log("🎓 exitProgram: Trainee '$VolunteerID' had control - transferred back to trainer '$roomName'");

                // Re-add trainer to CallControl so they can receive calls
                $readdTrainer = "INSERT INTO CallControl (user_id, logged_on_status, can_receive_calls, can_receive_chats)
                                VALUES (?, 4, 1, 1)
                                ON DUPLICATE KEY UPDATE can_receive_calls = 1, can_receive_chats = 1";
                dataQuery($readdTrainer, [$roomName]);
                error_log("🎓 exitProgram: Re-added trainer '$roomName' to CallControl");
            }

            // Send DB signal to notify trainer that trainee has left
            require_once(__DIR__ . '/trainingShare3/lib/TrainingDB.php');
            require_once(__DIR__ . '/trainingShare3/lib/SignalQueue.php');

            // Include whether control was transferred back so trainer JS can update state
            $hadControl = ($controlResult && count($controlResult) > 0 && $controlResult[0]->active_controller === $VolunteerID);
            SignalQueue::sendToParticipant($roomName, $VolunteerID, $roomName, 'trainee-exited', [
                'traineeId' => $VolunteerID,
                'message' => $VolunteerID . ' has left the training session',
                'controlReturnedToTrainer' => $hadControl
            ]);
            error_log("🎓 exitProgram: Sent trainee-exited DB signal for trainer '$roomName'" . ($hadControl ? ' (control returned)' : ''));

            // Remove trainee from training_participants
            TrainingDB::removeParticipant($roomName, $VolunteerID);
            error_log("🎓 exitProgram: Removed trainee '$VolunteerID' from training_participants");
        }

        // Notify media server of exit
        if ($roomName && ($isTrainer || $isTrainee)) {
            $eventType = $isTrainer ? 'trainer-signed-off' : 'trainee-signed-off';
            notifyMediaServer($eventType, [
                'roomName' => $roomName,
                'userId' => $VolunteerID,
                'role' => $isTrainer ? 'trainer' : 'trainee'
            ]);
        }      
    
        // Check logged on volunteers count
        $query7 = "SELECT count(username) as count FROM volunteers WHERE loggedon = 1";
        $result7 = dataQuery($query7);
        $LastLogOff = $result7[0]->count;
    
        // Delete volunteer IMs
        $query8 = "DELETE FROM VolunteerIM WHERE imTo = ? OR imFrom = ?";
        $result8 = dataQuery($query8, [$VolunteerID, $VolunteerID]);
    
        // If last volunteer logging off, clear chat
        if ($LastLogOff == 0) {
            $query9 = "DELETE FROM Chat";
            $result9 = dataQuery($query9);
        }

        echo "OK";

        // Clear session auth so login.php won't redirect back to index2.php
        // (This code only runs for intentional exits - sendBeacon exits break early above)
        $_SESSION['auth'] = '';
        $_SESSION['UserID'] = '';

        // Release session lock for clean shutdown
        session_write_close();
        break;

    // clearSession - Called when server detects LoggedOn=0 (cascade logout)
    // Clears the PHP session so login.php doesn't redirect back to index2.php
    case 'clearSession':
        $_SESSION['auth'] = '';
        $_SESSION['UserID'] = '';
        session_write_close();
        echo "OK";
        break;

    // restoreSession is no longer needed since sendBeacon no longer changes LoggedOn status
    // Keeping as no-op for backwards compatibility in case old JS code calls it
    case 'restoreSession':
        error_log("restoreSession: Called by $VolunteerID - no longer needed (no-op)");
        echo "OK";
        break;

    // Heartbeat - client sends this every 30 seconds to indicate they're still active
    // Also cleans up stale users who haven't sent a heartbeat in 2+ minutes
    // Cleanup mirrors the intentional exit logic (exitProgram with intentional=true)
    case 'heartbeat':
        if (empty($VolunteerID)) {
            echo json_encode(['status' => 'error', 'message' => 'No user ID']);
            break;
        }

        // Update this user's heartbeat timestamp
        $updateQuery = "UPDATE volunteers SET LastHeartbeat = NOW() WHERE UserName = ?";
        dataQuery($updateQuery, [$VolunteerID]);

        // Find stale users first so we can do per-user cleanup
        $staleThreshold = 2; // minutes
        $findStaleQuery = "SELECT UserName, LoggedOn, TraineeID FROM volunteers
            WHERE LoggedOn > 0
            AND LastHeartbeat IS NOT NULL
            AND LastHeartbeat < DATE_SUB(NOW(), INTERVAL ? MINUTE)
            AND UserName != ?";
        $staleUsers = dataQuery($findStaleQuery, [$staleThreshold, $VolunteerID]);

        if (!empty($staleUsers)) {
            foreach ($staleUsers as $staleUser) {
                $staleId = $staleUser->UserName;
                $staleLoggedOn = $staleUser->LoggedOn;
                $staleTraineeID = $staleUser->TraineeID;

                error_log("heartbeat cleanup: Removing stale user '$staleId' (LoggedOn=$staleLoggedOn)");

                // Log exit in volunteerlog (mirrors exitProgram)
                dataQuery("INSERT INTO volunteerlog VALUES (null, ?, now(), 0, 0)", [$staleId]);

                // Remove from CallControl (mirrors exitProgram)
                dataQuery("DELETE FROM CallControl WHERE user_id = ?", [$staleId]);

                // Clean up training_session_control (mirrors exitProgram)
                if ($staleLoggedOn == 4) {
                    // Stale trainer: delete their training control record
                    dataQuery("DELETE FROM training_session_control WHERE trainer_id = ?", [$staleId]);
                    error_log("heartbeat cleanup: Deleted training_session_control for trainer '$staleId'");

                    // Fully log out all trainees assigned to this trainer (mirrors exit button flow)
                    if (!empty($staleTraineeID)) {
                        $traineeIds = array_map('trim', explode(',', $staleTraineeID));
                        foreach ($traineeIds as $traineeIdToExit) {
                            if (empty($traineeIdToExit)) continue;

                            // Log trainee exit
                            dataQuery("INSERT INTO volunteerlog VALUES (null, ?, now(), 0, 0)", [$traineeIdToExit]);

                            // Fully log out the trainee
                            dataQuery("UPDATE volunteers SET LoggedOn = 0, Active1 = NULL, Active2 = NULL, OnCall = 0, ChatInvite = NULL, Ringing = NULL, Muted = 0, IncomingCallSid = NULL WHERE UserName = ?", [$traineeIdToExit]);

                            // Remove from CallControl
                            dataQuery("DELETE FROM CallControl WHERE user_id = ?", [$traineeIdToExit]);

                            // Delete IMs
                            dataQuery("DELETE FROM VolunteerIM WHERE imTo = ? OR imFrom = ?", [$traineeIdToExit, $traineeIdToExit]);

                            // Send DB signal to notify trainee's browser
                            require_once(__DIR__ . '/trainingShare3/lib/TrainingDB.php');
                            require_once(__DIR__ . '/trainingShare3/lib/SignalQueue.php');
                            SignalQueue::sendToParticipant($staleId, $staleId, $traineeIdToExit, 'trainer-exited', [
                                'trainerId' => $staleId,
                                'message' => 'Your trainer has signed off. You have been logged out.',
                                'reason' => 'heartbeat-timeout'
                            ]);

                            error_log("heartbeat cleanup: Logged out trainee '$traineeIdToExit' (trainer '$staleId' went stale)");
                        }

                        // Publish logout events for all trainees
                        try {
                            require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
                            $publisher = new VCCFeedPublisher();
                            foreach ($traineeIds as $traineeIdToExit) {
                                if (empty($traineeIdToExit)) continue;
                                $publisher->publishUserListChange('logout', [
                                    'username' => $traineeIdToExit,
                                    'timestamp' => time(),
                                    'reason' => 'trainer_heartbeat_timeout'
                                ]);
                            }
                            $publisher->refreshUserListCache();
                        } catch (Exception $e) {
                            error_log("VCCFeedPublisher error on trainee logout (heartbeat): " . $e->getMessage());
                        }
                    }

                    // Clean up training_rooms and training_participants
                    require_once(__DIR__ . '/trainingShare3/lib/TrainingDB.php');
                    TrainingDB::cleanupSession($staleId);
                    error_log("heartbeat cleanup: Cleaned up training session tables for trainer '$staleId'");

                } elseif ($staleLoggedOn == 6) {
                    // Stale trainee: check if they had control, transfer back to trainer
                    $trainerQuery = "SELECT UserName FROM volunteers WHERE FIND_IN_SET(?, TraineeID) > 0";
                    $trainerResult = dataQuery($trainerQuery, [$staleId]);

                    if (!empty($trainerResult)) {
                        $trainerId = $trainerResult[0]->UserName;
                        $checkControl = "SELECT active_controller FROM training_session_control WHERE trainer_id = ?";
                        $controlResult = dataQuery($checkControl, [$trainerId]);

                        $hadControl = (!empty($controlResult) && $controlResult[0]->active_controller === $staleId);

                        if ($hadControl) {
                            // Transfer control back to trainer
                            dataQuery("UPDATE training_session_control SET active_controller = trainer_id, controller_role = 'trainer' WHERE trainer_id = ?", [$trainerId]);
                            // Re-add trainer to CallControl so they can receive calls
                            dataQuery("INSERT INTO CallControl (user_id, logged_on_status, can_receive_calls, can_receive_chats) VALUES (?, 4, 1, 1) ON DUPLICATE KEY UPDATE can_receive_calls = 1, can_receive_chats = 1", [$trainerId]);
                            error_log("heartbeat cleanup: Trainee '$staleId' had control - transferred back to trainer '$trainerId'");
                        }

                        // Send DB signal to notify trainer that trainee has left
                        require_once(__DIR__ . '/trainingShare3/lib/TrainingDB.php');
                        require_once(__DIR__ . '/trainingShare3/lib/SignalQueue.php');
                        SignalQueue::sendToParticipant($trainerId, $staleId, $trainerId, 'trainee-exited', [
                            'traineeId' => $staleId,
                            'message' => $staleId . ' has left the training session (heartbeat timeout)',
                            'reason' => 'heartbeat-timeout',
                            'controlReturnedToTrainer' => $hadControl
                        ]);
                        error_log("heartbeat cleanup: Sent trainee-exited DB signal for trainer '$trainerId'" . ($hadControl ? ' (control returned)' : ''));

                        // Remove stale trainee from training_participants
                        TrainingDB::removeParticipant($trainerId, $staleId);
                    }
                }

                // Clean up VolunteerIM (mirrors exitProgram)
                dataQuery("DELETE FROM VolunteerIM WHERE imTo = ? OR imFrom = ?", [$staleId, $staleId]);
            }

            // Bulk update volunteers table for all stale users
            $cleanupQuery = "UPDATE volunteers SET
                LoggedOn = 0,
                Active1 = NULL,
                Active2 = NULL,
                OnCall = 0,
                ChatInvite = NULL,
                Ringing = NULL,
                TraineeID = NULL,
                Muted = 0,
                IncomingCallSid = NULL
                WHERE LoggedOn > 0
                AND LastHeartbeat IS NOT NULL
                AND LastHeartbeat < DATE_SUB(NOW(), INTERVAL ? MINUTE)
                AND UserName != ?";
            dataQuery($cleanupQuery, [$staleThreshold, $VolunteerID]);

            // If no volunteers remain logged in, clear stale chat data
            $remainingQuery = "SELECT count(username) as count FROM volunteers WHERE LoggedOn > 0";
            $remainingResult = dataQuery($remainingQuery);
            if (!empty($remainingResult) && $remainingResult[0]->count == 0) {
                dataQuery("DELETE FROM Chat");
            }

            // Refresh Redis cache so polling clients see updated user list
            try {
                require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
                $publisher = new VCCFeedPublisher();
                $publisher->refreshUserListCache();
            } catch (Exception $e) {
                error_log("VCCFeedPublisher error during heartbeat cleanup: " . $e->getMessage());
            }
        }

        echo json_encode(['status' => 'ok', 'timestamp' => date('Y-m-d H:i:s')]);
        break;

    // LEGACY: trainingControl case removed - no longer using volunteers.Muted field for training control
    // Training control is now managed via training_session_control table
    // The Muted field in volunteers table is retained for compatibility but not used for training
    // See /trainingShare3/setTrainingControl.php for current implementation
    case 'trainingControl':
        error_log("LEGACY ENDPOINT: trainingControl called by '$VolunteerID' - redirecting to new system");
        die("ERROR: Legacy trainingControl endpoint deprecated. Use /trainingShare3/setTrainingControl.php");
        break;

   
    case 'monitorChatStart':
        $query = "SELECT Active1, Active2 FROM volunteers WHERE UserName = ?";
        $result = dataQuery($query, [$Action]);
        
        if ($result && count($result) > 0) {
            $Active1 = $result[0]->Active1;
            $Active2 = $result[0]->Active2;
        }
    
        // Commented out as in original
        // $query2 = "UPDATE volunteers SET Active1 = ?, Active2 = ? WHERE UserName = ?";
        // $result = dataQuery($query2, [$Active1, $Active2, $VolunteerID]);
    
        echo "OK";
        break;
    
    case 'monitorChatEnd':
        // Commented out as in original
        // $query2 = "UPDATE volunteers SET Active1 = NULL, Active2 = NULL WHERE UserName = ?";
        // $result = dataQuery($query2, [$VolunteerID]);
    
        echo "OK";
        break;
    
    case 'welcomeSlides':
        $Slides = array();
        
        foreach(glob('Images/Welcome/*.*') as $filename){
            array_push($Slides, $filename);
        }
    
        echo json_encode($Slides);
        break;
    
    case 'watchingVideo':
        $query2 = "UPDATE volunteers SET onCall = 1, Ringing = 'Watching Video' WHERE UserName = ?";
        $result = dataQuery($query2, [$VolunteerID]);
        echo "OK";
        break;
    
    case 'videoEnded':
        $query2 = "UPDATE volunteers SET onCall = 0, Ringing = NULL WHERE UserName = ?";
        $result = dataQuery($query2, [$VolunteerID]);
        echo "OK";
        break;
}

// Release session lock after all processing is complete (if session still exists)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

?>
