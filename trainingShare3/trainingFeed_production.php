<?php
session_start();
set_time_limit(7300);

// Session validation
if (!isset($_SESSION['auth']) || $_SESSION['auth'] != 'yes') {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: text/plain');
    die("Unauthorized");
}

$participantId = $_SESSION['UserID'] ?? '';
if (empty($participantId)) {
    header('HTTP/1.1 403 Forbidden'); 
    header('Content-Type: text/plain');
    die("No participant ID");
}

// Determine role from session data
$participantRole = 'trainer'; // Default
if ((isset($_SESSION['trainer']) && $_SESSION['trainer'] == '1') ||
    (isset($_SESSION['LoggedOn']) && $_SESSION['LoggedOn'] == 4) ||
    (isset($_SESSION['Admin']) && $_SESSION['Admin'] != '')) {
    $participantRole = 'trainer';
} elseif ((isset($_SESSION['trainee']) && $_SESSION['trainee'] == '1') || 
          (isset($_SESSION['LoggedOn']) && $_SESSION['LoggedOn'] == 6)) {
    $participantRole = 'trainee';
}

// Close session to prevent blocking
session_write_close();

// EventSource headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

echo "retry: 500\n";

// CRITICAL: Send initial connection message IMMEDIATELY
$initialData = [
    'type' => 'training-connection-established',
    'participantId' => $participantId,
    'role' => $participantRole,
    'timestamp' => microtime(true)
];

echo "event: trainingConnection\n";
echo "data: " . json_encode($initialData) . "\n\n";

// Force immediate delivery to browser
if (ob_get_level()) {
    ob_end_flush();
}
flush();

// Log successful connection
error_log("TRAINING FEED: Connected - $participantId (role: $participantRole)");

$count = 0;
$participantFile = dirname(__FILE__) . '/Signals/participant_' . $participantId . '.txt';

// Main event loop - now safe to enter infinite loop since initial message was sent
while($count < 3600) { // 30 minutes max (3600 * 0.5s)
    if(connection_aborted()) {
        error_log("TRAINING FEED: Connection aborted for $participantId");
        exit();
    }

    // Check for new messages in participant file
    if (file_exists($participantFile) && filesize($participantFile) > 0) {
        $content = file_get_contents($participantFile);
        
        if (!empty($content)) {
            // Process signals
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
    
    // Send heartbeat every 30 seconds
    if ($count % 60 === 0 && $count > 0) { // Skip first heartbeat since we just sent connection
        $heartbeat = [
            'type' => 'training-heartbeat',
            'participantId' => $participantId,
            'role' => $participantRole,
            'timestamp' => microtime(true),
            'count' => $count
        ];
        
        echo "event: trainingHeartbeat\n";
        echo "data: " . json_encode($heartbeat) . "\n\n";
        flush();
    }
    
    // Clean up old signal files periodically
    if ($count % 240 === 0 && $count > 0) { // Every 2 minutes
        cleanupStaleSignals();
    }
    
    // 500ms polling interval
    usleep(500000);
    $count++;
}

// Connection timeout
error_log("TRAINING FEED: Timeout reached for $participantId after " . ($count * 0.5) . " seconds");

function sendTrainingEvent($eventData, $participantId) {
    $decoded = json_decode($eventData, true);
    if ($decoded === null) {
        error_log("TRAINING FEED: Invalid JSON in signal for $participantId: $eventData");
        return;
    }
    
    // Add delivery metadata
    $decoded['recipient'] = $participantId;
    $decoded['deliveredAt'] = microtime(true);
    
    // Determine appropriate event type
    $eventType = 'trainingSignal'; // Default
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
    }
    
    // Log and send
    $messageType = $decoded['type'] ?? 'unknown';
    $senderId = $decoded['senderId'] ?? $decoded['from'] ?? 'unknown';
    error_log("TRAINING FEED: Delivering '$messageType' from '$senderId' to '$participantId'");
    
    echo "event: $eventType\n";
    echo "data: " . json_encode($decoded) . "\n\n";
    flush();
}

function cleanupStaleSignals() {
    $signalsDir = dirname(__FILE__) . '/Signals/';
    if (!is_dir($signalsDir)) {
        return;
    }
    
    $cutoffTime = time() - 3600; // 1 hour ago
    $cleanedCount = 0;
    
    // Clean empty participant files
    $files = glob($signalsDir . 'participant_*.txt');
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime && filesize($file) === 0) {
            unlink($file);
            $cleanedCount++;
        }
    }
    
    // Clean old room files
    $roomFiles = glob($signalsDir . 'room_*.json');
    foreach ($roomFiles as $file) {
        if (filemtime($file) < $cutoffTime) {
            $roomData = json_decode(file_get_contents($file), true);
            if ($roomData && ($roomData['lastActivity'] ?? 0) < $cutoffTime) {
                unlink($file);
                $cleanedCount++;
            }
        }
    }
    
    if ($cleanedCount > 0) {
        error_log("TRAINING FEED: Cleaned up $cleanedCount stale files");
    }
}
?>