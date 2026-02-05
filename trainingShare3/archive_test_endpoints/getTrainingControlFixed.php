<?php
// Fixed version with better error handling
header('Content-Type: application/json');

// Start output buffering to catch any issues
ob_start();

try {
    // Load database connection
    require_once '../../private_html/db_login.php';
    
    // Get request data
    $trainerId = $_GET['trainerId'] ?? '';
    
    if (empty($trainerId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Trainer ID required']);
        exit;
    }
    
    // Get current control for this training session
    $query = "SELECT trainer_id, active_controller, controller_role, last_updated 
              FROM training_session_control 
              WHERE trainer_id = ?";
    $result = dataQuery($query, [$trainerId]);
    
    if ($result && is_array($result) && count($result) > 0) {
        $control = $result[0];
        $response = [
            'success' => true,
            'trainerId' => $control['trainer_id'],
            'activeController' => $control['active_controller'],
            'controllerRole' => $control['controller_role'],
            'lastUpdated' => $control['last_updated'],
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
    
    // Clear any unwanted output
    ob_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_clean();
    error_log('getTrainingControlFixed.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get training control: ' . $e->getMessage()]);
}

ob_end_flush();
?>