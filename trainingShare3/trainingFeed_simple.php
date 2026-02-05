<?php
session_start();
set_time_limit(7300);

// Training-specific session validation
if (!isset($_SESSION['auth']) || $_SESSION['auth'] != 'yes') {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: text/plain');
    die("Unauthorized - Training access requires authentication");
}

$participantId = $_SESSION['UserID'] ?? '';
if (empty($participantId)) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain');
    die("Forbidden - No participant ID in session");
}

// Determine role from session data (skip database for now)
$participantRole = 'trainee'; // default

if ((isset($_SESSION['trainer']) && $_SESSION['trainer'] == '1') ||
    (isset($_SESSION['LoggedOn']) && $_SESSION['LoggedOn'] == 4) ||
    (isset($_SESSION['Admin']) && $_SESSION['Admin'] != '')) {
    $participantRole = 'trainer';
} elseif ((isset($_SESSION['trainee']) && $_SESSION['trainee'] == '1') || 
          (isset($_SESSION['LoggedOn']) && $_SESSION['LoggedOn'] == 6)) {
    $participantRole = 'trainee';
} else {
    // Default to trainer for admins/testing
    $participantRole = 'trainer';
}

// Close session to prevent blocking other requests
session_write_close();

// Server Sent Events Headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Set retry interval for training (faster than main chat)
echo "retry: 500\n";

// Send initial connection confirmation
$initialData = [
    'type' => 'training-connection-established',
    'participantId' => $participantId,
    'role' => $participantRole,
    'timestamp' => microtime(true)
];

echo "event: trainingConnection\n";
echo "data: " . json_encode($initialData) . "\n\n";
ob_flush();
flush();

// Debug logging for training connections
error_log("TRAINING FEED: Connected - $participantId (role: $participantRole)");

$count = 0;
$participantFile = dirname(__FILE__) . '/Signals/participant_' . $participantId . '.txt';

// Main event loop - simplified for testing
while($count < 300) { // 5 minutes max
    if(connection_aborted()) {
        error_log("TRAINING FEED: Connection aborted for $participantId");
        exit();
    }

    // Check for new messages in participant file
    if (file_exists($participantFile) && filesize($participantFile) > 0) {
        $content = file_get_contents($participantFile);
        
        if (!empty($content)) {
            error_log("TRAINING FEED: Processing signals for $participantId - " . strlen($content) . " bytes");
            
            // Handle multiple events
            if (strpos($content, '_MULTIPLEVENTS_') !== false) {
                $events = explode('_MULTIPLEVENTS_', $content);
                foreach ($events as $eventData) {
                    if (!empty(trim($eventData))) {
                        sendTrainingEvent($eventData, $participantId);
                    }
                }
            } else {
                sendTrainingEvent($content, $participantId);
            }
            
            // Clear the file after processing
            file_put_contents($participantFile, '');
        }
    }
    
    // Send heartbeat every 30 seconds to keep connection alive
    if ($count % 60 === 0) { // Every 30 seconds (60 * 0.5s)
        $heartbeat = [
            'type' => 'training-heartbeat',
            'participantId' => $participantId,
            'role' => $participantRole,
            'timestamp' => microtime(true),
            'count' => $count
        ];
        
        echo "event: trainingHeartbeat\n";
        echo "data: " . json_encode($heartbeat) . "\n\n";
        ob_flush();
        flush();
    }
    
    // Faster polling for training system (500ms vs 2s for main chat)
    usleep(500000); // 500ms
    $count++;
}

// Connection timeout
error_log("TRAINING FEED: Timeout reached for $participantId after " . ($count * 0.5) . " seconds");

function sendTrainingEvent($eventData, $participantId) {
    // Validate JSON
    $decoded = json_decode($eventData, true);
    if ($decoded === null) {
        error_log("TRAINING FEED: Invalid JSON in signal for $participantId: $eventData");
        return;
    }
    
    // Add participant context for debugging
    $decoded['recipient'] = $participantId;
    $decoded['deliveredAt'] = microtime(true);
    
    // Determine event type for proper client handling
    $eventType = 'screenShare'; // Default event type for compatibility
    
    switch ($decoded['type'] ?? 'unknown') {
        case 'participant-joined':
        case 'participant-left':
            $eventType = 'trainingParticipant';
            break;
        case 'offer':
        case 'answer':
        case 'ice-candidate':
            $eventType = 'trainingWebRTC';
            break;
        case 'screen-share-start':
        case 'screen-share-stop':
            $eventType = 'trainingScreenShare';
            break;
        default:
            $eventType = 'trainingSignal';
    }
    
    // Log signal delivery
    $messageType = $decoded['type'] ?? 'unknown';
    $senderId = $decoded['senderId'] ?? $decoded['from'] ?? 'unknown';
    error_log("TRAINING FEED: Delivering '$messageType' from '$senderId' to '$participantId'");
    
    echo "event: $eventType\n";
    echo "data: " . json_encode($decoded) . "\n\n";
    ob_flush();
    flush();
}

?>