<?php
/**
 * Non-Authenticated WebRTC Signaling Server
 * 
 * Simple signaling server for testing WebRTC screen sharing
 * No authentication - just passes messages between participants
 */

// Enable CORS for cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Signals directory for file-based message queue
$signalsDir = __DIR__ . '/NoAuthSignals';
if (!is_dir($signalsDir)) {
    mkdir($signalsDir, 0755, true);
}

// Get room ID from request
$roomId = $_GET['roomId'] ?? $_POST['roomId'] ?? 'default';
$participantId = $_GET['participantId'] ?? $_POST['participantId'] ?? uniqid('participant_');

// Sanitize room and participant IDs
$roomId = preg_replace('/[^a-zA-Z0-9_-]/', '', $roomId);
$participantId = preg_replace('/[^a-zA-Z0-9_-]/', '', $participantId);

// Room file for participant management
$roomFile = $signalsDir . '/room_' . $roomId . '.json';

function updateRoom($roomFile, $participantId, $action = 'join') {
    $room = [];
    if (file_exists($roomFile)) {
        $content = file_get_contents($roomFile);
        $room = json_decode($content, true) ?: [];
    }
    
    if (!isset($room['participants'])) {
        $room['participants'] = [];
    }
    
    if ($action === 'join') {
        $room['participants'][$participantId] = [
            'joinedAt' => time(),
            'lastSeen' => time()
        ];
        $room['lastActivity'] = time();
        if (!isset($room['createdAt'])) {
            $room['createdAt'] = time();
        }
    } elseif ($action === 'leave') {
        unset($room['participants'][$participantId]);
        $room['lastActivity'] = time();
    }
    
    file_put_contents($roomFile, json_encode($room, JSON_PRETTY_PRINT));
    return $room;
}

function broadcastToRoom($signalsDir, $roomId, $message, $excludeParticipant = null) {
    $roomFile = $signalsDir . '/room_' . $roomId . '.json';
    if (!file_exists($roomFile)) {
        return;
    }
    
    $room = json_decode(file_get_contents($roomFile), true);
    if (!$room || !isset($room['participants'])) {
        return;
    }
    
    foreach ($room['participants'] as $participantId => $participant) {
        if ($excludeParticipant && $participantId === $excludeParticipant) {
            continue;
        }
        
        $participantFile = $signalsDir . '/participant_' . $participantId . '.txt';
        $messageStr = json_encode($message);
        
        // Append to existing file or create new
        if (file_exists($participantFile)) {
            $existing = file_get_contents($participantFile);
            $messageStr = $existing . '_MULTIPLEVENTS_' . $messageStr;
        }
        
        file_put_contents($participantFile, $messageStr);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle signaling messages
    $input = file_get_contents('php://input');
    $message = json_decode($input, true);
    
    if (!$message || !isset($message['type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid message format']);
        exit;
    }
    
    // Add sender info to message
    $message['from'] = $participantId;
    $message['timestamp'] = time();
    
    switch ($message['type']) {
        case 'join-room':
            $room = updateRoom($roomFile, $participantId, 'join');
            
            // Notify others of new participant
            broadcastToRoom($signalsDir, $roomId, [
                'type' => 'participant-joined',
                'participantId' => $participantId,
                'room' => $room
            ], $participantId);
            
            echo json_encode(['status' => 'joined', 'room' => $room]);
            break;
            
        case 'leave-room':
            $room = updateRoom($roomFile, $participantId, 'leave');
            
            // Notify others of participant leaving
            broadcastToRoom($signalsDir, $roomId, [
                'type' => 'participant-left',
                'participantId' => $participantId,
                'room' => $room
            ], $participantId);
            
            echo json_encode(['status' => 'left', 'room' => $room]);
            break;
            
        case 'offer':
        case 'answer':
        case 'ice-candidate':
            // Direct WebRTC signaling - send to specific target
            $targetId = $message['to'] ?? null;
            if ($targetId) {
                $targetFile = $signalsDir . '/participant_' . $targetId . '.txt';
                $messageStr = json_encode($message);
                
                if (file_exists($targetFile)) {
                    $existing = file_get_contents($targetFile);
                    $messageStr = $existing . '_MULTIPLEVENTS_' . $messageStr;
                }
                
                file_put_contents($targetFile, $messageStr);
                echo json_encode(['status' => 'sent to ' . $targetId]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Target participant required']);
            }
            break;
            
        case 'screen-share-start':
        case 'screen-share-stop':
            // Broadcast to all participants in room
            broadcastToRoom($signalsDir, $roomId, $message, $participantId);
            echo json_encode(['status' => 'broadcasted']);
            break;
            
        default:
            // Broadcast any other message type to all participants
            broadcastToRoom($signalsDir, $roomId, $message, $participantId);
            echo json_encode(['status' => 'broadcasted']);
            break;
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle EventSource connections for receiving signals
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    
    echo "retry: 1000\n\n";
    
    $participantFile = $signalsDir . '/participant_' . $participantId . '.txt';
    $count = 0;
    
    // Join room on connection
    updateRoom($roomFile, $participantId, 'join');
    
    while ($count < 300) { // 5 minutes max
        if (connection_aborted()) {
            break;
        }
        
        // Check for messages
        if (file_exists($participantFile)) {
            $content = file_get_contents($participantFile);
            if (!empty($content)) {
                echo "event: signal\n";
                echo "data: " . $content . "\n\n";
                
                // Clear the file after sending
                unlink($participantFile);
                
                ob_flush();
                flush();
            }
        }
        
        sleep(1);
        $count++;
    }
    
    // Clean up on disconnect
    updateRoom($roomFile, $participantId, 'leave');
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>