<?php
// Debug version of getTrainingControl
header('Content-Type: application/json');
require_once '../../private_html/db_login.php';

$trainerId = $_GET['trainerId'] ?? '';

try {
    echo json_encode([
        'debug' => 'step1',
        'trainerId' => $trainerId,
        'empty' => empty($trainerId)
    ]);
    
    if (empty($trainerId)) {
        echo json_encode(['error' => 'Trainer ID required']);
        exit;
    }
    
    echo json_encode(['debug' => 'step2']);
    
    // Test basic query first
    $testQuery = dataQuery("SELECT COUNT(*) as count FROM training_session_control");
    echo json_encode(['debug' => 'step3', 'count_result' => $testQuery]);
    
    // Get current control for this training session
    $query = "SELECT trainer_id, active_controller, controller_role, last_updated 
              FROM training_session_control 
              WHERE trainer_id = ?";
    $result = dataQuery($query, [$trainerId]);
    
    echo json_encode([
        'debug' => 'step4',
        'query_result' => $result,
        'is_array' => is_array($result),
        'count' => is_array($result) ? count($result) : 'not_array'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Debug error: ' . $e->getMessage()]);
}
?>