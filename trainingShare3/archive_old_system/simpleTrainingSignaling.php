<?php
// Simple training signaling server - no authentication required
// Based on minimal_signaling_test.php that worked perfectly

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$messagesFile = __DIR__ . '/training_messages.json';

// Initialize messages file if it doesn't exist
if (!file_exists($messagesFile)) {
    file_put_contents($messagesFile, json_encode([]));
}

// Clean old messages (older than 5 minutes)
function cleanOldMessages($messages) {
    $now = time();
    return array_filter($messages, function($msg) use ($now) {
        return ($now - $msg['timestamp']) < 300;
    });
}

// Read messages
function readMessages() {
    global $messagesFile;
    $content = file_get_contents($messagesFile);
    $messages = json_decode($content, true) ?: [];
    return cleanOldMessages($messages);
}

// Write messages
function writeMessages($messages) {
    global $messagesFile;
    file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle incoming message
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['type'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid message']);
        exit();
    }
    
    // Add timestamp and store message
    $input['timestamp'] = time();
    $input['id'] = uniqid();
    
    $messages = readMessages();
    $messages[] = $input;
    writeMessages($messages);
    
    echo json_encode(['status' => 'success', 'id' => $input['id']]);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'poll';
    
    if ($action === 'check-trainer') {
        // Check if trainer is active (has sent messages recently)
        $messages = readMessages();
        $now = time();
        $trainerActive = false;
        
        foreach ($messages as $msg) {
            if ($msg['from'] === 'trainer' && ($now - $msg['timestamp']) < 60) {
                $trainerActive = true;
                break;
            }
        }
        
        echo json_encode(['trainerActive' => $trainerActive]);
        
    } else {
        // Poll for messages
        $to = $_GET['to'] ?? null;
        $since = (int)($_GET['since'] ?? 0);
        
        $messages = readMessages();
        
        // Filter messages
        $filtered = array_filter($messages, function($msg) use ($to, $since) {
            $toMatch = ($to === null) || ($msg['to'] === $to) || ($msg['to'] === 'broadcast');
            $sinceMatch = $msg['timestamp'] > $since;
            return $toMatch && $sinceMatch;
        });
        
        // Re-index array
        $filtered = array_values($filtered);
        
        echo json_encode(['messages' => $filtered]);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
?>