<?php
// Get current training session control for a trainer
header('Content-Type: application/json');

// Load database connection
require_once '../../private_html/db_login.php';

// Get request data
$trainerId = $_GET['trainerId'] ?? '';

if (empty($trainerId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Trainer ID required']);
    exit;
}

try {
    // Get current control for this training session
    $query = "SELECT trainer_id, active_controller, controller_role, last_updated 
              FROM training_session_control 
              WHERE trainer_id = ?";
    $result = dataQuery($query, [$trainerId]);
    
    if ($result !== false && is_array($result) && count($result) > 0) {
        $control = $result[0];
        $response = [
            'success' => true,
            'trainerId' => $control->trainer_id,
            'activeController' => $control->active_controller,
            'controllerRole' => $control->controller_role,
            'lastUpdated' => $control->last_updated,
            'timestamp' => time()
        ];
    } else {
        // No control record exists, create default (trainer has control)
        $insertQuery = "INSERT INTO training_session_control (trainer_id, active_controller, controller_role) 
                        VALUES (?, ?, 'trainer')";
        dataQuery($insertQuery, [$trainerId, $trainerId]);
        
        $response = [
            'success' => true,
            'trainerId' => $trainerId,
            'activeController' => $trainerId,
            'controllerRole' => 'trainer',
            'lastUpdated' => date('Y-m-d H:i:s'),
            'timestamp' => time(),
            'created' => true
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('getTrainingControl.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get training control: ' . $e->getMessage()]);
}
?>