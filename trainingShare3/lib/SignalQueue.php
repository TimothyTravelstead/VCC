<?php
/**
 * SignalQueue - Atomic WebRTC signal send/receive operations
 *
 * Replaces file-based signaling with database-backed signaling.
 * Uses transactions for atomic read-and-mark-delivered operations.
 *
 * Benefits over file-based signaling:
 * - Atomic operations prevent race conditions
 * - No _MULTIPLEVENTS_ delimiter corruption
 * - Proper message ordering with millisecond precision
 * - Reliable delivery tracking
 */

class SignalQueue
{
    // Signal types
    const TYPE_OFFER = 'offer';
    const TYPE_ANSWER = 'answer';
    const TYPE_ICE_CANDIDATE = 'ice-candidate';
    const TYPE_JOIN_ROOM = 'join-room';
    const TYPE_LEAVE_ROOM = 'leave-room';
    const TYPE_PARTICIPANT_JOINED = 'participant-joined';
    const TYPE_PARTICIPANT_LEFT = 'participant-left';
    const TYPE_SCREEN_SHARE_START = 'screen-share-start';
    const TYPE_SCREEN_SHARE_STOP = 'screen-share-stop';
    const TYPE_CONTROL_CHANGE = 'control-change';
    const TYPE_CALL_START = 'call-start';
    const TYPE_CALL_END = 'call-end';
    const TYPE_CONFERENCE_RESTART = 'conference-restart';
    const TYPE_MUTE_STATE = 'mute-state';

    /**
     * Send a signal to a specific recipient
     *
     * Signals are tagged with the room's session_version. When polling,
     * only signals matching the current session version are returned.
     * This eliminates stale signals structurally.
     *
     * @param string $roomId      Room identifier
     * @param string $senderId    Sender username
     * @param string $recipientId Recipient username
     * @param string $signalType  Signal type (use class constants)
     * @param array  $signalData  Signal payload data
     * @param string|null $sessionVersion Optional session version (auto-fetched if not provided)
     * @return int|false Signal ID on success, false on failure
     */
    public static function sendToParticipant($roomId, $senderId, $recipientId, $signalType, $signalData, $sessionVersion = null)
    {
        // Get session version if not provided
        if ($sessionVersion === null) {
            $sessionVersion = TrainingDB::getSessionVersion($roomId) ?? '';
        }

        $result = dataQuery(
            "INSERT INTO training_signals
             (room_id, sender_id, recipient_id, signal_type, signal_data, session_version)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $roomId,
                $senderId,
                $recipientId,
                $signalType,
                json_encode($signalData),
                $sessionVersion
            ]
        );

        if (self::isError($result)) {
            error_log("SignalQueue: Failed to send signal to $recipientId: " . json_encode($result));
            return false;
        }

