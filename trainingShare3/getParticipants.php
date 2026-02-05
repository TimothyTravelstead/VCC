<?php
// Get complete participant list for a training session
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
    $participants = [];
    
    // Get current control status from training_session_control table
    // This replaces the legacy Muted field for determining who has control
    $controlQuery = "SELECT active_controller FROM training_session_control WHERE trainer_id = ?";
    $controlResult = dataQuery($controlQuery, [$trainerId]);
    $activeController = null;
    if ($controlResult !== false && is_array($controlResult) && count($controlResult) > 0) {
        $activeController = $controlResult[0]->active_controller;
    }
    
    // Get trainer info
    // NOTE: Muted field still selected but no longer used for training control (legacy field retained for DB compatibility)
    $trainerQuery = "SELECT UserName, FirstName, LastName, LoggedOn, Muted, TraineeID FROM volunteers WHERE UserName = ?";
    $trainerResult = dataQuery($trainerQuery, [$trainerId]);
    
    if ($trainerResult !== false && is_array($trainerResult) && count($trainerResult) > 0) {
        $trainer = $trainerResult[0];
        $participants[] = [
            'id' => $trainer->UserName ?: $trainerId, // Use passed trainerId if UserName is null
            'name' => trim(($trainer->FirstName ?: '') . ' ' . ($trainer->LastName ?: '')),
            'role' => 'trainer',
            'isSignedOn' => in_array($trainer->LoggedOn, [4, 7]), // 4=trainer, 7=trainer+admin
            'hasControl' => ($activeController === ($trainer->UserName ?: $trainerId))
            // 'muted' field removed - now using hasControl from training_session_control table
        ];
        
        // Get assigned trainees for this trainer
        // Method 1: Check TraineeID field for explicitly assigned trainees
        if (!empty($trainer->TraineeID)) {
            $traineeIds = array_map('trim', explode(',', $trainer->TraineeID));
            
            foreach ($traineeIds as $traineeId) {
                if (empty($traineeId)) continue;
                
                // NOTE: Muted field still selected but no longer used for training control (legacy field retained for DB compatibility)
                $traineeQuery = "SELECT UserName, FirstName, LastName, LoggedOn, Muted FROM volunteers WHERE UserName = ?";
                $traineeResult = dataQuery($traineeQuery, [$traineeId]);
                
                if ($traineeResult !== false && is_array($traineeResult) && count($traineeResult) > 0) {
                    $trainee = $traineeResult[0];
                    $participants[] = [
                        'id' => $trainee->UserName,
                        'name' => trim($trainee->FirstName . ' ' . $trainee->LastName),
                        'role' => 'trainee',
                        'isSignedOn' => in_array($trainee->LoggedOn, [6, 7]), // 6=trainee, 7=trainee+admin
                        'hasControl' => ($activeController === $trainee->UserName)
                        // 'muted' field removed - now using hasControl from training_session_control table
                    ];
                }
            }
        }
        
        // Method 2: Also look for trainees who have joined this trainer's session
        // Find trainees with LoggedOn=6 (trainee mode) who might be in training with this trainer
        // NOTE: Muted field still selected but no longer used for training control (legacy field retained for DB compatibility)
        $activeTraineesQuery = "SELECT UserName, FirstName, LastName, LoggedOn, Muted FROM volunteers WHERE LoggedOn = 6";
        $activeTraineesResult = dataQuery($activeTraineesQuery, []);
        
        if ($activeTraineesResult) {
            foreach ($activeTraineesResult as $trainee) {
                // Check if this trainee is already in our participants list
                $alreadyAdded = false;
                foreach ($participants as $participant) {
                    if ($participant['id'] === $trainee->UserName && $participant['role'] === 'trainee') {
                        $alreadyAdded = true;
                        break;
                    }
                }
                
                // Add trainee if not already added
                if (!$alreadyAdded) {
                    $participants[] = [
                        'id' => $trainee->UserName,
                        'name' => trim($trainee->FirstName . ' ' . $trainee->LastName),
                        'role' => 'trainee',
                        'isSignedOn' => true, // They have LoggedOn=6 so they're signed on
                        'hasControl' => ($activeController === $trainee->UserName)
                        // 'muted' field removed - now using hasControl from training_session_control table
                    ];
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'trainerId' => $trainerId,
        'participants' => $participants,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get participants: ' . $e->getMessage()]);
}
?>