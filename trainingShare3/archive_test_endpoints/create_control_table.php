<?php
// Web-accessible table creation
header('Content-Type: text/plain');
require_once '../../private_html/db_login.php';

echo "Creating training control table via web...\n";

try {
    // First try to drop the table if it exists to start fresh
    $dropResult = dataQuery("DROP TABLE IF EXISTS training_session_control");
    echo "Dropped existing table (if any)\n";
    
    // Create the table with a simpler structure
    $createSQL = "CREATE TABLE training_session_control (
        id INT AUTO_INCREMENT PRIMARY KEY,
        trainer_id VARCHAR(50) NOT NULL,
        active_controller VARCHAR(50) NOT NULL,
        controller_role VARCHAR(20) NOT NULL DEFAULT 'trainer',
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (trainer_id)
    )";
    
    $createResult = dataQuery($createSQL);
    echo "Created table successfully\n";
    
    // Insert a test record
    $insertSQL = "INSERT INTO training_session_control (trainer_id, active_controller, controller_role) 
                  VALUES (?, ?, ?)";
    $insertResult = dataQuery($insertSQL, ['Travelstead', 'Travelstead', 'trainer']);
    echo "Inserted test record\n";
    
    // Verify the table works
    $testResult = dataQuery("SELECT * FROM training_session_control");
    if ($testResult && is_array($testResult)) {
        echo "Table verification successful - found " . count($testResult) . " records\n";
        foreach ($testResult as $record) {
            echo "Record: trainer={$record['trainer_id']}, controller={$record['active_controller']}, role={$record['controller_role']}\n";
        }
    } else {
        echo "Table verification failed\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>