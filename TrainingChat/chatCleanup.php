<?php
/**
 * Chat Room Cleanup Service
 * Monitors for empty chat rooms and cleans up chat history
 * Should be called periodically via cron job or AJAX
 */

require_once('../../private_html/training_chat_db_login.php');

$UserID = $_SESSION['UserID'] ?? null;
$chatRoomID = $_REQUEST['chatRoomID'] ?? null;


class ChatCleanupService {
    private $inactivityThreshold = 300; // 5 minutes in seconds
    private $debug = [];
    
    public function __construct($inactivityThreshold = 300) {
        $this->inactivityThreshold = $inactivityThreshold;
        $this->debug = [
            'timestamp' => date('Y-m-d H:i:s'),
            'threshold_minutes' => $this->inactivityThreshold / 60,
            'actions' => []
        ];
    }
    
    /**
     * Main cleanup process
     */
    public function performCleanup() {
        $this->debug['actions'][] = 'Starting chat cleanup process';
        
        // Find inactive chat rooms
        $inactiveChatRooms = $chatRoomID;
        
        if (empty($inactiveChatRooms)) {
            $this->debug['actions'][] = 'No inactive chat rooms found';
            return $this->debug;
        }
        
        // Clean up each inactive room
        foreach ($inactiveChatRooms as $chatRoom) {
            $this->cleanupChatRoom($chatRoom);
        }
        
        return $this->debug;
    }
    
    /**
     * Clean up a specific chat room
     */
    private function cleanupChatRoom($chatRoomID) {
        $this->debug['actions'][] = [
            'action' => 'cleaning_room',
            'chatRoomID' => $chatRoomID
        ];
        
        try {
            // Start transaction
            $this->beginTransaction();
            
            // Count messages before deletion
            $messageCount = $this->getMessageCount($chatRoomID);
            
            // Delete chat messages
            $this->deleteChatMessages($chatRoomID);
            
            // Delete transactions
            $this->deleteTransactions($chatRoomID);
            
            // Clean up user records
            $this->cleanupUserRecords($chatRoomID);
            
            // Insert cleanup log
            $this->logCleanup($chatRoomID, $messageCount);
            
            // Commit transaction
            $this->commitTransaction();
            
            $this->debug['actions'][] = [
                'action' => 'room_cleaned_successfully',
                'chatRoomID' => $chatRoomID,
                'messages_deleted' => $messageCount
            ];
            
        } catch (Exception $e) {
            $this->rollbackTransaction();
            $this->debug['actions'][] = [
                'action' => 'cleanup_failed',
                'chatRoomID' => $chatRoomID,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get message count for a chat room
     */
    private function getMessageCount($chatRoomID) {
        $query = "SELECT COUNT(*) as count FROM groupChat WHERE chatRoomID = ?";
        $result = dataQuery($query, [$chatRoomID]);
        
        return $result && is_array($result) ? $result[0]->count : 0;
    }
    
    /**
     * Delete chat messages for a room
     */
    private function deleteChatMessages($chatRoomID) {
        $query = "DELETE FROM groupChat WHERE chatRoomID = ?";
        $result = dataQuery($query, [$chatRoomID]);
        
        if ($result === false) {
            throw new Exception("Failed to delete chat messages for room: " . $chatRoomID);
        }
        
        return $result;
    }
    
    /**
     * Delete transactions for a room
     */
    private function deleteTransactions($chatRoomID) {
        $query = "DELETE FROM Transactions WHERE chatRoomID = ?";
        $result = dataQuery($query, [$chatRoomID]);
        
        if ($result === false) {
            throw new Exception("Failed to delete transactions for room: " . $chatRoomID);
        }
        
        return $result;
    }
    
    /**
     * Clean up user records for inactive room
     */
    private function cleanupUserRecords($chatRoomID) {
        // Update caller status to offline for this room
        $query = "UPDATE callers SET status = 0, modified = now() WHERE chatRoomID = ?";
        $result = dataQuery($query, [$chatRoomID]);
        
        if ($result === false) {
            throw new Exception("Failed to cleanup user records for room: " . $chatRoomID);
        }
        
        return $result;
    }
    
    /**
     * Log the cleanup action
     */
    private function logCleanup($chatRoomID, $messageCount) {
        $query = "
            INSERT INTO chat_cleanup_log 
            (chatRoomID, messages_deleted, cleanup_time, cleanup_reason) 
            VALUES (?, ?, now(), 'Inactive room cleanup')
        ";
        
        $result = dataQuery($query, [$chatRoomID, $messageCount]);
        
        if ($result === false) {
            // Log failure but don't throw exception - cleanup should still succeed
            $this->debug['actions'][] = [
                'action' => 'log_cleanup_failed',
                'chatRoomID' => $chatRoomID
            ];
        }
        
        return $result;
    }
    
    /**
     * Transaction management methods
     */
    private function beginTransaction() {
        // Implementation depends on your database connection method
        // This is a placeholder - adapt to your actual DB connection
        global $mysqli;
        if (isset($mysqli)) {
            $mysqli->begin_transaction();
        }
    }
    
    private function commitTransaction() {
        global $mysqli;
        if (isset($mysqli)) {
            $mysqli->commit();
        }
    }
    
    private function rollbackTransaction() {
        global $mysqli;
        if (isset($mysqli)) {
            $mysqli->rollback();
        }
    }
}

// If called directly, perform cleanup and return JSON response
if (basename($_SERVER['PHP_SELF']) === 'chatCleanup.php') {
    header('Content-Type: application/json');
    
    $cleanupService = new ChatCleanupService();
    $result = $cleanupService->performCleanup();
    
    echo json_encode($result, JSON_PRETTY_PRINT);
}
?>