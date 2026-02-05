<?php
// Clear conference notification file
header('Content-Type: application/json');

$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    echo json_encode(['error' => 'Filename required']);
    exit;
}

// Sanitize filename
$filename = basename($filename);
$filepath = __DIR__ . '/Signals/' . $filename;

if (file_exists($filepath)) {
    if (unlink($filepath)) {
        echo json_encode(['success' => true, 'message' => 'Notification cleared']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to clear notification']);
    }
} else {
    echo json_encode(['success' => true, 'message' => 'Notification already cleared']);
}
?>