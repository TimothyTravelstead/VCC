<?php
// 1. Include db_login.php FIRST (sets session configuration)
require_once '../../private_html/db_login.php';

// 2. Start session (inherits 8-hour timeout from db_login.php)
session_cache_limiter('nocache');
session_start();

if ($_SESSION['auth'] != 'yes') {
    die("Unauthorized");
}

// DEEP DIVE DEBUG: Log everything
error_log("=== CHATFRAME.PHP DEBUG START ===");
error_log("Session auth: " . ($_SESSION['auth'] ?? 'MISSING'));
error_log("Session UserID: " . ($_SESSION['UserID'] ?? 'MISSING'));
error_log("Session trainer: " . ($_SESSION['trainer'] ?? 'MISSING'));
error_log("Session trainee: " . ($_SESSION['trainee'] ?? 'MISSING'));
error_log("Session TraineeList: " . ($_SESSION['TraineeList'] ?? 'MISSING'));
error_log("Session trainingChatRoomID: " . ($_SESSION['trainingChatRoomID'] ?? 'MISSING'));

// Enable error reporting to display errors in the browser
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include training chat database connection
require_once '../../private_html/training_chat_db_login.php';

// Set timezone in chat database using chatDataQuery
$timezoneQuery = "SET time_zone = ?";
chatDataQuery($timezoneQuery, [$offset]);

$UserID = $_SESSION['UserID'] ?? null;
$isTrainer = $_SESSION["trainer"] ?? false;  // true/false boolean
$isTrainee = $_SESSION["trainee"] ?? false;  // true/false boolean
$traineeList = $_SESSION["TraineeList"] ?? null;  // Comma-separated trainee IDs
$trainerID = null;  // Will be set based on role

// Determine trainer ID based on role (same logic as index2.php)
if ($isTrainer && $UserID) {
    // Current user is trainer
    $trainerID = $UserID;
    
    // Get trainee list if missing from session
    if (empty($traineeList)) {
        require_once '../../private_html/db_login.php';  // Main database
        $getTraineeQuery = "SELECT TraineeID FROM volunteers WHERE UserName = ? AND TraineeID IS NOT NULL AND TraineeID != ''";
        $traineeResult = dataQuery($getTraineeQuery, [$UserID]);
        if ($traineeResult && count($traineeResult) > 0) {
            $traineeList = $traineeResult[0]->TraineeID;
            $_SESSION['TraineeList'] = $traineeList; // Update session for consistency
            error_log("Retrieved TraineeList from database: " . $traineeList);
        }
    }
} elseif ($isTrainee && $UserID) {
    // Current user is trainee - find their trainer
    require_once '../../private_html/db_login.php';  // Main database
    $trainerQuery = "SELECT UserName FROM volunteers WHERE FIND_IN_SET(?, TraineeID) > 0";
    $trainerResult = dataQuery($trainerQuery, [$UserID]);
    
    if (!empty($trainerResult)) {
        $trainerID = $trainerResult[0]->UserName;
        error_log("Found trainer for trainee $UserID: $trainerID");
    } else {
        error_log("No trainer found for trainee $UserID");
    }
}

error_log("Computed UserID: " . ($UserID ?? 'NULL'));
error_log("Current user is trainer: " . ($isTrainer ? 'true' : 'false'));
error_log("TraineeList from session/database: " . ($traineeList ?? 'NULL'));

// GET PARTICIPANTS DIRECTLY FROM SESSION VARIABLES - NO API CALLS!
error_log("Building participants from session variables directly");

$participants = [];

// Add the CURRENT USER to participants (trainer or trainee)
if ($UserID) {
    if ($isTrainer) {
        $participants[] = [
            'id' => $UserID,
            'name' => $_SESSION['currentUserFullName'] ?? 'Trainer',
            'role' => 'trainer',
            'isSignedOn' => true  // They're loading this page
        ];
        error_log("Added trainer to participants: $UserID");
    } elseif ($isTrainee) {
        $participants[] = [
            'id' => $UserID,
            'name' => $_SESSION['currentUserFullName'] ?? 'Trainee',
            'role' => 'trainee',
            'isSignedOn' => true  // They're loading this page
        ];
        error_log("Added trainee (self) to participants: $UserID");
    }
}

// Add TRAINEES from session TraineeList
$trainees = [];
if (!empty($traineeList)) {
    $trainees = explode(',', $traineeList);
    error_log("Found trainees in session: " . $traineeList);
}

