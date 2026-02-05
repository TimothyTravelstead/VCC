<?php
/**
 * Redis Publisher for VCC Feed Real-time Updates
 *
 * This class publishes state changes to Redis Pub/Sub channels for the Node.js EventSource server.
 * It replaces the polling mechanism in vccFeed.php with event-driven updates.
 *
 * Usage:
 *   require_once(__DIR__ . '/../lib/VCCFeedPublisher.php');
 *   $publisher = new VCCFeedPublisher();
 *   $publisher->publishUserListChange('login', ['username' => $username]);
 *
 * Channels:
 *   - vccfeed:userlist - Volunteer status changes (login, logout, oncall, call state)
 *   - vccfeed:chat:{callerID} - Chat messages for specific chat room
 *   - vccfeed:chatinvite:{volunteerID} - Chat invitations for specific volunteer
 *   - vccfeed:im:{recipientID} - Instant messages (recipientID can be username, 'Admin', or 'All')
 *   - vccfeed:typing:{callerID} - Typing status for chat rooms
 *   - vccfeed:training:{sessionID} - Training session updates
 *
 * @author Claude Code
 * @date October 25, 2025
 */

class VCCFeedPublisher {
    private $redis;
    private $enabled;
    private $debug;

    /**
     * Initialize Redis connection
     * Fails gracefully if Redis is unavailable (old system continues to work)
     */
    public function __construct($debug = false) {
        $this->debug = $debug;
        $this->enabled = false;

        try {
            $this->redis = new Redis();
            $success = $this->redis->connect('127.0.0.1', 6379, 1.0); // 1 second timeout

            if ($success) {
                $this->redis->setOption(Redis::OPT_READ_TIMEOUT, -1);
                $this->enabled = true;

                if ($this->debug) {
                    error_log("VCCFeedPublisher: Redis connection established");
                }
            } else {
                error_log("VCCFeedPublisher: Redis connection failed");
            }
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Redis connection exception - " . $e->getMessage());
            $this->enabled = false;
        }
    }

    /**
     * Check if publisher is enabled and connected
     *
     * @return bool True if Redis connection is active
     */
    public function isEnabled() {
        return $this->enabled;
    }

