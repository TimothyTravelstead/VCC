<?php
// Setup script for training control table
require_once '../../private_html/db_login.php';

try {
    // Create the training control table using dataQuery
    $sql = "
    CREATE TABLE IF NOT EXISTS training_session_control (
        id INT AUTO_INCREMENT PRIMARY KEY,
        trainer_id VARCHAR(50) NOT NULL,
        active_controller VARCHAR(50) NOT NULL,
        controller_role ENUM('trainer', 'trainee') NOT NULL,
        session_start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        UNIQUE KEY unique_trainer_session (trainer_id),
        INDEX idx_trainer_controller (trainer_id, active_controller),
        INDEX idx_last_updated (last_updated)
    )";
    
    $result = dataQuery($sql);
    echo "Training control table created successfully\n";
    
    // Initialize with current active trainers having control
    $initSql = "
    INSERT IGNORE INTO training_session_control (trainer_id, active_controller, controller_role)
    SELECT 
        UserName as trainer_id,
        UserName as active_controller,
        'trainer' as controller_role
    FROM volunteers 
    WHERE AdminLoggedOn = 4 
    AND trainer = 1";
    
    $initResult = dataQuery($initSql);
    echo "Initialized training sessions for active trainers\n";
    
    // Show current sessions
    $sessions = dataQuery("SELECT * FROM training_session_control");
    if ($sessions) {
        echo "Current training sessions:\n";
        foreach ($sessions as $session) {
            echo "- Trainer: {$session['trainer_id']}, Controller: {$session['active_controller']} ({$session['controller_role']})\n";
        }
    } else {
        echo "No active training sessions found\n";
    }
    
} catch (Exception $e) {
    echo "Error setting up training control table: " . $e->getMessage() . "\n";
}
?>