        // Get the inserted ID
        $lastId = dataQuery("SELECT LAST_INSERT_ID() as id");
        return (!empty($lastId) && !self::isError($lastId)) ? $lastId[0]->id : true;
    }

    /**
     * Broadcast a signal to all participants in a room (except sender)
     *
     * @param string $roomId     Room identifier
     * @param string $senderId   Sender username
     * @param string $signalType Signal type
     * @param array  $signalData Signal payload data
     * @return int Number of signals sent
     */
    public static function broadcastToRoom($roomId, $senderId, $signalType, $signalData)
    {
        // Get session version once for all signals
        $sessionVersion = TrainingDB::getSessionVersion($roomId) ?? '';

        // Get all participants in the room except sender
        $participants = dataQuery(
            "SELECT participant_id FROM training_participants
             WHERE room_id = ? AND participant_id != ? AND is_connected = TRUE",
            [$roomId, $senderId]
        );

        if (self::isError($participants) || empty($participants)) {
            return 0;
        }

        $sent = 0;
        foreach ($participants as $participant) {
            $result = self::sendToParticipant(
                $roomId,
                $senderId,
                $participant->participant_id,
                $signalType,
                $signalData,
                $sessionVersion  // Pass version to avoid repeated lookups
            );
            if ($result !== false) {
                $sent++;
            }
        }

        return $sent;
    }

    // Cached PDO connection for transactional work
    private static $pdo = null;

    /**
     * Get or create a PDO connection for transactional operations
     */
    private static function getPDO()
    {
        if (self::$pdo === null) {
            global $env;

            $dbname = $env['DB_DATABASE'];
            $host = $env['DB_HOST'];
            $user = $env['DB_USERNAME'];
            $pass = $env['DB_PASSWORD'];

            self::$pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => true, // Use persistent connections
                ]
            );
        }

        return self::$pdo;
    }

    /**
     * Get pending signals for a recipient (atomic read + mark delivered)
     *
     * This is the key method that replaces file-based polling.
     * Uses a transaction to atomically:
     * 1. SELECT pending signals
     * 2. Mark them as delivered
     *
     * STRUCTURAL RACE CONDITION PREVENTION:
     * When sessionVersion is provided, only signals matching that version
     * are returned. Signals from old sessions are automatically filtered out
     * without any cleanup logic needed.
     *
     * @param string $recipientId    Recipient username
     * @param int    $limit          Max signals to return (default 50)
     * @param string|null $sessionVersion Only return signals with this version (null = all)
     * @return array Array of signal objects
     */
    public static function getSignals($recipientId, $limit = 50, $sessionVersion = null)
    {
        try {
            $pdo = self::getPDO();
            $pdo->beginTransaction();

            try {
                // Build query with optional session version filter
                $sql = "SELECT id, room_id, sender_id, signal_type, signal_data, created_at, session_version
                        FROM training_signals
                        WHERE recipient_id = ? AND delivered = FALSE";
                $params = [$recipientId];

                // Filter by session version if provided - this is the key structural protection
                if ($sessionVersion !== null && $sessionVersion !== '') {
                    $sql .= " AND session_version = ?";
                    $params[] = $sessionVersion;
                }

                $sql .= " ORDER BY created_at ASC LIMIT ? FOR UPDATE";
                $params[] = $limit;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $signals = $stmt->fetchAll(PDO::FETCH_OBJ);

                if (!empty($signals)) {
                    // Mark as delivered
                    $ids = array_map(function ($s) {
                        return $s->id;
                    }, $signals);

                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $updateStmt = $pdo->prepare(
                        "UPDATE training_signals SET delivered = TRUE WHERE id IN ($placeholders)"
                    );
                    $updateStmt->execute($ids);
                }

                $pdo->commit();

                // Parse signal_data JSON for each signal
                foreach ($signals as $signal) {
                    $signal->signal_data = json_decode($signal->signal_data, true);
                }

                return $signals;

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        } catch (PDOException $e) {
            error_log("SignalQueue: Database error in getSignals: " . $e->getMessage());
            // Reset connection on error so next call gets a fresh one
            self::$pdo = null;
            return [];
        }
    }

    /**
     * Get pending signal count for a recipient (without marking delivered)
     * Useful for checking if there are signals without consuming them
     *
     * @param string $recipientId Recipient username
     * @return int Number of pending signals
     */
    public static function getPendingCount($recipientId)
    {
        $result = dataQuery(
            "SELECT COUNT(*) as count FROM training_signals
             WHERE recipient_id = ? AND delivered = FALSE",
            [$recipientId]
        );

        return (!self::isError($result) && !empty($result)) ? (int) $result[0]->count : 0;
    }

    /**
     * Delete old signals (cleanup)
     *
     * @param int $deliveredMaxAgeMinutes  Age of delivered signals to delete (default 60)
     * @param int $undeliveredMaxAgeMinutes Age of undelivered signals to delete (default 5)
     * @return array Cleanup statistics
     */
    public static function cleanup($deliveredMaxAgeMinutes = 60, $undeliveredMaxAgeMinutes = 5)
    {
        $stats = ['delivered_deleted' => 0, 'undelivered_deleted' => 0];

        // Delete old delivered signals
        $result = dataQuery(
            "DELETE FROM training_signals
             WHERE delivered = TRUE
             AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$deliveredMaxAgeMinutes]
        );

        // Delete old undelivered signals (stale)
        $result = dataQuery(
            "DELETE FROM training_signals
             WHERE delivered = FALSE
             AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$undeliveredMaxAgeMinutes]
        );

        return $stats;
    }

    /**
     * Clear all signals for a room (on room close)
     *
     * @param string $roomId Room identifier
     * @return bool Success status
     */
    public static function clearRoomSignals($roomId)
    {
        $result = dataQuery(
            "DELETE FROM training_signals WHERE room_id = ?",
            [$roomId]
        );

        return !self::isError($result);
    }

    /**
     * Clear all signals for a participant (on leave)
     *
     * Only clears signals TO the departing participant (they won't poll anymore).
     * Signals FROM the departing participant are preserved so remaining
     * participants can still receive them (e.g., screen-share-stop broadcasts).
     * The periodic cleanup() method handles eventual deletion of stale signals.
     *
     * @param string $participantId Participant username
     * @return bool Success status
     */
    public static function clearParticipantSignals($participantId)
    {
        // Clear signals TO this participant (they won't poll anymore)
        $result = dataQuery(
            "DELETE FROM training_signals WHERE recipient_id = ?",
            [$participantId]
        );

        return !self::isError($result);
    }

    // ==========================================================================
    // Convenience Methods for Common Signal Types
    // ==========================================================================

    /**
     * Send WebRTC offer
     */
    public static function sendOffer($roomId, $senderId, $recipientId, $offerSdp)
    {
        return self::sendToParticipant($roomId, $senderId, $recipientId, self::TYPE_OFFER, [
            'sdp' => $offerSdp,
            'type' => 'offer'
        ]);
    }

    /**
     * Send WebRTC answer
     */
    public static function sendAnswer($roomId, $senderId, $recipientId, $answerSdp)
    {
        return self::sendToParticipant($roomId, $senderId, $recipientId, self::TYPE_ANSWER, [
            'sdp' => $answerSdp,
            'type' => 'answer'
        ]);
    }

    /**
     * Send ICE candidate
     */
    public static function sendIceCandidate($roomId, $senderId, $recipientId, $candidate)
    {
        return self::sendToParticipant($roomId, $senderId, $recipientId, self::TYPE_ICE_CANDIDATE, [
            'candidate' => $candidate
        ]);
    }

    /**
     * Broadcast participant joined
     */
    public static function broadcastParticipantJoined($roomId, $participantId, $role)
    {
        return self::broadcastToRoom($roomId, $participantId, self::TYPE_PARTICIPANT_JOINED, [
            'participantId' => $participantId,
            'role' => $role
        ]);
    }

    /**
     * Broadcast participant left
     */
    public static function broadcastParticipantLeft($roomId, $participantId)
    {
        return self::broadcastToRoom($roomId, $participantId, self::TYPE_PARTICIPANT_LEFT, [
            'participantId' => $participantId
        ]);
    }

    /**
     * Broadcast screen share started
     */
    public static function broadcastScreenShareStart($roomId, $sharerId)
    {
        return self::broadcastToRoom($roomId, $sharerId, self::TYPE_SCREEN_SHARE_START, [
            'sharerId' => $sharerId
        ]);
    }

    /**
     * Broadcast screen share stopped
     */
    public static function broadcastScreenShareStop($roomId, $sharerId)
    {
        return self::broadcastToRoom($roomId, $sharerId, self::TYPE_SCREEN_SHARE_STOP, [
            'sharerId' => $sharerId
        ]);
    }

    /**
     * Broadcast control change
     */
    public static function broadcastControlChange($roomId, $senderId, $newControllerId)
    {
        return self::broadcastToRoom($roomId, $senderId, self::TYPE_CONTROL_CHANGE, [
            'newController' => $newControllerId,
            'previousController' => $senderId
        ]);
    }

    /**
     * Broadcast call started
     */
    public static function broadcastCallStart($roomId, $senderId, $callSid, $activeController = null)
    {
        return self::broadcastToRoom($roomId, $senderId, self::TYPE_CALL_START, [
            'callSid' => $callSid,
            'answeredBy' => $senderId,
            'activeController' => $activeController ?? $senderId
        ]);
    }

    /**
     * Broadcast call ended
     */
    public static function broadcastCallEnd($roomId, $senderId)
    {
        return self::broadcastToRoom($roomId, $senderId, self::TYPE_CALL_END, [
            'endedBy' => $senderId
        ]);
    }

    /**
     * Broadcast conference restart notification
     */
    public static function broadcastConferenceRestart($roomId, $senderId)
    {
        return self::broadcastToRoom($roomId, $senderId, self::TYPE_CONFERENCE_RESTART, [
            'initiatedBy' => $senderId
        ]);
    }

    /**
     * Broadcast mute state update
     */
    public static function broadcastMuteState($roomId, $senderId, $participantId, $isMuted, $reason = null)
    {
        return self::broadcastToRoom($roomId, $senderId, self::TYPE_MUTE_STATE, [
            'participantId' => $participantId,
            'isMuted' => $isMuted,
            'reason' => $reason
        ]);
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
