<?php
header('Content-Type: application/json');
require_once '../../private_html/db_login.php';

$trainerId = $_GET['trainerId'] ?? '';

if (empty($trainerId)) {
    echo json_encode(['error' => 'Trainer ID required']);
    exit;
}

try {
    // Get current control from database
    $result = dataQuery("SELECT trainer_id, active_controller, controller_role, last_updated FROM training_session_control WHERE trainer_id = ?", [$trainerId]);
    
    if ($result && is_array($result) && count($result) > 0) {
        $control = $result[0];
        echo json_encode([
            'success' => true,
            'trainerId' => $control['trainer_id'],
            'activeController' => $control['active_controller'],
            'controllerRole' => $control['controller_role'],
            'lastUpdated' => $control['last_updated'],
            'timestamp' => time(),
            'source' => 'database'
        ]);
    } else {
        // No record exists, return trainer as default controller
        echo json_encode([
            'success' => true,
            'trainerId' => $trainerId,
            'activeController' => $trainerId,
            'controllerRole' => 'trainer',
            'lastUpdated' => date('Y-m-d H:i:s'),
            'timestamp' => time(),
            'source' => 'default'
        ]);
    }
    
} catch (Exception $e) {
    // Fallback to default on error
    echo json_encode([
        'success' => true,
        'trainerId' => $trainerId,
        'activeController' => $trainerId,
        'controllerRole' => 'trainer',
        'lastUpdated' => date('Y-m-d H:i:s'),
        'timestamp' => time(),
        'source' => 'fallback',
        'error' => $e->getMessage()
    ]);
}
?>