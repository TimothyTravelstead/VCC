<?php
session_start();
if ($_SESSION['auth'] != 'yes') {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$roomId = $_GET['roomId'] ?? '';

// Ensure Signals directory exists
$signalsDir = dirname(__FILE__) . '/Signals/';
if (!file_exists($signalsDir)) {
    mkdir($signalsDir, 0755, true);
}

switch ($method) {
    case 'GET':
        handleGetRequest($action, $roomId);
        break;
    case 'POST':
        handlePostRequest($action, $roomId);
        break;
    case 'DELETE':
        handleDeleteRequest($action, $roomId);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function handleGetRequest($action, $roomId) {
    switch ($action) {
        case 'room-status':
            getRoomStatus($roomId);
            break;
        case 'participant-list':
            getParticipantList($roomId);
            break;
        case 'active-rooms':
            getActiveRooms();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePostRequest($action, $roomId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create-room':
            createRoom($roomId, $input);
            break;
        case 'join-room':
            joinRoom($roomId, $input);
            break;
        case 'update-participant':
            updateParticipant($roomId, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handleDeleteRequest($action, $roomId) {
    switch ($action) {
        case 'leave-room':
            leaveRoom($roomId);
            break;
        case 'close-room':
            closeRoom($roomId);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function getRoomStatus($roomId) {
    if (empty($roomId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Room ID required']);
        return;
    }
    
    $room = loadRoom($roomId);
    
    // Clean up inactive participants (older than 5 minutes)
    $cutoff = time() - 300;
    foreach ($room['participants'] as $participantId => $participant) {
        if ($participant['lastSeen'] < $cutoff) {
            unset($room['participants'][$participantId]);
        }
    }
    
    if (!empty($room['participants'])) {
        saveRoom($roomId, $room);
    }
    
    echo json_encode([
        'roomId' => $roomId,
        'participantCount' => count($room['participants']),
        'participants' => $room['participants'],
        'createdAt' => $room['createdAt'],
        'lastActivity' => $room['lastActivity']
    ]);
}

function getParticipantList($roomId) {
    if (empty($roomId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Room ID required']);
        return;
    }
    
    $room = loadRoom($roomId);
    $participants = [];
    
    foreach ($room['participants'] as $participantId => $participant) {
        $participants[] = [
            'id' => $participantId,
            'role' => $participant['role'],
            'joinedAt' => $participant['joinedAt'],
            'lastSeen' => $participant['lastSeen'],
            'isActive' => (time() - $participant['lastSeen']) < 60
        ];
    }
    
    echo json_encode(['participants' => $participants]);
}

function getActiveRooms() {
    $signalsDir = dirname(__FILE__) . '/Signals/';
    $rooms = [];
    
    if (is_dir($signalsDir)) {
        $files = scandir($signalsDir);
        foreach ($files as $file) {
            if (preg_match('/^room_(.+)\.json$/', $file, $matches)) {
                $roomId = $matches[1];
                $room = loadRoom($roomId);
                
                // Only include rooms with active participants
                $activeParticipants = 0;
                $cutoff = time() - 300; // 5 minutes
                
                foreach ($room['participants'] as $participant) {
                    if ($participant['lastSeen'] > $cutoff) {
                        $activeParticipants++;
                    }
                }
                
                if ($activeParticipants > 0) {
                    $rooms[] = [
                        'roomId' => $roomId,
                        'participantCount' => $activeParticipants,
                        'createdAt' => $room['createdAt'],
                        'lastActivity' => $room['lastActivity']
                    ];
                }
            }
        }
    }
    
    echo json_encode(['rooms' => $rooms]);
}

function createRoom($roomId, $input) {
    if (empty($roomId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Room ID required']);
        return;
    }
    
    $room = [
        'participants' => [],
        'createdAt' => time(),
        'lastActivity' => time(),
        'settings' => $input['settings'] ?? []
    ];
    
    saveRoom($roomId, $room);
    
    echo json_encode([
        'success' => true,
        'roomId' => $roomId,
        'room' => $room
    ]);
}

function joinRoom($roomId, $input) {
    if (empty($roomId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Room ID required']);
        return;
    }
    
    $participantId = $input['participantId'] ?? $_SESSION['UserID'];
    $participantRole = $input['role'] ?? 'trainee';
    
    if (empty($participantId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Participant ID required']);
        return;
    }
    
    $room = loadRoom($roomId);
    
    $room['participants'][$participantId] = [
        'role' => $participantRole,
        'joinedAt' => time(),
        'lastSeen' => time(),
        'status' => 'active'
    ];
    
    saveRoom($roomId, $room);
    
    echo json_encode([
        'success' => true,
        'participantId' => $participantId,
        'room' => $room
    ]);
}

function updateParticipant($roomId, $input) {
    if (empty($roomId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Room ID required']);
        return;
    }
    
    $participantId = $input['participantId'] ?? $_SESSION['UserID'];
    
    if (empty($participantId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Participant ID required']);
        return;
    }
    
    $room = loadRoom($roomId);
    
    if (isset($room['participants'][$participantId])) {
        $room['participants'][$participantId]['lastSeen'] = time();
        
        if (isset($input['status'])) {
            $room['participants'][$participantId]['status'] = $input['status'];
        }
        
        saveRoom($roomId, $room);
        
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Participant not found in room']);
    }
}

function leaveRoom($roomId) {
    if (empty($roomId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Room ID required']);
        return;
    }
    
    $participantId = $_SESSION['UserID'];
    $room = loadRoom($roomId);
    
    if (isset($room['participants'][$participantId])) {
        unset($room['participants'][$participantId]);
        
        if (empty($room['participants'])) {
            // Delete empty room
            $roomFile = dirname(__FILE__) . '/Signals/room_' . $roomId . '.json';
            if (file_exists($roomFile)) {
                unlink($roomFile);
            }
        } else {
            saveRoom($roomId, $room);
        }
        
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Participant not found in room']);
    }
}

function closeRoom($roomId) {
    if (empty($roomId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Room ID required']);
        return;
    }
    
    // Only allow trainers to close rooms
    $room = loadRoom($roomId);
    $participantId = $_SESSION['UserID'];
    
    if (!isset($room['participants'][$participantId]) || 
        $room['participants'][$participantId]['role'] !== 'trainer') {
        http_response_code(403);
        echo json_encode(['error' => 'Only trainers can close rooms']);
        return;
    }
    
    // Delete room file
    $roomFile = dirname(__FILE__) . '/Signals/room_' . $roomId . '.json';
    if (file_exists($roomFile)) {
        unlink($roomFile);
    }
    
    // Clean up participant files
    $signalsDir = dirname(__FILE__) . '/Signals/';
    foreach ($room['participants'] as $pid => $participant) {
        $participantFile = $signalsDir . 'participant_' . $pid . '.txt';
        if (file_exists($participantFile)) {
            unlink($participantFile);
        }
    }
    
    echo json_encode(['success' => true]);
}

function loadRoom($roomId) {
    $roomFile = dirname(__FILE__) . '/Signals/room_' . $roomId . '.json';
    
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

function saveRoom($roomId, $room) {
    $room['lastActivity'] = time();
    $roomFile = dirname(__FILE__) . '/Signals/room_' . $roomId . '.json';
    file_put_contents($roomFile, json_encode($room, JSON_PRETTY_PRINT));
}

?>