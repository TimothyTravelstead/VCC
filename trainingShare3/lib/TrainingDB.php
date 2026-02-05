<?php
/**
 * TrainingDB - Database abstraction layer for training system tables
 *
 * Provides methods for managing:
 * - Training rooms
 * - Training participants
 * - Session state machine
 * - Mute state management
 * - Event logging
 *
 * All methods use the global dataQuery() function from db_login.php
 */

class TrainingDB
{
    // ==========================================================================
    // Room Management
    // ==========================================================================

    /**
     * Create or update a training room
     *
     * Generates a new session_version on creation. This version is used to
     * filter out stale signals from previous sessions - a structural solution
     * to race conditions that doesn't require cleanup logic.
     *
     * @param string $roomId    Room identifier (trainer username)
     * @param string $trainerId Trainer username
     * @return string|false Session version on success, false on failure
     */
    public static function createRoom($roomId, $trainerId)
    {
        // Generate unique session version (32 hex chars)
        $sessionVersion = bin2hex(random_bytes(16));

        $result = dataQuery(
            "INSERT INTO training_rooms (room_id, trainer_id, status, session_version)
             VALUES (?, ?, 'active', ?)
             ON DUPLICATE KEY UPDATE
                trainer_id = VALUES(trainer_id),
                status = 'active',
                session_version = VALUES(session_version),
                last_activity = CURRENT_TIMESTAMP",
            [$roomId, $trainerId, $sessionVersion]
        );

        if (self::isError($result)) {
            return false;
        }

        return $sessionVersion;
    }

    /**
     * Get session version for a room
     *
     * @param string $roomId Room identifier
     * @return string|null Session version or null if room not found
     */
    public static function getSessionVersion($roomId)
    {
        $result = dataQuery(
            "SELECT session_version FROM training_rooms WHERE room_id = ? AND status = 'active'",
            [$roomId]
        );

        return (!empty($result) && !self::isError($result)) ? $result[0]->session_version : null;
    }

    /**
     * Get room by ID
     *
     * @param string $roomId Room identifier
     * @return object|null Room data or null
     */
    public static function getRoom($roomId)
    {
        $result = dataQuery(
            "SELECT * FROM training_rooms WHERE room_id = ?",
            [$roomId]
        );

        return (!empty($result) && !self::isError($result)) ? $result[0] : null;
    }

    /**
     * Get active room for trainer
     *
     * @param string $trainerId Trainer username
     * @return object|null Room data or null
     */
    public static function getActiveRoomForTrainer($trainerId)
    {
        $result = dataQuery(
            "SELECT * FROM training_rooms
             WHERE trainer_id = ? AND status = 'active'
             ORDER BY last_activity DESC
             LIMIT 1",
            [$trainerId]
        );

        return (!empty($result) && !self::isError($result)) ? $result[0] : null;
    }

    /**
     * Update room activity timestamp
     *
     * @param string $roomId Room identifier
     * @return bool Success status
     */
    public static function touchRoom($roomId)
    {
        $result = dataQuery(
            "UPDATE training_rooms
             SET last_activity = CURRENT_TIMESTAMP
             WHERE room_id = ?",
            [$roomId]
        );

        return !self::isError($result);
    }

    /**
     * Close a training room
     *
     * @param string $roomId Room identifier
     * @return bool Success status
     */
    public static function closeRoom($roomId)
    {
        $result = dataQuery(
            "UPDATE training_rooms
             SET status = 'inactive'
             WHERE room_id = ?",
            [$roomId]
        );

        return !self::isError($result);
    }

    // ==========================================================================
    // Participant Management
    // ==========================================================================

    /**
     * Add participant to room (idempotent)
     *
     * This operation is idempotent - calling it multiple times for the same
     * participant has the same effect as calling once. Returns whether this
     * was a NEW join so callers can decide whether to broadcast.
     *
     * @param string $roomId        Room identifier
     * @param string $participantId Participant username
     * @param string $role          'trainer' or 'trainee'
     * @param string|null $callSid  Optional Twilio CallSid
     * @return array ['success' => bool, 'isNewJoin' => bool]
     */
    public static function addParticipant($roomId, $participantId, $role, $callSid = null)
    {
        // Check if already connected (for idempotency)
        $existing = self::getParticipant($roomId, $participantId);
        $isNewJoin = !$existing || !$existing->is_connected;

        $result = dataQuery(
            "INSERT INTO training_participants
             (room_id, participant_id, participant_role, call_sid, is_connected)
             VALUES (?, ?, ?, ?, TRUE)
             ON DUPLICATE KEY UPDATE
                participant_role = VALUES(participant_role),
                call_sid = COALESCE(VALUES(call_sid), call_sid),
                is_connected = TRUE,
                last_seen = CURRENT_TIMESTAMP",
            [$roomId, $participantId, $role, $callSid]
        );

        return [
            'success' => !self::isError($result),
            'isNewJoin' => $isNewJoin
        ];
    }

