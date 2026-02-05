<?php
// Notify participants to reconnect to new conference
// This could use database, WebSocket, or polling mechanism

header('Content-Type: application/json');

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$trainerId = $input['trainerId'] ?? '';
$activeController = $input['activeController'] ?? '';
$newConferenceId = $input['newConferenceId'] ?? '';
$participants = $input['participants'] ?? []; // All participants except active controller

if (empty($trainerId) || empty($newConferenceId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Trainer ID and new conference ID required']);
    exit;
}

try {
    // Create notification file for participants to poll
    $notificationData = [
        'action' => 'conference_restart',
        'trainerId' => $trainerId,
        'activeController' => $activeController,
        'newConferenceId' => $newConferenceId,
        'timestamp' => time(),
        'participants' => $participants
    ];
    
    // Write notification to file that participants can poll
    $notificationFile = __DIR__ . '/Signals/conference_restart_' . $trainerId . '.json';
    file_put_contents($notificationFile, json_encode($notificationData));
    
    // Also write individual notifications for each participant
    foreach ($participants as $participantId) {
        $participantNotification = [
            'type' => 'conference-restart',
            'trainerId' => $trainerId,
            'activeController' => $activeController,
            'newConferenceId' => $newConferenceId,
            'timestamp' => microtime(true)
        ];
        
        $participantFile = __DIR__ . '/Signals/participant_' . $participantId . '.txt';
        
        // Append to existing signals
        $existingData = '';
        if (file_exists($participantFile)) {
            $existingData = file_get_contents($participantFile);
        }
        
        $newData = json_encode($participantNotification) . "_MULTIPLEVENTS_" . $existingData;
        file_put_contents($participantFile, $newData);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Notified ' . count($participants) . ' participants to reconnect',
        'activeController' => $activeController,
        'newConferenceId' => $newConferenceId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to notify trainees: ' . $e->getMessage()]);
}
?>