// Get trainee names from main database if we have trainee IDs
if (!empty($trainees)) {
    require_once '../../private_html/db_login.php';  // Main database for volunteer names
    
    foreach ($trainees as $traineeId) {
        $traineeId = trim($traineeId);
        if (!empty($traineeId)) {
            // Get trainee info from main database
            $traineeQuery = "SELECT UserName, FirstName, LastName, LoggedOn FROM volunteers WHERE UserName = ?";
            $traineeResult = dataQuery($traineeQuery, [$traineeId]);
            
            if ($traineeResult && count($traineeResult) > 0) {
                $traineeData = $traineeResult[0];
                $participants[] = [
                    'id' => $traineeData->UserName,
                    'name' => trim($traineeData->FirstName . ' ' . $traineeData->LastName),
                    'role' => 'trainee', 
                    'isSignedOn' => in_array($traineeData->LoggedOn, [6, 7])  // 6=trainee, 7=trainee+admin
                ];
                error_log("Added trainee to participants: " . $traineeData->UserName . " (LoggedOn: " . $traineeData->LoggedOn . ")");
            } else {
                // Add trainee even if not found in database (with default values)
                $participants[] = [
                    'id' => $traineeId,
                    'name' => $traineeId,
                    'role' => 'trainee',
                    'isSignedOn' => false  // Unknown status
                ];
                error_log("Added unknown trainee to participants: $traineeId");
            }
        }
    }
}

error_log("Total participants built from session: " . count($participants));

// COMPLETE ROOM SETUP - CHATFRAME.PHP DOES EVERYTHING!!!
// For trainers: Create room
// For trainees: Find trainer's room
if ($trainerID) {
    error_log("=== ROOM SETUP FOR TRAINER ID: $trainerID ===");
    
    if ($isTrainer && $UserID) {
        // TRAINER: Create new room
        error_log("User is trainer - creating new room");
    
    // CLEAN SLATE: Delete any existing room and all associated data for this trainer
    $getOldRoomQuery = "SELECT id FROM groupchatrooms WHERE Name = ?";
    $getOldRoomResult = chatDataQuery($getOldRoomQuery, [$UserID]);
    
    if ($getOldRoomResult && $getOldRoomResult[0]->id) {
        $oldRoomID = $getOldRoomResult[0]->id;
        error_log("Cleaning up existing room for trainer $UserID: roomID = $oldRoomID");
        
        // Delete all messages/transactions for this room
        $deleteMessagesQuery = "DELETE FROM transactions WHERE chatRoomID = ?";
        chatDataQuery($deleteMessagesQuery, [$oldRoomID]);
        
        // Delete all callers for this room  
        $deleteCallersQuery = "DELETE FROM callers WHERE chatRoomID = ?";
        chatDataQuery($deleteCallersQuery, [$oldRoomID]);
        
        // Delete the room itself
        $deleteRoomQuery = "DELETE FROM groupchatrooms WHERE id = ?";
        chatDataQuery($deleteRoomQuery, [$oldRoomID]);
        
        error_log("Cleaned up old room $oldRoomID for trainer $UserID");
    }
    
    // Create fresh new room for this trainer
    $createRoomQuery = "INSERT INTO groupchatrooms (Name, Open) VALUES (?, 1)";
    $createRoomResult = chatDataQuery($createRoomQuery, [$UserID]);
    
    // Get the NEW room ID
    $getRoomQuery = "SELECT id FROM groupchatrooms WHERE Name = ? ORDER BY id DESC LIMIT 1";
    $getRoomResult = chatDataQuery($getRoomQuery, [$UserID]);
    $chatRoomID = $getRoomResult ? $getRoomResult[0]->id : null;
    
    if ($chatRoomID) {
        error_log("Created room $chatRoomID for trainer $UserID");
        
        // Send room status "open" message so JavaScript enables the UI
        $roomStatusQuery = "INSERT INTO transactions (type, action, UserID, chatRoomID, messageNumber, Message, created) 
                           VALUES ('roomStatus', '1', 'SYSTEM', ?, 0, 'Room is now open for training', NOW())";
        chatDataQuery($roomStatusQuery, [$chatRoomID]);
        error_log("Sent room status 'open' message for room $chatRoomID");
        
        // REGISTER ALL PARTICIPANTS (trainer + all trainees) IN CALLERS TABLE
        // Register everyone regardless of isSignedOn status for training purposes
        foreach ($participants as $participant) {
            $participantId = $participant['id'];
            $participantName = $participant['name'];
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            
            // Check if participant already exists
            $checkQuery = "SELECT id FROM callers WHERE userID = ? AND chatRoomID = ?";
            $checkResult = chatDataQuery($checkQuery, [$participantId, $chatRoomID]);
            
            if (!$checkResult) {
                // Insert new participant
                $insertQuery = "INSERT INTO callers (userID, name, chatRoomID, ipAddress, status, modified) 
                               VALUES (?, ?, ?, ?, 1, NOW())";
                $insertResult = chatDataQuery($insertQuery, [$participantId, $participantName, $chatRoomID, $ipAddress]);
                error_log("Registered participant $participantId ($participantName) in room $chatRoomID");
            } else {
                // Update existing participant
                $updateQuery = "UPDATE callers SET name = ?, status = 1, modified = NOW() 
                               WHERE userID = ? AND chatRoomID = ?";
                $updateResult = chatDataQuery($updateQuery, [$participantName, $participantId, $chatRoomID]);
                error_log("Updated participant $participantId ($participantName) in room $chatRoomID");
            }
        }
        
        // Store chatRoomID for JavaScript
        $_SESSION['trainingChatRoomID'] = $chatRoomID;
        error_log("=== ROOM SETUP COMPLETE - Room ID: $chatRoomID ===");
    } else {
        error_log("FATAL: Failed to create room for trainer $UserID");
    }
    } elseif ($isTrainee && $UserID) {
        // TRAINEE: Find their room from callers table
        error_log("User is trainee - finding room from callers table for UserID: $UserID");
        
        // Look up the trainee in callers table to find their chatRoomID
        $getCallerQuery = "SELECT chatRoomID FROM callers WHERE userID = ? ORDER BY modified DESC LIMIT 1";
        $getCallerResult = chatDataQuery($getCallerQuery, [$UserID]);
        
        if ($getCallerResult && $getCallerResult[0]->chatRoomID) {
            $chatRoomID = $getCallerResult[0]->chatRoomID;
            error_log("Found trainee's room from callers table: $chatRoomID");
            
            // Now get the trainer's name from the room
            $getRoomQuery = "SELECT Name FROM groupchatrooms WHERE id = ?";
            $getRoomResult = chatDataQuery($getRoomQuery, [$chatRoomID]);
            if ($getRoomResult && $getRoomResult[0]->Name) {
                $trainerID = $getRoomResult[0]->Name;
                error_log("Found trainer from room: $trainerID");
            }
            
            $_SESSION['trainingChatRoomID'] = $chatRoomID;
        } else {
            error_log("Trainee not found in any room - trainer may not have started session yet");
            $chatRoomID = 0;
        }
    }
} else {
    error_log("SKIPPING room setup - no trainerID found");
    $chatRoomID = 0;
}

