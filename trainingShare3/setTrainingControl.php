<?php
// Set training session control (only trainers can change control)

require_once '../../private_html/db_login.php';
session_start();
header('Content-Type: application/json');

// Load database connection

// Verify user is logged in and is a trainer
if (!isset($_SESSION['trainer']) || $_SESSION['trainer'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Only trainers can change control']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$trainerId = $input['trainerId'] ?? '';
$activeController = $input['activeController'] ?? '';
$controllerRole = $input['controllerRole'] ?? '';

// Verify the trainer ID matches the logged-in trainer
if ($trainerId !== $_SESSION['UserID']) {
    http_response_code(403);
    echo json_encode(['error' => 'You can only change control for your own training session']);
    exit;
}

if (empty($trainerId) || empty($activeController) || empty($controllerRole)) {
    http_response_code(400);
    echo json_encode(['error' => 'Trainer ID, active controller, and controller role required']);
    exit;
}

// Validate controller role
if (!in_array($controllerRole, ['trainer', 'trainee'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Controller role must be trainer or trainee']);
    exit;
}

// Validate trainee is actually assigned to this trainer and is logged in
if ($controllerRole === 'trainee') {
    // Check trainee exists, is assigned to this trainer, and is logged in as trainee
    $validateTrainee = "SELECT v.UserName, v.LoggedOn, t.TraineeID
                        FROM volunteers v
                        JOIN volunteers t ON t.UserName = ?
                        WHERE v.UserName = ?
                        AND FIND_IN_SET(v.UserName, t.TraineeID) > 0
                        AND v.LoggedOn = 6";
    $validationResult = dataQuery($validateTrainee, [$trainerId, $activeController]);

    if (!$validationResult || count($validationResult) === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid trainee: not assigned to this trainer or not logged in as trainee']);
        exit;
    }
} elseif ($controllerRole === 'trainer' && $activeController !== $trainerId) {
    // If giving control to trainer, it must be this trainer
    http_response_code(400);
    echo json_encode(['error' => 'Can only transfer control to yourself as trainer']);
    exit;
}

try {
    // Update or insert control record for this training session
    $query = "INSERT INTO training_session_control (trainer_id, active_controller, controller_role)
              VALUES (?, ?, ?)
              ON DUPLICATE KEY UPDATE
              active_controller = VALUES(active_controller),
              controller_role = VALUES(controller_role),
              last_updated = CURRENT_TIMESTAMP";

    $result = dataQuery($query, [$trainerId, $activeController, $controllerRole]);

    // Get all participants in this training session
    $getTraineesQuery = "SELECT TraineeID FROM volunteers WHERE UserName = ?";
    $traineeResult = dataQuery($getTraineesQuery, [$trainerId]);

    $participants = [$trainerId]; // Start with the trainer

    if ($traineeResult && count($traineeResult) > 0 && !empty($traineeResult[0]->TraineeID)) {
        // Parse comma-separated trainee list
        $traineeIds = array_map('trim', explode(',', $traineeResult[0]->TraineeID));
        $participants = array_merge($participants, $traineeIds);
    }

    // Get list of other participants (excluding the active controller)
    $otherParticipants = array_filter($participants, fn($p) => $p !== $activeController);

    // === CALLCONTROL TABLE MANAGEMENT ===

    // Get the controller's actual LoggedOn status (trainer=4, trainee=6)
    $getStatus = "SELECT LoggedOn FROM volunteers WHERE UserName = ?";
    $statusResult = dataQuery($getStatus, [$activeController]);
    $controllerStatus = ($statusResult && count($statusResult) > 0)
        ? $statusResult[0]->LoggedOn
        : 4;  // Default to 4 (trainer) if not found

    // Add the new controller to CallControl with their ACTUAL status
    $addController = "INSERT INTO CallControl (user_id, logged_on_status, can_receive_calls, can_receive_chats)
                      VALUES (?, ?, 1, 1)
                      ON DUPLICATE KEY UPDATE
                      logged_on_status = VALUES(logged_on_status),
                      can_receive_calls = 1,
                      can_receive_chats = 1";
    dataQuery($addController, [$activeController, $controllerStatus]);

    // Remove all other training participants from CallControl
    // They cannot receive calls or chats while not in control
    if (count($otherParticipants) > 0) {
        $placeholders = implode(',', array_fill(0, count($otherParticipants), '?'));
        dataQuery("DELETE FROM CallControl WHERE user_id IN ($placeholders)", $otherParticipants);
    }

    echo json_encode([
        'success' => true,
        'trainerId' => $trainerId,
        'activeController' => $activeController,
        'controllerRole' => $controllerRole,
        'timestamp' => time(),
        'message' => 'Training control updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to set training control: ' . $e->getMessage()]);
}
?>