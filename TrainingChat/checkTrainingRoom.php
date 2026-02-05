<?php

require_once '../../private_html/training_chat_db_login.php';
session_start();
session_cache_limiter('nocache');

if ($_SESSION['auth'] != 'yes') {
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}
session_write_close();


$trainer = $_POST['trainer'] ?? '';

if ($trainer) {
    // Get the LATEST room for this trainer (in case there are duplicates)
    $getRoomQuery = "SELECT id FROM groupchatrooms WHERE Name = ? ORDER BY id DESC LIMIT 1";
    $getRoomResult = chatDataQuery($getRoomQuery, [$trainer]);
    
    if ($getRoomResult && $getRoomResult[0]->id) {
        echo json_encode([
            'success' => true, 
            'chatRoomID' => $getRoomResult[0]->id,
            'trainer' => $trainer
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Room not found',
            'trainer' => $trainer
        ]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No trainer specified']);
}
?>