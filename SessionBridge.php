<?php
/**
 * SessionBridge - Bridge between PHP authentication and Socket.IO training system
 * 
 * This class manages session state between the traditional PHP-based volunteer
 * authentication system and the real-time Socket.IO training/chat features.
 */

class SessionBridge {
    
    public function __construct() {
        // SessionBridge uses the main database system, not GroupChat database
        // Database connection is handled by dataQuery() function which should be available
        // No separate include needed as loginverify2.php already includes the main db connection
    }
    
    /**
     * Create a new session when a volunteer logs in via PHP
     * Called from loginverify2.php after successful authentication
     */
    public function createSession($userData, $sessionData = []) {
        $sessionId = $this->generateSessionId();
        $userId = $userData['UserID'];
        $userName = $userData['UserName'];
        $roleType = $this->mapAdminToRoleType($sessionData['Admin'] ?? '1');
        
        // Set session expiration (4 hours from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+4 hours'));
        
        // Prepare session data
        $sessionDataJson = json_encode([
            'full_name' => $userData['FullName'] ?? '',
            'desk' => $userData['Desk'] ?? '',
            'admin_level' => $sessionData['Admin'] ?? '1',
            'chat_only_flag' => $sessionData['ChatOnlyFlag'] ?? '0',
            'trainee_list' => $sessionData['TraineeList'] ?? null,
            'php_session_data' => $sessionData
        ]);
        
        // Get volunteer ID from volunteers table
        $volunteerQuery = "SELECT UserId FROM volunteers WHERE UserName = ? LIMIT 1";
        $volunteerResult = dataQuery($volunteerQuery, [$userName]);
        
        if (!$volunteerResult || empty($volunteerResult)) {
            throw new Exception("Volunteer not found: " . $userName);
        }
        
        $volunteerId = $volunteerResult[0]->UserId;
        
        // Insert session record
        $insertQuery = "INSERT INTO volunteer_sessions (
            session_id, volunteer_id, user_name, php_session_id, expires_at,
            role_type, training_room_id, trainer_id, trainee_list, session_data
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $sessionId,
            $volunteerId,
            $userName,
            session_id(),
            $expiresAt,
            $roleType,
            $this->generateTrainingRoomId($userName, $roleType, $sessionData),
            $this->getTrainerId($roleType, $userName, $sessionData),
            $sessionData['TraineeList'] ?? null,
            $sessionDataJson
        ];
        
        $result = dataQuery($insertQuery, $params);
        
        if ($result === false) {
            throw new Exception("Failed to create session for " . $userName);
        }
        
        // Store session ID in PHP session for easy access
        $_SESSION['volunteer_session_id'] = $sessionId;
        