    /**
     * Remove participant from room
     *
     * @param string $roomId        Room identifier
     * @param string $participantId Participant username
     * @return bool Success status
     */
    public static function removeParticipant($roomId, $participantId)
    {
        $result = dataQuery(
            "DELETE FROM training_participants
             WHERE room_id = ? AND participant_id = ?",
            [$roomId, $participantId]
        );

        return !self::isError($result);
    }

    /**
     * Get all participants in a room
     *
     * @param string $roomId Room identifier
     * @return array Array of participant objects
     */
    public static function getParticipants($roomId)
    {
        $result = dataQuery(
            "SELECT * FROM training_participants
             WHERE room_id = ? AND is_connected = TRUE
             ORDER BY joined_at ASC",
            [$roomId]
        );

        return (!self::isError($result)) ? $result : [];
    }

    /**
     * Get participant by ID
     *
     * @param string $roomId        Room identifier
     * @param string $participantId Participant username
     * @return object|null Participant data or null
     */
    public static function getParticipant($roomId, $participantId)
    {
        $result = dataQuery(
            "SELECT * FROM training_participants
             WHERE room_id = ? AND participant_id = ?",
            [$roomId, $participantId]
        );

        return (!empty($result) && !self::isError($result)) ? $result[0] : null;
    }

    /**
     * Update participant's CallSid
     *
     * @param string $roomId        Room identifier
     * @param string $participantId Participant username
     * @param string $callSid       Twilio CallSid
     * @return bool Success status
     */
    public static function updateParticipantCallSid($roomId, $participantId, $callSid)
    {
        $result = dataQuery(
            "UPDATE training_participants
             SET call_sid = ?, last_seen = CURRENT_TIMESTAMP
             WHERE room_id = ? AND participant_id = ?",
            [$callSid, $roomId, $participantId]
        );

        return !self::isError($result);
    }

    /**
     * Update participant's last_seen timestamp (heartbeat)
     *
     * @param string $roomId        Room identifier
     * @param string $participantId Participant username
     * @return bool Success status
     */
    public static function touchParticipant($roomId, $participantId)
    {
        $result = dataQuery(
            "UPDATE training_participants
             SET last_seen = CURRENT_TIMESTAMP
             WHERE room_id = ? AND participant_id = ?",
            [$roomId, $participantId]
        );

        return !self::isError($result);
    }

    /**
     * Mark participant as disconnected
     *
     * @param string $roomId        Room identifier
     * @param string $participantId Participant username
     * @return bool Success status
     */
    public static function disconnectParticipant($roomId, $participantId)
    {
        $result = dataQuery(
            "UPDATE training_participants
             SET is_connected = FALSE
             WHERE room_id = ? AND participant_id = ?",
            [$roomId, $participantId]
        );

        return !self::isError($result);
    }

    // ==========================================================================
    // Session State Machine
    // ==========================================================================

    /**
     * Initialize or reset session state
     *
     * @param string $trainerId         Trainer username
     * @param string $activeController  Who has control (initially trainer)
     * @return bool Success status
     */
    public static function initSessionState($trainerId, $activeController = null)
    {
        $controller = $activeController ?? $trainerId;

        $result = dataQuery(
            "INSERT INTO training_session_state
             (trainer_id, session_state, active_controller, external_call_active)
             VALUES (?, 'INITIALIZING', ?, FALSE)
             ON DUPLICATE KEY UPDATE
                session_state = 'INITIALIZING',
                active_controller = VALUES(active_controller),
                external_call_active = FALSE,
                external_call_sid = NULL,
                conference_sid = NULL,
                state_changed_at = CURRENT_TIMESTAMP",
            [$trainerId, $controller]
        );

        return !self::isError($result);
    }

    /**
     * Get current session state
     *
     * @param string $trainerId Trainer username
     * @return object|null Session state or null
     */
    public static function getSessionState($trainerId)
    {
        $result = dataQuery(
            "SELECT * FROM training_session_state WHERE trainer_id = ?",
            [$trainerId]
        );

        return (!empty($result) && !self::isError($result)) ? $result[0] : null;
    }

