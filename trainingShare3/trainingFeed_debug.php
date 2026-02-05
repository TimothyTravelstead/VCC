<?php
session_start();

// Session validation (copy from working test)
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

// Determine role
$participantRole = 'trainer'; // Default for testing

// Close session
session_write_close();

// EventSource headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

echo "retry: 500\n";

// Send initial connection
$initialData = [
    'type' => 'training-connection-established',
    'participantId' => $participantId,
    'role' => $participantRole,
    'timestamp' => microtime(true)
];

echo "event: trainingConnection\n";
echo "data: " . json_encode($initialData) . "\n\n";

if (ob_get_level()) {
    ob_end_flush();
}
flush();

// Send a few more messages then exit (no infinite loop)
for ($i = 1; $i <= 3; $i++) {
    sleep(1);
    
    $heartbeat = [
        'type' => 'training-heartbeat',
        'participantId' => $participantId,
        'role' => $participantRole,
        'count' => $i,
        'timestamp' => microtime(true)
    ];
    
    echo "event: trainingHeartbeat\n";
    echo "data: " . json_encode($heartbeat) . "\n\n";
    flush();
}

echo "data: Debug feed completed\n\n";
flush();
?>