/**
 * TrainingSignalingClient - Database-backed signaling for WebRTC
 *
 * Replaces file-based signaling with database-backed endpoints.
 * Features:
 * - 500ms polling interval for faster WebRTC negotiation
 * - Server-authoritative state via getSessionState endpoint
 * - Simplified muting via setMuteState endpoint
 *
 * Usage:
 *   const signaling = new TrainingSignalingClient({
 *       participantId: 'username',
 *       trainerId: 'trainer_username',
 *       role: 'trainer' | 'trainee',
 *       onSignal: (signal) => { ... },
 *       onStateChange: (state) => { ... }
 *   });
 *
 *   await signaling.connect();
 *   signaling.sendSignal({ type: 'offer', ... });
 *   signaling.disconnect();
 */

class TrainingSignalingClient {
    constructor(options = {}) {
        this.participantId = options.participantId;
        this.trainerId = options.trainerId;
        this.role = options.role;
        this.roomId = options.roomId || this.trainerId;

        // Callbacks
        this.onSignal = options.onSignal || (() => {});
        this.onStateChange = options.onStateChange || (() => {});
        this.onParticipantJoin = options.onParticipantJoin || (() => {});
        this.onParticipantLeave = options.onParticipantLeave || (() => {});
        this.onMuteStateChange = options.onMuteStateChange || (() => {});
        this.onError = options.onError || console.error;

        // State
        this.connected = false;
        this.pollInterval = null;
        this.statePollInterval = null;
        this.lastSessionState = null;

        // Configuration
        this.signalPollIntervalMs = 500; // Fast polling for WebRTC
        this.statePollIntervalMs = 2000; // Slower polling for session state

        // Endpoints
        this.endpoints = {
            signalSend: '/trainingShare3/signalSend.php',
            signalPoll: '/trainingShare3/signalPollDB.php',
            roomJoin: '/trainingShare3/roomJoin.php',
            roomLeave: '/trainingShare3/roomLeave.php',
            getSessionState: '/trainingShare3/getSessionState.php',
            setMuteState: '/trainingShare3/setMuteState.php',
            getMuteState: '/trainingShare3/getMuteState.php',
            bulkMute: '/trainingShare3/bulkMute.php',
            transitionState: '/trainingShare3/transitionState.php'
        };

        console.log(`📡 TrainingSignalingClient initialized: ${this.role} ${this.participantId} in room ${this.roomId}`);
    }

