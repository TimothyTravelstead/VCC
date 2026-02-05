/**
 * Training WebSocket Client
 * Connects to the Ratchet-based WebSocket server for real-time training communication
 */

class TrainingWebSocketClient {
    constructor(options = {}) {
        this.options = {
            url: 'ws://localhost:8080',
            reconnectInterval: 3000,
            maxReconnectAttempts: 10,
            heartbeatInterval: 30000,
            ...options
        };
        
        this.ws = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.heartbeatTimer = null;
        this.messageQueue = [];
        this.eventHandlers = {};
        
        // Bind methods to preserve 'this' context
        this.onOpen = this.onOpen.bind(this);
        this.onMessage = this.onMessage.bind(this);
        this.onClose = this.onClose.bind(this);
        this.onError = this.onError.bind(this);
        
        console.log('🔧 TrainingWebSocketClient initialized');
    }

    /**
     * Connect to the WebSocket server
     * @param {string} roomId - Training room ID
     * @param {string} userId - User ID from session
     * @param {string} sessionId - Session ID for authentication
     */
    connect(roomId, userId, sessionId) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            console.log('⚠️ Already connected to WebSocket');
            return;
        }

        this.roomId = roomId;
        this.userId = userId;
        this.sessionId = sessionId;

        const url = `${this.options.url}?room=${encodeURIComponent(roomId)}&user=${encodeURIComponent(userId)}&sessionId=${encodeURIComponent(sessionId)}`;
        
        console.log(`🔌 Connecting to WebSocket: ${url}`);
        
        try {
            this.ws = new WebSocket(url);
            this.ws.onopen = this.onOpen;
            this.ws.onmessage = this.onMessage;
            this.ws.onclose = this.onClose;
            this.ws.onerror = this.onError;
        } catch (error) {
            console.error('❌ WebSocket connection failed:', error);
            this.emit('error', error);
        }
    }

    /**
     * Disconnect from WebSocket server
     */
    disconnect() {
        console.log('🔌 Disconnecting from WebSocket');
        
        this.clearHeartbeat();
        this.reconnectAttempts = this.options.maxReconnectAttempts; // Prevent reconnection
        
        if (this.ws) {
            this.ws.close(1000, 'Client disconnect');
        }
    }

    /**
     * Send a message to the WebSocket server
     * @param {Object} message - Message object
     */
    send(message) {
        if (!this.isConnected) {
            console.log('📤 Queuing message (not connected):', message);
            this.messageQueue.push(message);
            return;
        }

        try {
            const jsonMessage = JSON.stringify(message);
            this.ws.send(jsonMessage);
            console.log('📤 Sent message:', message.type);
        } catch (error) {
            console.error('❌ Failed to send message:', error);
            this.emit('error', error);
        }
    }

    /**
     * Send WebRTC offer
     * @param {RTCSessionDescription} offer - WebRTC offer
     * @param {string} targetUserId - Target user ID (optional for broadcast)
     */
    sendOffer(offer, targetUserId = null) {
        this.send({
            type: 'offer',
            offer: offer,
            target: targetUserId
        });
    }

    /**
     * Send WebRTC answer
     * @param {RTCSessionDescription} answer - WebRTC answer
     * @param {string} targetUserId - Target user ID
     */
    sendAnswer(answer, targetUserId) {
        this.send({
            type: 'answer',
            answer: answer,
            target: targetUserId
        });
    }

    /**
     * Send ICE candidate
     * @param {RTCIceCandidate} candidate - ICE candidate
     * @param {string} targetUserId - Target user ID (optional for broadcast)
     */
    sendIceCandidate(candidate, targetUserId = null) {
        this.send({
            type: 'ice-candidate',
            candidate: candidate,
            target: targetUserId
        });
    }

    /**
     * Send screen sharing start signal
     */
    sendScreenShareStart() {
        this.send({
            type: 'screen-share-start'
        });
    }

    /**
     * Send screen sharing stop signal
     */
    sendScreenShareStop() {
        this.send({
            type: 'screen-share-stop'
        });
    }

    /**
     * Send trainer active signal (trainers only)
     */
    sendTrainerActive() {
        this.send({
            type: 'trainer-active'
        });
    }

    /**
     * Add event listener
     * @param {string} event - Event name
     * @param {Function} handler - Event handler function
     */
    on(event, handler) {
        if (!this.eventHandlers[event]) {
            this.eventHandlers[event] = [];
        }
        this.eventHandlers[event].push(handler);
    }

    /**
     * Remove event listener
     * @param {string} event - Event name
     * @param {Function} handler - Event handler function
     */
    off(event, handler) {
        if (!this.eventHandlers[event]) return;
        
        const index = this.eventHandlers[event].indexOf(handler);
        if (index > -1) {
            this.eventHandlers[event].splice(index, 1);
        }
    }

    /**
     * Emit event to registered handlers
     * @param {string} event - Event name
     * @param {*} data - Event data
     */
    emit(event, data) {
        if (!this.eventHandlers[event]) return;
        
        this.eventHandlers[event].forEach(handler => {
            try {
                handler(data);
            } catch (error) {
                console.error(`❌ Error in event handler for ${event}:`, error);
            }
        });
    }

    // WebSocket event handlers

    onOpen(event) {
        console.log('✅ WebSocket connected');
        this.isConnected = true;
        this.reconnectAttempts = 0;
        
        // Send queued messages
        while (this.messageQueue.length > 0) {
            const message = this.messageQueue.shift();
            this.send(message);
        }
        
        // Start heartbeat
        this.startHeartbeat();
        
        this.emit('connected', event);
    }

    onMessage(event) {
        try {
            const message = JSON.parse(event.data);
            console.log('📥 Received message:', message.type);
            
            // Handle built-in message types
            switch (message.type) {
                case 'welcome':
                    this.handleWelcome(message);
                    break;
                case 'error':
                    console.error('🚨 Server error:', message.message);
                    this.emit('error', new Error(message.message));
                    break;
                case 'pong':
                    // Heartbeat response
                    break;
                default:
                    // Emit custom events
                    this.emit(message.type, message);
                    this.emit('message', message);
                    break;
            }
        } catch (error) {
            console.error('❌ Failed to parse message:', error);
        }
    }

    onClose(event) {
        console.log(`🔌 WebSocket disconnected (code: ${event.code}, reason: ${event.reason})`);
        this.isConnected = false;
        this.clearHeartbeat();
        
        this.emit('disconnected', event);
        
        // Attempt reconnection if not intentional disconnect
        if (event.code !== 1000 && this.reconnectAttempts < this.options.maxReconnectAttempts) {
            this.scheduleReconnect();
        }
    }

    onError(error) {
        console.error('❌ WebSocket error:', error);
        this.emit('error', error);
    }

    // Helper methods

    handleWelcome(message) {
        console.log(`👋 Welcome! User: ${message.userId}, Role: ${message.role}, Room: ${message.roomId}`);
        console.log('👥 Participants:', message.participants);
        
        this.userRole = message.role;
        this.participants = message.participants;
        
        this.emit('welcome', message);
    }

    scheduleReconnect() {
        this.reconnectAttempts++;
        const delay = this.options.reconnectInterval * Math.pow(1.5, this.reconnectAttempts - 1);
        
        console.log(`🔄 Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts}/${this.options.maxReconnectAttempts})`);
        
        setTimeout(() => {
            if (this.reconnectAttempts <= this.options.maxReconnectAttempts) {
                this.connect(this.roomId, this.userId, this.sessionId);
            }
        }, delay);
    }

    startHeartbeat() {
        this.clearHeartbeat();
        
        this.heartbeatTimer = setInterval(() => {
            if (this.isConnected) {
                this.send({ type: 'ping' });
            }
        }, this.options.heartbeatInterval);
    }

    clearHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
    }

    /**
     * Get connection status
     * @returns {Object} Status information
     */
    getStatus() {
        return {
            connected: this.isConnected,
            reconnectAttempts: this.reconnectAttempts,
            queuedMessages: this.messageQueue.length,
            userRole: this.userRole,
            participants: this.participants,
            roomId: this.roomId,
            userId: this.userId
        };
    }
}

// Make available globally
window.TrainingWebSocketClient = TrainingWebSocketClient;

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TrainingWebSocketClient;
}