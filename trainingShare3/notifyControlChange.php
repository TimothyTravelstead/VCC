<?php
// Notify participants about control changes
header('Content-Type: application/json');

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$trainerId = $input['trainerId'] ?? '';
$activeController = $input['activeController'] ?? '';
$controllerRole = $input['controllerRole'] ?? '';
$trainees = $input['trainees'] ?? [];

if (empty($trainerId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Trainer ID required']);
    exit;
}

try {
    // Create list of all participants (trainer + trainees)
    $allParticipants = array_merge([$trainerId], $trainees);
    
    // Send notification to all participants
    foreach ($allParticipants as $participantId) {
        $controlNotification = [
            'type' => 'control-change',
            'activeController' => $activeController,
            'controllerRole' => $controllerRole,
            'trainerId' => $trainerId,
            'timestamp' => microtime(true)
        ];
        
        $participantFile = __DIR__ . '/Signals/participant_' . $participantId . '.txt';
        
        // Append to existing signals
        $existingData = '';
        if (file_exists($participantFile)) {
            $existingData = file_get_contents($participantFile);
        }
        
        $newData = json_encode($controlNotification) . "_MULTIPLEVENTS_" . $existingData;
        file_put_contents($participantFile, $newData);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Notified ' . count($allParticipants) . ' participants of control change',
        'activeController' => $activeController,
        'controllerRole' => $controllerRole
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to notify control change: ' . $e->getMessage()]);
}
?>