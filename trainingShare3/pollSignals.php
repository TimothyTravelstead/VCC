<?php
// NO AUTHENTICATION - DEVELOPMENT MODE
// Get participant info directly from URL parameters
$participantId = $_GET['participantId'] ?? 'test_participant';
$participantRole = $_GET['role'] ?? 'trainee';

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Path to participant's signal file
$participantFile = dirname(__FILE__) . '/Signals/participant_' . $participantId . '.txt';

$response = [
    'participantId' => $participantId,
    'role' => $participantRole,
    'timestamp' => microtime(true),
    'messages' => []
];

// Check for new messages
if (file_exists($participantFile) && filesize($participantFile) > 0) {
    $content = file_get_contents($participantFile);
    
    if (!empty($content)) {
        // Handle multiple events
        if (strpos($content, '_MULTIPLEVENTS_') !== false) {
            $events = explode('_MULTIPLEVENTS_', $content);
            foreach ($events as $eventData) {
                if (!empty(trim($eventData))) {
                    try {
                        $message = json_decode($eventData, true);
                        if ($message) {
                            $response['messages'][] = $message;
                        }
                    } catch (Exception $e) {
                        error_log("POLL SIGNALS: Invalid JSON in signal: " . $eventData);
                    }
                }
            }
        } else {
            try {
                $message = json_decode($content, true);
                if ($message) {
                    $response['messages'][] = $message;
                }
            } catch (Exception $e) {
                error_log("POLL SIGNALS: Invalid JSON in signal: " . $content);
            }
        }
        
        // Clear the file after reading
        file_put_contents($participantFile, '');
        
        error_log("POLL SIGNALS: Delivered " . count($response['messages']) . " messages to $participantId");
    }
}

// Send response
echo json_encode($response);
?>