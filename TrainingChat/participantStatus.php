<?php

require_once('../../private_html/training_chat_db_login.php');
session_start();
session_cache_limiter('nocache');
session_write_close();

header('Content-Type: application/json');

$chatRoomID = $_POST['chatRoomID'] ?? $_GET['chatRoomID'] ?? null;

if (!$chatRoomID) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing chatRoomID']);
    exit;
}

try {
    // Get trainer and trainee info from session or database
    // You might need to get this from the session or pass it as a parameter
    $trainer = $chatRoomID; // Assuming chatRoomID is the trainer ID
    
    // Get all participants for this training session from Volunteers table
    $query = "
        SELECT 
            UserName,
            FirstName,
            LastName,
            LoggedOn,
            CASE 
                WHEN LoggedOn > 0 THEN 'online' 
                ELSE 'offline' 
            END as current_status
        FROM Volunteers 
        WHERE UserName = ? 
           OR FIND_IN_SET(UserName, (
               SELECT trainee FROM sessions WHERE trainer = ? LIMIT 1
           ))
        ORDER BY LoggedOn DESC
    ";
    
    $results = dataQuery($query, [$trainer, $trainer]);
    
    $participants = [];
    if ($results && is_array($results)) {
        foreach ($results as $row) {
            $participants[] = [
                'id' => $row->UserName,
                'name' => trim($row->FirstName . ' ' . $row->LastName),
                'status' => $row->current_status,
                'loggedOn' => $row->LoggedOn
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'chatRoomID' => $chatRoomID,
        'participants' => $participants,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to get participant status',
        'message' => $e->getMessage()
    ]);
}
?>