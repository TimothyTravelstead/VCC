<?php
// NO AUTHENTICATION - DEVELOPMENT MODE
error_log("TRAINING FEED: Script starting - " . date('Y-m-d H:i:s'));
set_time_limit(30);

// Include the database connection for role validation
try {
    require_once('../private_html/db_login.php');
    error_log("TRAINING FEED: Database connection included successfully");
} catch (Exception $e) {
    error_log("TRAINING FEED ERROR: Database include failed - " . $e->getMessage());
    // Don't die, just log and continue with URL parameters
}

$participantId = $_GET['participantId'] ?? 'test_participant';
$participantRole = $_GET['role'] ?? 'trainee';

// If we have database access, validate the role
if (function_exists('dataQuery')) {
    try {
        error_log("TRAINING FEED: Attempting database role validation for $participantId");
        // Simple query to avoid hanging
        $participantRole = $participantRole; // Keep URL parameter for now
        error_log("TRAINING FEED: Database available, using role: $participantRole");
    } catch (Exception $e) {
        error_log("TRAINING FEED ERROR: Database query failed - " . $e->getMessage());
        error_log("TRAINING FEED: Falling back to URL parameter role: $participantRole");
    }
} else {
    error_log("TRAINING FEED: No database function available, using URL parameter role: $participantRole");
}

// No session to close in development mode

// Server Sent Events Headers
error_log("TRAINING FEED: About to send headers");
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
error_log("TRAINING FEED: Headers sent");

// Immediate debug output to help with connection troubleshooting
error_log("TRAINING FEED: Headers sent for participant $participantId");

// Set retry interval for training (faster than main chat)
echo "retry: 500\n";

// Send an immediate ping to establish connection
echo "data: TRAINING_FEED_CONNECTED\n\n";
ob_flush();
flush();

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

// Main event loop - optimized for training system (TEMPORARY: shorter timeout for testing)
while($count < 60) { // Shorter timeout for testing (30 seconds)
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
    
    // Check for stale participant files and clean up
    if ($count % 240 === 0) { // Every 2 minutes
        cleanupStaleSignals();
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

function cleanupStaleSignals() {
    $signalsDir = dirname(__FILE__) . '/Signals/';
    if (!is_dir($signalsDir)) {
        return;
    }
    
    $files = glob($signalsDir . 'participant_*.txt');
    $cutoffTime = time() - 3600; // 1 hour ago
    $cleanedCount = 0;
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime && filesize($file) === 0) {
            unlink($file);
            $cleanedCount++;
        }
    }
    
    if ($cleanedCount > 0) {
        error_log("TRAINING FEED: Cleaned up $cleanedCount stale signal files");
    }
    
    // Also clean up old room files
    $roomFiles = glob($signalsDir . 'room_*.json');
    foreach ($roomFiles as $file) {
        if (filemtime($file) < $cutoffTime) {
            $roomData = json_decode(file_get_contents($file), true);
            // Only delete if no recent activity
            if ($roomData && ($roomData['lastActivity'] ?? 0) < $cutoffTime) {
                unlink($file);
                error_log("TRAINING FEED: Cleaned up stale room file: " . basename($file));
            }
        }
    }
}

?>