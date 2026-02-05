<?php
session_start();
if ($_SESSION['auth'] != 'yes') {
    die("Unauthorized");
} 

// Get room ID and participant info - ALWAYS use session data for security
$roomId = $_GET["trainingShareRoom"] ?? '';
$participantId = $_SESSION['UserID'] ?? '';

// Determine role from session data, not URL parameters (more secure)
$participantRole = 'trainee'; // default
if (isset($_SESSION['trainer']) && $_SESSION['trainer'] == '1') {
    $participantRole = 'trainer';
} elseif (isset($_SESSION['trainee']) && $_SESSION['trainee'] == '1') {
    $participantRole = 'trainee';
}

if (empty($roomId) || empty($participantId)) {
    die("Missing room or participant ID");
}

// Create room-based signaling structure
$roomFile = dirname(__FILE__) . '/Signals/room_' . $roomId . '.json';
$participantFile = dirname(__FILE__) . '/Signals/participant_' . $participantId . '.txt';

// Ensure Signals directory exists
if (!file_exists(dirname(__FILE__) . '/Signals/')) {
    mkdir(dirname(__FILE__) . '/Signals/', 0755, true);
}

if (count($_POST) != 0 || $_SERVER['REQUEST_METHOD'] === 'POST') { 
    // POST request - send signal to room
    
    $posted = file_get_contents('php://input');
    
    // Try to decode as JSON first
    $signal = json_decode($posted, true);
    
    // If it's not valid JSON, it might be URL-encoded from AjaxRequest
    if (!$signal && $posted) {
        // Try to parse as URL-encoded string
        parse_str($posted, $parsed);
        // Check if there's a JSON string in the parsed data
        if (isset($parsed) && is_array($parsed) && count($parsed) === 1) {
            // Get the first (and only) value which should be our JSON
            $jsonString = reset($parsed);
            $signal = json_decode($jsonString, true);
        }
    }
    
    if (!$signal) {
        die("Invalid signal format");
    }
    
    // Add sender info and timestamp - FORCE server-side values for security
    $signal['from'] = $participantId;  // ALWAYS use session UserID
    $signal['fromRole'] = $participantRole;  // ALWAYS use session-derived role
    $signal['timestamp'] = microtime(true);
    
    // Remove any client-provided sender fields to prevent spoofing
    unset($signal['participantId']); // Client might send wrong value
    unset($signal['roomId']); // Client might send wrong value
    
    // Set server-authoritative values
    $signal['senderId'] = $participantId; // Clear sender identification
    $signal['senderRole'] = $participantRole; // Clear role identification
    $signal['targetRoom'] = $roomId; // Server-determined room
    
    // Debug logging for sender identity verification
    error_log("SIGNALING DEBUG: Signal type '{$signal['type']}' from '$participantId' (role: '$participantRole') in room '$roomId'");
    
    // Handle different signal types
    switch ($signal['type']) {
        case 'join-room':
            handleJoinRoom($roomFile, $participantId, $participantRole);
            break;
            
        case 'leave-room':
            handleLeaveRoom($roomFile, $participantId);
            break;
            
        case 'offer':
        case 'answer':
        case 'ice-candidate':
            // WebRTC signaling - route to specific participant or broadcast
            routeSignal($signal, $roomFile, $participantId);
            break;
            
        case 'screen-share-start':
        case 'screen-share-stop':
            // Screen sharing control - broadcast to all participants
            broadcastToRoom($signal, $roomFile, $participantId);
            break;
            
        default:
            // Generic message - broadcast to room
            broadcastToRoom($signal, $roomFile, $participantId);
    }
    
} else { 
    // GET request - no longer used since we're using vccFeed.php
    die("Screen sharing now uses the main EventSource");
}

// Helper functions

function handleJoinRoom($roomFile, $participantId, $participantRole) {
    $room = loadRoom($roomFile);
    
    // Add participant to room
    $room['participants'][$participantId] = [
        'role' => $participantRole,
        'joinedAt' => time(),
        'lastSeen' => time()
    ];
    
    saveRoom($roomFile, $room);
    
    // Notify all other participants
    $joinMessage = [
        'type' => 'participant-joined',
        'participantId' => $participantId,
        'participantRole' => $participantRole,
        'room' => $room
    ];
    
    broadcastToRoom($joinMessage, $roomFile, $participantId);
}

function handleLeaveRoom($roomFile, $participantId) {
    $room = loadRoom($roomFile);
    
    if (isset($room['participants'][$participantId])) {
        unset($room['participants'][$participantId]);
        saveRoom($roomFile, $room);
        
        // Notify remaining participants
        $leaveMessage = [
            'type' => 'participant-left',
            'participantId' => $participantId
        ];
        
        broadcastToRoom($leaveMessage, $roomFile, $participantId);
    }
}

function routeSignal($signal, $roomFile, $senderId) {
    $room = loadRoom($roomFile);
    
    if (isset($signal['to'])) {
        // Direct message to specific participant
        sendToParticipant($signal, $signal['to']);
    } else {
        // Broadcast to all other participants in room
        foreach ($room['participants'] as $participantId => $participant) {
            if ($participantId !== $senderId) {
                sendToParticipant($signal, $participantId);
            }
        }
    }
}

function broadcastToRoom($message, $roomFile, $senderId) {
    $room = loadRoom($roomFile);
    
    foreach ($room['participants'] as $participantId => $participant) {
        if ($participantId !== $senderId) {
            sendToParticipant($message, $participantId);
        }
    }
}

function sendToParticipant($message, $participantId) {
    $participantFile = dirname(__FILE__) . '/Signals/participant_' . $participantId . '.txt';
    
    $messageJson = json_encode($message);
    
    // Debug logging for signal delivery
    $messageType = $message['type'] ?? 'unknown';
    $senderId = $message['senderId'] ?? $message['from'] ?? 'unknown';
    error_log("SIGNALING DEBUG: Delivering '$messageType' signal from '$senderId' to '$participantId'");
    
    // Append message to participant's file
    $file = fopen($participantFile, 'a');
    if ($file) {
        if (filesize($participantFile) > 0) {
            fwrite($file, '_MULTIPLEVENTS_');
        }
        fwrite($file, $messageJson);
        fclose($file);
    } else {
        error_log("SIGNALING ERROR: Could not open participant file for '$participantId'");
    }
}

function loadRoom($roomFile) {
    if (file_exists($roomFile)) {
        $content = file_get_contents($roomFile);
        $room = json_decode($content, true);
        if ($room) {
            return $room;
        }
    }
    
    // Default empty room
    return [
        'participants' => [],
        'createdAt' => time(),
        'lastActivity' => time()
    ];
}

function saveRoom($roomFile, $room) {
    $room['lastActivity'] = time();
    file_put_contents($roomFile, json_encode($room, JSON_PRETTY_PRINT));
}

?>