function getRealIpAddr() {
    if (!empty($_SERVER['HTTP_CLIENT_IP']))   
    {
        $ip=$_SERVER['HTTP_CLIENT_IP'];
    }
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   
    {
        $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    else
    {
        $ip=$_SERVER['REMOTE_ADDR'];
    }
    
    if(empty($ip)) {
        $ip = 0;       
    }
    return $ip;
}

// Fetch participant names from the database
function getUserNames($usernames) {
    require_once '../../private_html/db_login2.php';
    if (empty($usernames)) {
        return [];
    }
    $placeholders = implode(",", array_fill(0, count($usernames), "?"));
    $query = "SELECT UserName, FirstName, LastName FROM volunteers WHERE UserName IN ($placeholders)";
    $results = dataQueryTwo($query, $usernames);

    $userNames = [];
    if ($results) {
        foreach ($results as $row) {
            $userNames[$row->UserName] = trim($row->FirstName . ' ' . $row->LastName);
        }
    }
    return $userNames;
}

$traineeArray = $traineeList ? explode(',', $traineeList) : [];

// Build list of all usernames that need to be looked up
$allUserNames = [];

// Add trainer ID (whether current user is trainer or trainee)
if ($trainerID) {
    $allUserNames[] = $trainerID;
}

// Add all trainees from the list
$allUserNames = array_merge($allUserNames, $traineeArray);

// Add current user if not already included
if (!in_array($UserID, $allUserNames)) {
    $allUserNames[] = $UserID;
}

error_log("All usernames to lookup: " . print_r($allUserNames, true));

// Fetch names
$userNames = getUserNames($allUserNames);
$userNamesJSON = json_encode($userNames);
error_log("Built userNames array: " . print_r($userNames, true));
error_log("userNamesJSON: " . $userNamesJSON);

// Set currentUserFullName from database or session, with fallback
$currentUserFullName = $_SESSION['currentUserFullName'] ?? $userNames[$UserID] ?? 'Unknown User';
$_SESSION['currentUserFullName'] = $currentUserFullName;
$ipAddress = $_SESSION['ipAddress'] = getRealIpAddr();

// Room setup is handled above - get the chatRoomID that was created
$chatRoomID = $_SESSION['trainingChatRoomID'] ?? 0;
session_write_close();

// Participant registration is handled in the room setup above
// No need for duplicate registration here
error_log("Using chatRoomID: $chatRoomID for JavaScript");

// Note: Removed LoggedOn update to preserve training status (4=trainer, 6=trainee)
// TrainingChat uses its own callers table for status tracking



?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, private">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no" />
  <title>VCC Training Chat</title>
  <link type="text/css" rel="stylesheet" href="groupChat2025.css">
  <link type="text/css" rel="stylesheet" href="nicEditPanel.css">
  
  <script src="nicEdit/nicEdit.js" type="text/javascript"></script>
  <script src="./groupChat2025.js" type="text/javascript"></script>
  <script src="../LibraryScripts/Ajax.js" type="text/javascript"></script>
  <script src="../LibraryScripts/domAndString.js" type="text/javascript"></script>
  <script src="../LibraryScripts/Dates.js" type="text/javascript"></script>
</head>
<body id="body">
    <div id="groupChatContainer">
        <!-- Messages Area -->
        <div id="groupChatUpperChatArea">
            <div id="groupChatMainWindow">
                <!-- Messages will be dynamically inserted here -->
            </div>
        </div>

        <!-- Input Area -->
        <div id="groupChatTypingWrapper">
            <div class="input-container">
                <div id="groupChatTypingWindow" 
                     contenteditable="true" 
                     data-placeholder="Type your message..."
                     role="textbox" 
                     aria-label="Message input"></div>
                <button type="submit" id="groupChatSubmitButton" aria-label="Send message">
                    ↑
                </button>
            </div>
        </div>
    </div>

    <!-- Connection Status Indicator -->
    <div id="connectionStatus" style="position: fixed; top: 10px; left: 10px; padding: 8px 12px; 
         background: #ffcc00; color: #000; border-radius: 4px; font-size: 12px; display: none; 
         font-family: 'Times New Roman', serif; font-weight: bold; z-index: 10001; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
        <span id="statusText">Connecting to training room...</span>
        <div id="statusSpinner" style="display: inline-block; margin-left: 8px; animation: spin 1s linear infinite;">⟳</div>
    </div>

    <!-- Hidden inputs -->
    <input id='trainerID' type='hidden' value='<?php echo htmlspecialchars($trainerID ?? ''); ?>'>
    <input id='trainer' type='hidden' value='<?php echo htmlspecialchars($trainerID ?? ''); ?>'>
    <input id='volunteerID' type='hidden' value='<?php echo htmlspecialchars($UserID); ?>'>
    <input id='userID' type='hidden' value='<?php echo htmlspecialchars($UserID); ?>'>
    <input id='groupChatRoomID' type='hidden' value='<?php echo htmlspecialchars($chatRoomID); ?>'>
    <input id='assignedTraineeIDs' type='hidden' value='<?php echo htmlspecialchars($traineeList); ?>'>
    <input id='userNames' type='hidden' value='<?php echo htmlspecialchars($userNamesJSON); ?>'>
    <input id='currentUserFullName' type='hidden' value='<?php echo htmlspecialchars($currentUserFullName); ?>'>

    <style>
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
    
    <script>
	// Enhanced page unload handler in chatFrame.php
	window.addEventListener('beforeunload', function() {
		// Mark user as offline when leaving
		if (navigator.sendBeacon) {
			const formData = new FormData();
			formData.append('action', 'user_leaving');
			formData.append('userID', document.getElementById('userID').value);
			formData.append('chatRoomID', document.getElementById('groupChatRoomID').value);
			
			navigator.sendBeacon('userDeparture.php', formData);
		} else {
			// Fallback for browsers that don't support sendBeacon
			const xhr = new XMLHttpRequest();
			xhr.open('POST', 'userDeparture.php', false); // Synchronous for unload
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.send(`action=user_leaving&userID=${document.getElementById('userID').value}&chatRoomID=${document.getElementById('groupChatRoomID').value}`);
		}
	});

	// Also mark as offline on page visibility change (when tab is closed/minimized for extended time)
	document.addEventListener('visibilitychange', function() {
		if (document.hidden) {
			// Page is hidden, start a timer
			setTimeout(() => {
				if (document.hidden) {
					// Still hidden after 2 minutes, mark as inactive
					fetch('userDeparture.php', {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: `action=user_inactive&userID=${document.getElementById('userID').value}&chatRoomID=${document.getElementById('groupChatRoomID').value}`
					});
				}
			}, 120000); // 2 minutes
		}
	});

    // Periodic cleanup check (every 2 minutes)
    setInterval(function() {
        fetch('chatCleanup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        }).catch(error => {
            console.log('Cleanup check failed:', error);
        });
    }, 120000); // 2 minutes
    </script>
</body>
</html>