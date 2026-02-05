<?php
// Test script to manually change control
header('Content-Type: application/json');
require_once '../../private_html/db_login.php';

$trainerId = $_GET['trainerId'] ?? '';

if (empty($trainerId)) {
    echo json_encode(['error' => 'Trainer ID required']);
    exit;
}

try {
    // Get current control
    $result = dataQuery("SELECT * FROM training_session_control WHERE trainer_id = ?", [$trainerId]);
    if ($result && is_array($result) && count($result) > 0) {
        $control = $result[0];
        echo json_encode([
            'success' => true,
            'trainerId' => $control['trainer_id'],
            'activeController' => $control['active_controller'],
            'controllerRole' => $control['controller_role'],
            'lastUpdated' => $control['last_updated'],
            'timestamp' => time()
        ]);
    } else {
        // No control record exists, create default
        $insertQuery = "INSERT INTO training_session_control (trainer_id, active_controller, controller_role) 
                        VALUES (?, ?, 'trainer')";
        dataQuery($insertQuery, [$trainerId, $trainerId]);
        
        echo json_encode([
            'success' => true,
            'trainerId' => $trainerId,
            'activeController' => $trainerId,
            'controllerRole' => 'trainer',
            'lastUpdated' => date('Y-m-d H:i:s'),
            'timestamp' => time(),
            'created' => true
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>