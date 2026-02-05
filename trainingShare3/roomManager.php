<?php
// NO AUTHENTICATION - DEVELOPMENT MODE
// Room Manager for training system - provides room status and management

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$action = $_GET['action'] ?? '';
$roomId = $_GET['roomId'] ?? '';

if (empty($action) || empty($roomId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action or roomId']);
    exit;
}

$roomFile = dirname(__FILE__) . '/Signals/room_' . $roomId . '.json';

switch ($action) {
    case 'room-status':
        if (file_exists($roomFile)) {
            $roomData = json_decode(file_get_contents($roomFile), true);
            if ($roomData) {
                echo json_encode($roomData);
            } else {
                echo json_encode(['participants' => [], 'createdAt' => time(), 'lastActivity' => time()]);
            }
        } else {
            // Return empty room if file doesn't exist
            echo json_encode(['participants' => [], 'createdAt' => time(), 'lastActivity' => time()]);
        }
        break;
        
    case 'create-room':
        $room = [
            'participants' => [],
            'createdAt' => time(),
            'lastActivity' => time()
        ];
        
        // Ensure Signals directory exists
        if (!file_exists(dirname(__FILE__) . '/Signals/')) {
            mkdir(dirname(__FILE__) . '/Signals/', 0755, true);
        }
        
        file_put_contents($roomFile, json_encode($room, JSON_PRETTY_PRINT));
        echo json_encode($room);
        break;
        
    case 'delete-room':
        if (file_exists($roomFile)) {
            unlink($roomFile);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Room not found']);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
?>