    /**
     * Transition to a new session state
     *
     * @param string $trainerId  Trainer username
     * @param string $newState   New state (INITIALIZING, CONNECTED, ON_CALL, RECONNECTING, DISCONNECTED)
     * @return bool Success status
     */
    public static function transitionState($trainerId, $newState)
    {
        $validStates = ['INITIALIZING', 'CONNECTED', 'ON_CALL', 'RECONNECTING', 'DISCONNECTED'];
        if (!in_array($newState, $validStates)) {
            error_log("TrainingDB: Invalid state transition to '$newState'");
            return false;
        }

        $result = dataQuery(
            "UPDATE training_session_state
             SET session_state = ?, state_changed_at = CURRENT_TIMESTAMP
             WHERE trainer_id = ?",
            [$newState, $trainerId]
        );

        return !self::isError($result);
    }

    /**
     * Set active controller
     *
     * @param string $trainerId    Trainer username
     * @param string $controllerId Who should have control
     * @return bool Success status
     */
    public static function setActiveController($trainerId, $controllerId)
    {
        $result = dataQuery(
            "UPDATE training_session_state
             SET active_controller = ?, state_changed_at = CURRENT_TIMESTAMP
             WHERE trainer_id = ?",
            [$controllerId, $trainerId]
        );

        return !self::isError($result);
    }

    /**
     * Set external call state
     *
     * @param string $trainerId     Trainer username
     * @param bool   $isActive      Is external call active?
     * @param string|null $callSid  External caller's CallSid
     * @param string|null $confSid  Conference SID
     * @return bool Success status
     */
    public static function setExternalCallState($trainerId, $isActive, $callSid = null, $confSid = null)
    {
        // When external call ends, return to CONNECTED state (not RECONNECTING)
        // RECONNECTING is for network/peer connection issues, not call state changes
        $newState = $isActive ? 'ON_CALL' : 'CONNECTED';

        $result = dataQuery(
            "UPDATE training_session_state
             SET external_call_active = ?,
                 external_call_sid = ?,
                 conference_sid = ?,
                 session_state = ?,
                 state_changed_at = CURRENT_TIMESTAMP
             WHERE trainer_id = ?",
            [
                $isActive ? 1 : 0,
                $callSid,
                $confSid,
                $newState,
                $trainerId
            ]
        );

        return !self::isError($result);
    }

    /**
     * Delete session state (on logout)
     *
     * @param string $trainerId Trainer username
     * @return bool Success status
     */
    public static function deleteSessionState($trainerId)
    {
        $result = dataQuery(
            "DELETE FROM training_session_state WHERE trainer_id = ?",
            [$trainerId]
        );

        return !self::isError($result);
    }

    // ==========================================================================
    // Mute State Management
    // ==========================================================================

    /**
     * Set mute state for a participant
     *
     * @param string $trainerId     Trainer username (conference identifier)
     * @param string $participantId Participant username
     * @param bool   $isMuted       Should be muted?
     * @param string|null $callSid  Participant's CallSid for Twilio API
     * @param string|null $reason   Reason for muting
     * @return bool Success status
     */
    public static function setMuteState($trainerId, $participantId, $isMuted, $callSid = null, $reason = null)
    {
        $result = dataQuery(
            "INSERT INTO training_mute_state
             (trainer_id, participant_id, is_muted, call_sid, mute_reason)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                is_muted = VALUES(is_muted),
                call_sid = COALESCE(VALUES(call_sid), call_sid),
                mute_reason = VALUES(mute_reason),
                updated_at = CURRENT_TIMESTAMP",
            [$trainerId, $participantId, $isMuted ? 1 : 0, $callSid, $reason]
        );

        return !self::isError($result);
    }

    /**
     * Get mute state for a participant
     *
     * @param string $trainerId     Trainer username
     * @param string $participantId Participant username
     * @return object|null Mute state or null
     */
    public static function getMuteState($trainerId, $participantId)
    {
        $result = dataQuery(
            "SELECT * FROM training_mute_state
             WHERE trainer_id = ? AND participant_id = ?",
            [$trainerId, $participantId]
        );

        return (!empty($result) && !self::isError($result)) ? $result[0] : null;
    }

    /**
     * Get all mute states for a training session
     *
     * @param string $trainerId Trainer username
     * @return array Array of mute state objects
     */
    public static function getAllMuteStates($trainerId)
    {
        $result = dataQuery(
            "SELECT * FROM training_mute_state WHERE trainer_id = ?",
            [$trainerId]
        );

        return (!self::isError($result)) ? $result : [];
    }

