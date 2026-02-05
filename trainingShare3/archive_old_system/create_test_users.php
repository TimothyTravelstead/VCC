<?php
// One-time script to create test users for screen sharing
include '../../private_html/db_login.php';

$testUsers = [
    ['TestTrainer', 4],  // Trainer
    ['TestTrainee', 6]   // Trainee
];

foreach ($testUsers as [$userID, $loggedOnStatus]) {
    echo "Creating/updating user: $userID with LoggedOn: $loggedOnStatus\n";
    
    // Check if user exists
    $checkQuery = "SELECT UserID FROM Volunteers WHERE UserID = ?";
    $checkResult = dataQuery($checkQuery, [$userID]);
    
    if (!$checkResult || count($checkResult) == 0) {
        // Create with all required fields
        $insertQuery = "INSERT INTO Volunteers SET 
            UserID = ?, 
            UserName = ?, 
            LoggedOn = ?, 
            Active1 = 'Empty', 
            Active2 = 'Empty',
            Type = 'Test',
            FirstName = ?,
            LastName = 'User',
            Password = 'test123',
            Office = 'Test',
            Hotline = 0";
        
        $insertResult = dataQuery($insertQuery, [$userID, $userID, $loggedOnStatus, $userID]);
        
        if (is_array($insertResult) && isset($insertResult['error'])) {
            echo "  Error creating: " . $insertResult['message'] . "\n";
        } else {
            echo "  ✓ Created successfully\n";
        }
    } else {
        // Update existing
        $updateQuery = "UPDATE Volunteers SET LoggedOn = ?, UserName = ? WHERE UserID = ?";
        $updateResult = dataQuery($updateQuery, [$loggedOnStatus, $userID, $userID]);
        
        if (is_array($updateResult) && isset($updateResult['error'])) {
            echo "  Error updating: " . $updateResult['message'] . "\n";
        } else {
            echo "  ✓ Updated successfully\n";
        }
    }
}

echo "\nTest users setup complete!\n";
?>