        return $sessionId;
    }
    
    /**
     * Update session when Socket.IO connection is established
     */
    public function connectSocket($sessionId, $socketId) {
        $updateQuery = "UPDATE volunteer_sessions 
                       SET socket_id = ?, connection_status = 'connected', 
                           last_socket_activity = NOW(), last_activity = NOW()
                       WHERE session_id = ? AND is_active = TRUE";
        
        return dataQuery($updateQuery, [$socketId, $sessionId]);
    }
    
    /**
     * Update session activity (heartbeat)
     */
    public function updateActivity($sessionId, $socketId = null) {
        $query = "UPDATE volunteer_sessions 
                 SET last_activity = NOW()";
        $params = [$sessionId];
        
        if ($socketId) {
            $query .= ", last_socket_activity = NOW()";
        }
        
        $query .= " WHERE session_id = ? AND is_active = TRUE";
        
        return dataQuery($query, $params);
    }
    
    /**
     * Validate session and get session data
     */
    public function getSession($sessionId) {
        $query = "SELECT vs.*, v.FirstName, v.LastName, v.LoggedOn
                 FROM volunteer_sessions vs
                 JOIN volunteers v ON vs.volunteer_id = v.UserId
                 WHERE vs.session_id = ? 
                   AND vs.is_active = TRUE 
                   AND vs.expires_at > NOW()";
        
        $result = dataQuery($query, [$sessionId]);
        
        if (!$result || empty($result)) {
            return null;
        }
        
        $session = $result[0];
        
        // Parse JSON data
        $session->session_data_parsed = json_decode($session->session_data, true);
        
        return $session;
    }
    
    /**
     * Get session by Socket.IO socket ID
     */
    public function getSessionBySocketId($socketId) {
        $query = "SELECT vs.*, v.FirstName, v.LastName, v.LoggedOn
                 FROM volunteer_sessions vs
                 JOIN volunteers v ON vs.volunteer_id = v.UserId
                 WHERE vs.socket_id = ? 
                   AND vs.is_active = TRUE 
                   AND vs.expires_at > NOW()";
        
        $result = dataQuery($query, [$socketId]);
        
        return $result ? $result[0] : null;
    }
    
    /**
     * End session (logout)
     */
    public function endSession($sessionId, $reason = 'logout') {
        $updateQuery = "UPDATE volunteer_sessions 
                       SET is_active = FALSE, 
                           logout_reason = ?, 
                           connection_status = 'disconnected',
                           socket_id = NULL
                       WHERE session_id = ?";
        
        return dataQuery($updateQuery, [$reason, $sessionId]);
    }
    
    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions() {
        $deleteQuery = "DELETE FROM volunteer_sessions 
                       WHERE expires_at < NOW() 
                          OR (last_activity < DATE_SUB(NOW(), INTERVAL 4 HOUR) 
                              AND connection_status = 'disconnected')";
        
        return dataQuery($deleteQuery, []);
    }
    
    /**
     * Get all active training sessions
     */
    public function getActiveTrainingSessions() {
        $query = "SELECT * FROM active_training_sessions ORDER BY last_activity DESC";
        return dataQuery($query, []);
    }
    
    /**
     * Map Admin level to role type
     */
    private function mapAdminToRoleType($adminLevel) {
        $roleMap = [
            '1' => 'volunteer',
            '2' => 'resources_only', 
            '3' => 'admin',
            '4' => 'trainer',
            '5' => 'monitor',
            '6' => 'trainee',
            '7' => 'admin_mini',
            '8' => 'group_chat_monitor',
            '9' => 'resource_admin'
        ];
        
        return $roleMap[$adminLevel] ?? 'volunteer';
    }
    
    /**
     * Generate training room ID for trainers/trainees
     */
    private function generateTrainingRoomId($userName, $roleType, $sessionData) {
        if ($roleType === 'trainer' && !empty($sessionData['TraineeList'])) {
            return 'training_' . $userName . '_' . time();
        } elseif ($roleType === 'trainee') {
            return null; // Will be set when joining trainer's room
        }
        return null;
    }
    
    /**
     * Get trainer ID for training sessions
     */
    private function getTrainerId($roleType, $userName, $sessionData) {
        if ($roleType === 'trainer') {
            return $userName;
        } elseif ($roleType === 'trainee') {
            // Logic to find the trainer for this trainee
            // This might need to be enhanced based on your trainer assignment logic
            return null;
        }
        return null;
    }
    
    /**
     * Generate unique session ID
     */
    private function generateSessionId() {
        return 'vs_' . bin2hex(random_bytes(16)) . '_' . time();
    }
    
    
    /**
     * Static method to initialize session bridge after login
     * Call this from loginverify2.php after successful authentication
     */
    public static function initializeAfterLogin($userData, $sessionData) {
        try {
            $bridge = new SessionBridge();
            $sessionId = $bridge->createSession($userData, $sessionData);
            
            // Optionally notify the media server about the new session
            if (function_exists('notifyMediaServer')) {
                notifyMediaServer('session_created', [
                    'session_id' => $sessionId,
                    'user_name' => $userData['UserName'],
                    'role_type' => $bridge->mapAdminToRoleType($sessionData['Admin'] ?? '1')
                ]);
            }
            
            return $sessionId;
        } catch (Exception $e) {
            error_log("SessionBridge initialization failed: " . $e->getMessage());
            return null;
        }
    }
}

// Helper functions for use in Socket.IO server

/**
 * Authenticate Socket.IO connection using session ID
 */
function authenticateSocketConnection($sessionId) {
    try {
        $bridge = new SessionBridge();
        return $bridge->getSession($sessionId);
    } catch (Exception $e) {
        error_log("Socket authentication failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Update session activity from Socket.IO events
 */
function updateSessionActivity($sessionId, $socketId = null) {
    try {
        $bridge = new SessionBridge();
        return $bridge->updateActivity($sessionId, $socketId);
    } catch (Exception $e) {
        error_log("Failed to update session activity: " . $e->getMessage());
        return false;
    }
}

?>