    /**
     * Publish volunteer list change
     * Triggers when volunteer status changes (login, logout, oncall, call state, etc.)
     *
     * @param string $changeType Type of change (login, logout, oncall, ringing, call_answered, etc.)
     * @param array $data Additional data about the change
     * @return bool True if published successfully
     */
    public function publishUserListChange($changeType, $data = []) {
        if (!$this->enabled) return false;

        try {
            $message = json_encode([
                'type' => $changeType,
                'data' => $data,
                'timestamp' => microtime(true)
            ]);

            $result = $this->redis->publish('vccfeed:userlist', $message);

            if ($this->debug) {
                error_log("VCCFeedPublisher: Published userlist change - $changeType - $result subscribers");
            }

            return $result !== false;
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Error publishing userlist change - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Publish chat message to specific room
     *
     * @param string $callerID The chat room/caller ID
     * @param array $messageData Message data (id, room, name, text, status, etc.)
     * @return bool True if published successfully
     */
    public function publishChatMessage($callerID, $messageData) {
        if (!$this->enabled) return false;

        try {
            $messageData['timestamp'] = microtime(true);
            $message = json_encode($messageData);

            $result = $this->redis->publish("vccfeed:chat:{$callerID}", $message);

            if ($this->debug) {
                error_log("VCCFeedPublisher: Published chat message to {$callerID} - $result subscribers");
            }

            return $result !== false;
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Error publishing chat message - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Publish chat invitation to specific volunteer
     *
     * @param string $volunteerID Volunteer username receiving the invitation
     * @param array $inviteData Invitation data (browser, room, callerID, etc.)
     * @return bool True if published successfully
     */
    public function publishChatInvite($volunteerID, $inviteData) {
        if (!$this->enabled) return false;

        try {
            $inviteData['timestamp'] = microtime(true);
            $message = json_encode($inviteData);

            $result = $this->redis->publish("vccfeed:chatinvite:{$volunteerID}", $message);

            if ($this->debug) {
                error_log("VCCFeedPublisher: Published chat invite to {$volunteerID} - $result subscribers");
            }

            return $result !== false;
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Error publishing chat invite - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Publish instant message
     *
     * @param string $recipientID Recipient username ('Admin', 'All', or specific username)
     * @param array $messageData Message data (id, to, from, text, etc.)
     * @return bool True if published successfully
     */
    public function publishIM($recipientID, $messageData) {
        if (!$this->enabled) return false;

        try {
            $messageData['timestamp'] = microtime(true);
            $message = json_encode($messageData);

            $result = $this->redis->publish("vccfeed:im:{$recipientID}", $message);

            if ($this->debug) {
                error_log("VCCFeedPublisher: Published IM to {$recipientID} - $result subscribers");
            }

            return $result !== false;
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Error publishing IM - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Publish typing status change for chat room
     *
     * @param string $callerID Chat room/caller ID
     * @param bool $isTyping Whether user is currently typing
     * @param bool $isVolunteer True if volunteer is typing, false if caller is typing
     * @return bool True if published successfully
     */
    public function publishTypingStatus($callerID, $isTyping, $isVolunteer = false) {
        if (!$this->enabled) return false;

        try {
            $message = json_encode([
                'callerID' => $callerID,
                'isTyping' => $isTyping,
                'isVolunteer' => $isVolunteer,
                'timestamp' => microtime(true)
            ]);

            $result = $this->redis->publish("vccfeed:typing:{$callerID}", $message);

            if ($this->debug) {
                error_log("VCCFeedPublisher: Published typing status for {$callerID} - $result subscribers");
            }

            return $result !== false;
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Error publishing typing status - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Publish training session update
     *
     * @param string $sessionID Training session ID (usually trainer username)
     * @param array $updateData Training update data
     * @return bool True if published successfully
     */
    public function publishTrainingUpdate($sessionID, $updateData) {
        if (!$this->enabled) return false;

        try {
            $updateData['timestamp'] = microtime(true);
            $message = json_encode($updateData);

            $result = $this->redis->publish("vccfeed:training:{$sessionID}", $message);

            if ($this->debug) {
                error_log("VCCFeedPublisher: Published training update for {$sessionID} - $result subscribers");
            }

            return $result !== false;
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Error publishing training update - " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // CACHE MANAGEMENT METHODS (for Redis-backed polling system)
    // =========================================================================

    /**
     * Rebuild and cache the full user list in Redis
     * Called after any user state change (login, logout, call state, etc.)
     *
     * @return bool True if cache was updated successfully
     */
    public function refreshUserListCache() {
        if (!$this->enabled) return false;

        try {
            // Query current user list (same query as vccFeed.php)
            $users = $this->queryUserList();

            // Store in Redis
            $data = json_encode([
                'timestamp' => time(),
                'users' => $users
            ]);

            $result = $this->redis->set('vcc:userlist', $data);

            if ($this->debug) {
                error_log("VCCFeedPublisher: Refreshed user list cache - " . count($users) . " users");
            }

            return $result !== false;
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Error refreshing user list cache - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Query the current user list from database
     * Replicates the query from vccFeed.php
     *
     * @return array Array of user objects
     */
    private function queryUserList() {
        // Include database functions if not already available
        if (!function_exists('dataQuery')) {
            require_once(dirname(__FILE__) . '/../../private_html/db_login.php');
        }

        $query = "SELECT
            UserID, firstname, lastname, shift, Volunteers.office, Volunteers.desk,
            oncall, Active1, Active2, UserName, ringing, ChatOnly, LoggedOn,
            IncomingCallSid, TraineeID, Muted,
            (SELECT callObject FROM CallRouting WHERE CallSid = Volunteers.IncomingCallSid ORDER BY ID DESC LIMIT 1) as callObject,
            (SELECT callStatus FROM CallRouting WHERE CallSid = Volunteers.IncomingCallSid ORDER BY ID DESC LIMIT 1) as callStatus,
            CASE WHEN LoggedOn = 4 THEN UserName ELSE (
                SELECT V3.UserName FROM Volunteers V3
                WHERE V3.LoggedOn = 4 AND (
                    FIND_IN_SET(Volunteers.UserName, V3.TraineeID) > 0
                    OR V3.TraineeID LIKE CONCAT('%', Volunteers.UserName, '%')
                )
            ) END as TrainerID,
            (SELECT COUNT(*) FROM Volunteers V_Trainee
             WHERE Volunteers.LoggedOn = 4 AND FIND_IN_SET(V_Trainee.UserName, Volunteers.TraineeID) > 0
             AND V_Trainee.oncall = 1) as traineeOnCall,
            chatInvite, groupChatMonitor, SkypeID as pronouns
        FROM Volunteers
        WHERE LoggedOn IN (1,2,4,6,7,8,9)
        ORDER BY Shift, lastname";

        $result = dataQuery($query);

        if (!$result) {
            return [];
        }

        // Process users exactly like vccFeed.php does
        $users = [];
        foreach ($result as $row) {
            $singleUser = [
                'idnum' => $row->UserID,
                'FirstName' => $row->firstname,
                'LastName' => $row->lastname,
                'Shift' => $row->shift,
                'Office' => $row->office,
                'Desk' => $row->desk,
                'OnCall' => $row->oncall,
                'Chat1' => $row->Active1,
                'Chat2' => $row->Active2,
                'UserName' => $row->UserName,
                'ringing' => $row->ringing ? substr($row->ringing, 0, 7) : null,
                'adminRinging' => $row->ringing,
                'ChatOnly' => (int)$row->ChatOnly,
                'AdminLoggedOn' => (int)$row->LoggedOn,
                'IncomingCallSid' => $row->IncomingCallSid,
                'TraineeID' => $row->TraineeID,
                'Muted' => (int)$row->Muted,
                'CallObject' => $row->callObject,
                'CallStatus' => $row->callStatus,
                'TrainerID' => $row->TrainerID,
                'traineeOnCall' => (int)$row->traineeOnCall,
                'groupChatMonitor' => (int)$row->groupChatMonitor,
                'pronouns' => $row->pronouns
            ];

            // Clear CallObject if Muted (same as vccFeed.php)
            if ($singleUser['Muted']) {
                $singleUser['CallObject'] = null;
            }

            // Process Shift (same as vccFeed.php)
            $singleUser['Shift'] = match((int)$singleUser['Shift']) {
                0 => "Closed",
                1 => "1st",
                2 => "2nd",
                3 => "3rd",
                4 => "4th",
                default => "Closed"
            };

            // Process Desk/CallerType (same as vccFeed.php)
            if ($singleUser['Desk'] == 0) {
                $singleUser['CallerType'] = "Both";
            } elseif ($singleUser['Desk'] == 1) {
                $singleUser['CallerType'] = "Chat";
                $singleUser['ChatOnly'] = 1;
            } elseif ($singleUser['Desk'] == 2) {
                $singleUser['CallerType'] = "Call";
                $singleUser['ChatOnly'] = 0;
            }

            // Process OnCall Status (same as vccFeed.php)
            if ($singleUser['ChatOnly'] == 1) {
                $singleUser['OnCall'] = "Chat Only";
                $singleUser['Desk'] = "Chat Only";
            } elseif ($singleUser['OnCall'] == 1) {
                $singleUser['OnCall'] = "YES";
            } elseif ($singleUser['AdminLoggedOn'] == 4 && $singleUser['traineeOnCall'] > 0) {
                $singleUser['OnCall'] = "YES";
            } elseif ($singleUser['ringing'] == null) {
                $singleUser['OnCall'] = " ";
            } else {
                $singleUser['OnCall'] = $singleUser['ringing'];
            }

            // Process Chat Status (same as vccFeed.php)
            if (($singleUser['Chat1'] != null && $singleUser['Chat1'] != "Blocked") &&
                ($singleUser['Chat2'] != null && $singleUser['Chat2'] != "Blocked")) {
                $singleUser['Chat'] = "YES - 2";
            } elseif (($singleUser['Chat1'] != null && $singleUser['Chat1'] != "Blocked") ||
                     ($singleUser['Chat2'] != null && $singleUser['Chat2'] != "Blocked")) {
                $singleUser['Chat'] = "YES - 1";
            } else {
                $singleUser['Chat'] = " ";
            }

            // Handle Group Chat Monitor (same as vccFeed.php)
            if ($singleUser['groupChatMonitor'] == 1 && $singleUser['AdminLoggedOn'] == 8) {
                $singleUser['Chat'] = "Group Chat";
                $singleUser['Desk'] = "Group Chat";
            }

            $users[] = $singleUser;
        }

        return $users;
    }

    /**
     * Queue an event for a specific user (chat invites, IMs, etc.)
     * Events are stored in a Redis list and retrieved/cleared on poll
     *
     * @param string $userID The volunteer's username
     * @param string $eventType Type of event (chatInvite, IM, trainingUpdate, etc.)
     * @param array $data Event data
     * @return bool True if queued successfully
     */
    public function queueUserEvent($userID, $eventType, $data) {
        if (!$this->enabled) return false;

        try {
            $event = json_encode([
                'type' => $eventType,
                'data' => $data,
                'timestamp' => time()
            ]);

            $key = "vcc:user:{$userID}:events";
            $this->redis->rPush($key, $event);
            $this->redis->expire($key, 300); // 5 minute TTL

            if ($this->debug) {
                error_log("VCCFeedPublisher: Queued {$eventType} event for {$userID}");
            }

            return true;
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Error queueing user event - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Store a chat message in Redis for polling retrieval
     *
     * @param string $callerID The chat room/caller ID
     * @param array $messageData Message data
     * @return bool True if stored successfully
     */
    public function storeChatMessage($callerID, $messageData) {
        if (!$this->enabled) return false;

        try {
            $messageData['timestamp'] = time();
            $key = "vcc:chat:{$callerID}:messages";

            $this->redis->rPush($key, json_encode($messageData));
            $this->redis->lTrim($key, -50, -1); // Keep last 50 messages
            $this->redis->expire($key, 3600); // 1 hour TTL

            if ($this->debug) {
                error_log("VCCFeedPublisher: Stored chat message for room {$callerID}");
            }

            return true;
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Error storing chat message - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update typing status in Redis (auto-expires after 10 seconds)
     *
     * @param string $callerID The chat room/caller ID
     * @param bool $isTyping Whether someone is typing
     * @param string $userName Who is typing
     * @return bool True if updated successfully
     */
    public function storeTypingStatus($callerID, $isTyping, $userName) {
        if (!$this->enabled) return false;

        try {
            $key = "vcc:chat:{$callerID}:typing";

            if ($isTyping) {
                $this->redis->setex($key, 10, json_encode([
                    'userName' => $userName,
                    'timestamp' => time()
                ]));
            } else {
                $this->redis->del($key);
            }

            return true;
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Error storing typing status - " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // RINGING STATE METHODS (for fast-poll incoming call detection)
    // =========================================================================

    /**
     * Set ringing state for a volunteer (called by Twilio webhook)
     * This triggers immediate notification via fast-poll
     *
     * @param string $userID The volunteer's username
     * @param string $callSid The Twilio call SID
     * @param array $callerInfo Optional caller information
     * @return bool True if set successfully
     */
    public function setRinging($userID, $callSid, $callerInfo = []) {
        if (!$this->enabled) return false;

        try {
            $key = "vcc:ringing:{$userID}";
            $data = json_encode([
                'ringing' => true,
                'callSid' => $callSid,
                'callerInfo' => $callerInfo,
                'timestamp' => time()
            ]);

            $this->redis->setex($key, 60, $data); // 60 second TTL

            if ($this->debug) {
                error_log("VCCFeedPublisher: Set ringing state for {$userID} - CallSid: {$callSid}");
            }

            return true;
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Error setting ringing state - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear ringing state for a volunteer (called when answered/rejected)
     *
     * @param string $userID The volunteer's username
     * @return bool True if cleared successfully
     */
    public function clearRinging($userID) {
        if (!$this->enabled) return false;

        try {
            $key = "vcc:ringing:{$userID}";
            $this->redis->del($key);

            if ($this->debug) {
                error_log("VCCFeedPublisher: Cleared ringing state for {$userID}");
            }

            return true;
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Error clearing ringing state - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear ringing state for multiple volunteers
     *
     * @param array $userIDs Array of volunteer usernames
     * @return bool True if all cleared successfully
     */
    public function clearRingingMultiple($userIDs) {
        if (!$this->enabled) return false;

        try {
            foreach ($userIDs as $userID) {
                $this->redis->del("vcc:ringing:{$userID}");
            }

            if ($this->debug) {
                error_log("VCCFeedPublisher: Cleared ringing state for " . count($userIDs) . " users");
            }

            return true;
        } catch (Exception $e) {
            error_log("VCCFeedPublisher: Error clearing multiple ringing states - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Close Redis connection
     */
    public function __destruct() {
        if ($this->enabled && $this->redis) {
            try {
                $this->redis->close();
            } catch (Exception $e) {
                // Ignore errors during destruction
            }
        }
    }
}
