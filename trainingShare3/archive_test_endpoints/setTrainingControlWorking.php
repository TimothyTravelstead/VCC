<?php
// Set training control using the working pattern from testControlChange.php
header('Content-Type: application/json');
require_once '../../private_html/db_login.php';

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$trainerId = $input['trainerId'] ?? '';
$activeController = $input['activeController'] ?? '';
$controllerRole = $input['controllerRole'] ?? '';

if (empty($trainerId) || empty($activeController) || empty($controllerRole)) {
    echo json_encode(['error' => 'Trainer ID, active controller, and controller role required']);
    exit;
}

// Validate controller role
if (!in_array($controllerRole, ['trainer', 'trainee'])) {
    echo json_encode(['error' => 'Controller role must be trainer or trainee']);
    exit;
}

try {
    // Set new controller (using exact same pattern as testControlChange.php)
    $role = ($activeController === $trainerId) ? 'trainer' : 'trainee';
    $query = "INSERT INTO training_session_control (trainer_id, active_controller, controller_role) 
              VALUES (?, ?, ?) 
              ON DUPLICATE KEY UPDATE 
              active_controller = VALUES(active_controller),
              controller_role = VALUES(controller_role)";
    
    $result = dataQuery($query, [$trainerId, $activeController, $role]);
    echo json_encode([
        'success' => true,
        'trainerId' => $trainerId,
        'activeController' => $activeController,
        'controllerRole' => $role,
        'timestamp' => time(),
        'message' => 'Training control updated successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to set training control: ' . $e->getMessage()]);
}
?>