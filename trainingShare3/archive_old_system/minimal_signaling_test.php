<?php
/**
 * Minimal PHP WebRTC Signaling Test
 * Just stores and retrieves messages
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$messagesFile = __DIR__ . '/test_messages.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store a message
    $input = file_get_contents('php://input');
    $message = json_decode($input, true);
    
    if ($message) {
        $message['timestamp'] = time();
        
        // Read existing messages
        $messages = [];
        if (file_exists($messagesFile)) {
            $existing = file_get_contents($messagesFile);
            $messages = json_decode($existing, true) ?: [];
        }
        
        // Add new message
        $messages[] = $message;
        
        // Keep only last 50 messages
        if (count($messages) > 50) {
            $messages = array_slice($messages, -50);
        }
        
        // Save messages
        file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT));
        
        echo json_encode(['status' => 'stored', 'id' => count($messages)]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'poll';
    
    if ($action === 'check-caller') {
        // Check if caller is active (has sent messages recently)
        $messages = [];
        if (file_exists($messagesFile)) {
            $existing = file_get_contents($messagesFile);
            $messages = json_decode($existing, true) ?: [];
        }
        
        $now = time();
        $callerActive = false;
        
        // Look for recent messages from caller (within last 60 seconds)
        foreach ($messages as $msg) {
            if ($msg['from'] === 'caller' && ($now - $msg['timestamp']) < 60) {
                $callerActive = true;
                break;
            }
        }
        
        echo json_encode(['callerActive' => $callerActive]);
        
    } else {
        // Regular message polling
        $to = $_GET['to'] ?? '';
        $since = (int)($_GET['since'] ?? 0);
        
        if (empty($to)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing "to" parameter']);
            exit;
        }
        
        // Read messages
        $messages = [];
        if (file_exists($messagesFile)) {
            $existing = file_get_contents($messagesFile);
            $messages = json_decode($existing, true) ?: [];
        }
        
        // Filter messages for this recipient since timestamp
        $filtered = array_filter($messages, function($msg) use ($to, $since) {
            return ($msg['to'] === $to) && ($msg['timestamp'] > $since);
        });
        
        echo json_encode(['messages' => array_values($filtered)]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>