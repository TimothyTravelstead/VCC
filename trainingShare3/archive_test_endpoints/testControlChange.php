<?php
// Test script to manually change control
header('Content-Type: application/json');
require_once '../../private_html/db_login.php';

$action = $_GET['action'] ?? '';
$trainerId = $_GET['trainerId'] ?? 'Travelstead';
$newController = $_GET['newController'] ?? '';

try {
    switch ($action) {
        case 'get':
            // Get current control
            $result = dataQuery("SELECT * FROM training_session_control WHERE trainer_id = ?", [$trainerId]);
            if ($result && is_array($result) && count($result) > 0) {
                echo json_encode(['success' => true, 'data' => $result[0]]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No control record found']);
            }
            break;
            
        case 'set':
            // Set new controller
            if (empty($newController)) {
                echo json_encode(['error' => 'newController parameter required']);
                break;
            }
            
            $role = ($newController === $trainerId) ? 'trainer' : 'trainee';
            $query = "INSERT INTO training_session_control (trainer_id, active_controller, controller_role) 
                      VALUES (?, ?, ?) 
                      ON DUPLICATE KEY UPDATE 
                      active_controller = VALUES(active_controller),
                      controller_role = VALUES(controller_role)";
            
            $result = dataQuery($query, [$trainerId, $newController, $role]);
            echo json_encode(['success' => true, 'message' => "Control set to $newController ($role)"]);
            break;
            
        case 'list':
            // List all control records
            $result = dataQuery("SELECT * FROM training_session_control");
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        default:
            echo json_encode([
                'error' => 'Invalid action',
                'usage' => [
                    'get' => '?action=get&trainerId=Travelstead',
                    'set' => '?action=set&trainerId=Travelstead&newController=TimTesting',
                    'list' => '?action=list'
                ]
            ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>