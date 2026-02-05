<?php
// Simple table creation script
require_once '../../private_html/db_login.php';

echo "Creating training_session_control table...\n";

try {
    $sql = "CREATE TABLE training_session_control (
        id INT AUTO_INCREMENT PRIMARY KEY,
        trainer_id VARCHAR(50) NOT NULL,
        active_controller VARCHAR(50) NOT NULL,
        controller_role ENUM('trainer', 'trainee') NOT NULL,
        session_start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_trainer_session (trainer_id)
    )";
    
    $result = dataQuery($sql);
    echo "Table created successfully!\n";
    
    // Insert default record for current trainer
    $insertSql = "INSERT INTO training_session_control (trainer_id, active_controller, controller_role) 
                  VALUES ('Travelstead', 'Travelstead', 'trainer')";
    $insertResult = dataQuery($insertSql);
    echo "Default record inserted!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>