    /**
     * Clear all mute states for a session
     *
     * @param string $trainerId Trainer username
     * @return bool Success status
     */
    public static function clearMuteStates($trainerId)
    {
        $result = dataQuery(
            "DELETE FROM training_mute_state WHERE trainer_id = ?",
            [$trainerId]
        );

        return !self::isError($result);
    }

    /**
     * Bulk mute/unmute all non-controllers
     *
     * @param string $trainerId    Trainer username
     * @param string $controllerId Active controller (stays unmuted)
     * @param bool   $mute         Mute or unmute non-controllers
     * @param string|null $reason  Reason for bulk mute
     * @return bool Success status
     */
    public static function bulkMuteNonControllers($trainerId, $controllerId, $mute = true, $reason = null)
    {
        // Get all participants except the controller
        $participants = self::getParticipants($trainerId);

        $success = true;
        foreach ($participants as $participant) {
            $shouldMute = ($participant->participant_id !== $controllerId) && $mute;
            $result = self::setMuteState(
                $trainerId,
                $participant->participant_id,
                $shouldMute,
                $participant->call_sid,
                $shouldMute ? $reason : null
            );
            if (!$result) {
                $success = false;
            }
        }

        return $success;
    }

    // ==========================================================================
    // Event Logging
    // ==========================================================================

    /**
     * Log a training event
     *
     * @param string $trainerId     Trainer username
     * @param string $eventType     Event type (join, leave, call_start, etc.)
     * @param array|null $eventData Additional event data
     * @param string|null $participantId Who triggered the event
     * @return bool Success status
     */
    public static function logEvent($trainerId, $eventType, $eventData = null, $participantId = null)
    {
        $result = dataQuery(
            "INSERT INTO training_events_log
             (trainer_id, event_type, event_data, participant_id)
             VALUES (?, ?, ?, ?)",
            [
                $trainerId,
                $eventType,
                $eventData ? json_encode($eventData) : null,
                $participantId
            ]
        );

        return !self::isError($result);
    }

    /**
     * Get recent events for a training session
     *
     * @param string $trainerId Trainer username
     * @param int    $limit     Max events to return
     * @return array Array of event objects
     */
    public static function getRecentEvents($trainerId, $limit = 50)
    {
        $result = dataQuery(
            "SELECT * FROM training_events_log
             WHERE trainer_id = ?
             ORDER BY created_at DESC
             LIMIT " . intval($limit),
            [$trainerId]
        );

        return (!self::isError($result)) ? $result : [];
    }

    // ==========================================================================
    // Cleanup
    // ==========================================================================

    /**
     * Clean up all data for a training session
     *
     * @param string $trainerId Trainer username
     * @return bool Success status
     */
    public static function cleanupSession($trainerId)
    {
        $success = true;

        // Close room
        $success = self::closeRoom($trainerId) && $success;

        // Remove all participants
        dataQuery("DELETE FROM training_participants WHERE room_id = ?", [$trainerId]);

        // Delete session state
        $success = self::deleteSessionState($trainerId) && $success;

        // Clear mute states
        $success = self::clearMuteStates($trainerId) && $success;

        // Log the cleanup
        self::logEvent($trainerId, 'session_cleanup', null, null);

        return $success;
    }

    /**
     * Run cleanup of stale data
     * Called by cron or on-demand
     *
     * @return array Cleanup statistics
     */
    public static function runCleanup()
    {
        $stats = [
            'signals_deleted' => 0,
            'participants_removed' => 0,
            'rooms_closed' => 0,
            'events_deleted' => 0
        ];

        // Remove delivered signals older than 1 hour
        dataQuery("DELETE FROM training_signals WHERE delivered = TRUE AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

        // Remove undelivered signals older than 5 minutes
        dataQuery("DELETE FROM training_signals WHERE delivered = FALSE AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

        // Remove stale participants (not seen for 5 minutes)
        dataQuery("DELETE FROM training_participants WHERE last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

        // Close inactive rooms
        dataQuery("UPDATE training_rooms SET status = 'inactive' WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE) AND status = 'active'");

        // Remove old event logs (keep 7 days)
        dataQuery("DELETE FROM training_events_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");

        return $stats;
    }

    // ==========================================================================
    // Helper Methods
    // ==========================================================================

    /**
     * Check if a result is an error
     *
     * @param mixed $result Query result
     * @return bool True if error
     */
    private static function isError($result)
    {
        return is_array($result) && isset($result['error']) && $result['error'] === true;
    }
}
