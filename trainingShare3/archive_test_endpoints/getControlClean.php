<?php
header('Content-Type: application/json');
require_once '../../private_html/db_login.php';

$trainerId = $_GET['trainerId'] ?? '';

if (empty($trainerId)) {
    echo json_encode(['error' => 'Trainer ID required']);
    exit;
}

try {
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