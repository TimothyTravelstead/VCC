<?php

require_once('../../private_html/training_chat_db_login.php');
session_start();
session_cache_limiter('nocache');
session_write_close();

$userID = $_POST['userID'] ?? $_GET['userID'] ?? null;
$chatRoomID = $_POST['chatRoomID'] ?? $_GET['chatRoomID'] ?? null;
$action = $_POST['action'] ?? $_GET['action'] ?? 'user_leaving';

if (!$userID || !$chatRoomID) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

try {
    // Mark user as offline
    $params = [$userID, $chatRoomID];
    $query = "UPDATE callers SET status = 0, modified = now() WHERE userID = ? AND chatRoomID = ?";
    $result = dataQuery($query, $params);
    
    if ($result !== false) {
        // Check if this was the last active user in the room
        $activeUsersQuery = "SELECT COUNT(*) as count FROM callers WHERE chatRoomID = ? AND status > 0";
        $activeResult = dataQuery($activeUsersQuery, [$chatRoomID]);
        
        $activeCount = 0;
        if ($activeResult && is_array($activeResult)) {
            $activeCount = $activeResult[0]->count;
        }
        
        // If no active users, trigger immediate cleanup
        if ($activeCount == 0) {
            include_once('chatCleanup.php');
            $cleanupService = new ChatCleanupService(0); // No threshold for immediate cleanup
            $cleanupResult = $cleanupService->performCleanup();
            
            echo json_encode([
                'status' => 'success',
                'user_marked_offline' => true,
                'room_cleaned' => true,
                'cleanup_result' => $cleanupResult
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'user_marked_offline' => true,
                'active_users_remaining' => $activeCount
            ]);
        }
    } else {
        throw new Exception('Failed to update user status');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to process user departure',
        'message' => $e->getMessage()
    ]);
}
?>