    /**
     * Connect to the training room
     */
    async connect() {
        if (this.connected) {
            console.log('📡 Already connected');
            return;
        }

        try {
            // Join the room
            const response = await fetch(this.endpoints.roomJoin, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    roomId: this.roomId,
                    callSid: null // Will be set later when joining conference
                }),
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`Failed to join room: ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to join room');
            }

            console.log(`📡 Joined room ${this.roomId}:`, data);

            this.connected = true;

            // Notify about existing participants
            if (data.participants) {
                data.participants.forEach(p => {
                    if (p.id !== this.participantId) {
                        this.onParticipantJoin(p);
                    }
                });
            }

            // Notify about initial session state
            if (data.sessionState) {
                this.lastSessionState = data.sessionState;
                this.onStateChange(data.sessionState);
            }

            // Start polling
            this.startSignalPolling();
            this.startStatePolling();

            return data;

        } catch (error) {
            console.error('📡 Connection error:', error);
            this.onError(error);
            throw error;
        }
    }

    /**
     * Disconnect from the training room
     */
    async disconnect() {
        if (!this.connected) {
            return;
        }

        // Stop polling
        this.stopSignalPolling();
        this.stopStatePolling();

        try {
            const response = await fetch(this.endpoints.roomLeave, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ roomId: this.roomId }),
                credentials: 'same-origin'
            });

            if (response.ok) {
                console.log(`📡 Left room ${this.roomId}`);
            }
        } catch (error) {
            console.error('📡 Error leaving room:', error);
        }

        this.connected = false;
    }

    /**
     * Send a signal to a recipient or broadcast
     */
    async sendSignal(signal, recipientId = null) {
        if (!this.connected) {
            console.warn('📡 Not connected, cannot send signal');
            return false;
        }

        try {
            const payload = {
                type: signal.type,
                ...signal,
                roomId: this.roomId
            };

            if (recipientId) {
                payload.to = recipientId;
            }

            const response = await fetch(this.endpoints.signalSend, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`Failed to send signal: ${response.status}`);
            }

            const data = await response.json();
            console.log(`📡 Signal sent: ${signal.type}`, data);
            return data.success;

        } catch (error) {
            console.error('📡 Error sending signal:', error);
            this.onError(error);
            return false;
        }
    }

    /**
     * Start polling for signals (500ms interval)
     */
    startSignalPolling() {
        if (this.pollInterval) {
            return;
        }

        console.log('📡 Starting signal polling (500ms)');

        // Poll immediately
        this.pollSignals();

        // Then poll every 500ms
        this.pollInterval = setInterval(() => {
            this.pollSignals();
        }, this.signalPollIntervalMs);
    }

    /**
     * Stop signal polling
     */
    stopSignalPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
            console.log('📡 Stopped signal polling');
        }
    }

    /**
     * Poll for pending signals
     */
    async pollSignals() {
        if (!this.connected) {
            return;
        }

        try {
            const url = `${this.endpoints.signalPoll}?roomId=${encodeURIComponent(this.roomId)}`;
            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-cache'
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (data.messages && data.messages.length > 0) {
                console.log(`📡 Received ${data.messages.length} signals`);
                data.messages.forEach(signal => {
                    this.handleSignal(signal);
                });
            }

        } catch (error) {
            // Silently handle polling errors
        }
    }

    /**
     * Handle incoming signal
     */
    handleSignal(signal) {
        console.log(`📡 Received: ${signal.type} from ${signal.from}`);

        // Handle built-in signal types
        switch (signal.type) {
            case 'participant-joined':
                this.onParticipantJoin({
                    id: signal.participantId,
                    role: signal.role
                });
                break;

            case 'participant-left':
                this.onParticipantLeave({
                    id: signal.participantId
                });
                break;

            case 'mute-state':
                this.onMuteStateChange({
                    participantId: signal.participantId,
                    isMuted: signal.isMuted,
                    reason: signal.reason
                });
                break;

            case 'bulk-mute':
                // Re-fetch full mute state
                this.fetchMuteState();
                break;

            default:
                // Pass to callback for WebRTC signals etc.
                this.onSignal(signal);
        }
    }

    /**
     * Start polling for session state (2 second interval)
     */
    startStatePolling() {
        if (this.statePollInterval) {
            return;
        }

        console.log('📡 Starting state polling (2s)');

        // Poll immediately
        this.pollSessionState();

        // Then poll every 2 seconds
        this.statePollInterval = setInterval(() => {
            this.pollSessionState();
        }, this.statePollIntervalMs);
    }

    /**
     * Stop state polling
     */
    stopStatePolling() {
        if (this.statePollInterval) {
            clearInterval(this.statePollInterval);
            this.statePollInterval = null;
            console.log('📡 Stopped state polling');
        }
    }

    /**
     * Poll for session state
     */
    async pollSessionState() {
        if (!this.connected) {
            return;
        }

        try {
            const url = `${this.endpoints.getSessionState}?trainerId=${encodeURIComponent(this.trainerId)}`;
            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-cache'
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (!data.exists) {
                return;
            }

            // Check for changes
            if (this.stateChanged(data)) {
                console.log('📡 Session state changed:', data);
                this.lastSessionState = data;
                this.onStateChange(data);
            }

        } catch (error) {
            // Silently handle polling errors
        }
    }

    /**
     * Check if session state has changed
     */
    stateChanged(newState) {
        if (!this.lastSessionState) {
            return true;
        }

        // Compare key fields
        return (
            this.lastSessionState.state !== newState.state ||
            this.lastSessionState.activeController !== newState.activeController ||
            this.lastSessionState.externalCall?.active !== newState.externalCall?.active
        );
    }

    /**
     * Fetch current mute state
     */
    async fetchMuteState() {
        try {
            const url = `${this.endpoints.getMuteState}?trainerId=${encodeURIComponent(this.trainerId)}`;
            const response = await fetch(url, {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                return null;
            }

            return await response.json();

        } catch (error) {
            console.error('📡 Error fetching mute state:', error);
            return null;
        }
    }

    /**
     * Set mute state for a participant
     */
    async setMuteState(participantId, muted, reason = null, callSid = null) {
        try {
            const response = await fetch(this.endpoints.setMuteState, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    trainerId: this.trainerId,
                    participantId: participantId,
                    muted: muted,
                    callSid: callSid,
                    reason: reason
                }),
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`Failed to set mute state: ${response.status}`);
            }

            const data = await response.json();
            console.log(`📡 Mute state set:`, data);
            return data;

        } catch (error) {
            console.error('📡 Error setting mute state:', error);
            this.onError(error);
            return null;
        }
    }

    /**
     * Bulk mute/unmute non-controllers
     */
    async bulkMute(action, controllerId = null, reason = null) {
        try {
            const response = await fetch(this.endpoints.bulkMute, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    trainerId: this.trainerId,
                    action: action, // 'mute_non_controllers' or 'unmute_all'
                    controllerId: controllerId,
                    reason: reason
                }),
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`Failed to bulk mute: ${response.status}`);
            }

            const data = await response.json();
            console.log(`📡 Bulk mute result:`, data);
            return data;

        } catch (error) {
            console.error('📡 Error with bulk mute:', error);
            this.onError(error);
            return null;
        }
    }

    /**
     * Trigger a session state transition
     */
    async transitionState(event, options = {}) {
        try {
            const response = await fetch(this.endpoints.transitionState, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    trainerId: this.trainerId,
                    event: event,
                    callSid: options.callSid,
                    conferenceSid: options.conferenceSid,
                    controllerId: options.controllerId
                }),
                credentials: 'same-origin'
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || `Transition failed: ${response.status}`);
            }

            const data = await response.json();
            console.log(`📡 State transition:`, data);
            return data;

        } catch (error) {
            console.error('📡 Error transitioning state:', error);
            this.onError(error);
            return null;
        }
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TrainingSignalingClient;
}

// Also attach to window for browser use
if (typeof window !== 'undefined') {
    window.TrainingSignalingClient = TrainingSignalingClient;
}
