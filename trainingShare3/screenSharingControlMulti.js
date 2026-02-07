/**
 * Multi-Trainee Screen Sharing Control
 * Supports 1 trainer + multiple trainees using PHP-based signaling
 */
class MultiTraineeScreenSharing {
    constructor(options = {}) {
        // Get participant info from page or use defaults for development
        this.trainerId = options.trainerId || 
                        document.getElementById("trainer")?.value || 
                        document.getElementById("trainerID")?.value ||
                        'TestTrainer'; // Default for development
        this.participantId = options.participantId || 
                           document.getElementById("volunteerID")?.value ||
                           'TestParticipant'; // Default for development
        this.role = options.role || this.determineRole();
        this.roomId = options.roomId || this.trainerId; // Use trainer ID as room ID
        
        // Debug logging
        this.debugLog("CONSTRUCTOR", {
            trainerId: this.trainerId,
            participantId: this.participantId,
            role: this.role,
            roomId: this.roomId,
            options: options
        });
        
        // UI elements
        this.localVideo = document.getElementById("localVideo");
        this.remoteVideo = document.getElementById("remoteVideo");
        if (this.remoteVideo) {
            this.remoteVideo.poster = "trainingShare3/poster.png";
        }
        
        // WebRTC configuration
        this.rtcConfiguration = {
            'iceServers': [
                {'urls': 'stun:stun.stunprotocol.org:3478'},
                {'urls': 'stun:stun.l.google.com:19302'},
                {'urls': 'stun:stun1.l.google.com:19302'},
                {'urls': 'stun:stun2.l.google.com:19302'},
                {
                    'urls': 'turns:match.volunteerlogin.org:5349',
                    'username': 'travelstead@mac.com',
                    'credential': 'BarbMassapequa99+'
                }
            ]
        };
        
        // State management
        this.participants = new Map(); // participantId -> {role, peerConnection, status}
        this.localStream = null;
        this.isSharing = false;
        this.eventSource = null;
        this.currentSharer = null;
        this.pendingParticipants = []; // Queue for participants that join before stream is ready
        this.streamReadyListenerSet = false;

        // Session versioning - eliminates stale signal race conditions structurally
        this.sessionVersion = null;

        // Connection health monitoring
        this.healthCheckInterval = null;
        this.healthCheckIntervalMs = 3000; // Check every 3 seconds
        this.lastVideoFrameTime = 0;
        this.connectionAttempts = 0;
        this.maxConnectionAttempts = 5;
        this.reconnectDelayMs = 2000;
        this.isReconnecting = false;

        // Room join state (prevent duplicates)
        this.joinInProgress = false;
        this.hasJoinedRoom = false;

        // Message queue for sequential processing - eliminates handler race conditions
        this.messageQueue = [];
        this.processingMessage = false;

        // Initialize
        this.init();
    }
    
    debugLog(stage, data = {}) {
        const timestamp = new Date().toISOString();
        const logEntry = {
            timestamp,
            stage,
            role: this.role,
            participantId: this.participantId,
            roomId: this.roomId,
            data
        };
        console.log(`🔍 [SCREEN_SHARING_DEBUG] ${stage}:`, logEntry);
        
        // Also store in window for inspection
        if (!window.screenSharingDebugLog) {
            window.screenSharingDebugLog = [];
        }
        window.screenSharingDebugLog.push(logEntry);
    }
    
    determineRole() {
        // Try multiple field names for compatibility, with defaults
        const trainerId = document.getElementById("trainer")?.value || 
                         document.getElementById("trainerID")?.value ||
                         'TestTrainer';
        const participantId = document.getElementById("volunteerID")?.value ||
                             'TestParticipant';
        return participantId === trainerId ? 'trainer' : 'trainee';
    }
    
    async init() {
        this.debugLog("INIT_START");
        console.log(`Initializing multi-trainee screen sharing as ${this.role} in room ${this.roomId}`);
        
        try {
            // Setup signaling first
            this.setupSignaling();
            
            // For trainers, automatically start screen sharing on init
            // For trainees, check if they should start (if they have control)
            if (this.role === 'trainer') {
                console.log('Trainer detected - auto-starting screen share');
                // Give a brief delay for signaling to be ready
                setTimeout(async () => {
                    try {
                        await this.setupLocalStream();
                        // Note: isSharing is now set inside setupLocalStream() before processStreamReady()
                        this.sendSignal({
                            type: 'screen-share-start',
                            participantId: this.participantId,
                            participantRole: this.role
                        });
                        console.log('Trainer screen sharing started automatically');

                        // Also trigger join-room after screen share starts
                        console.log('🚀 Trainer joining room after screen share start');
                        this.joinRoom();
                    } catch (error) {
                        console.error('Failed to auto-start screen sharing:', error);
                        // User may have denied permission - that's ok
                    }
                }, 1000);
            } else {
                // Trainees: Just join room and wait for signals
                // No polling - rely on screen-share-start signal from trainer
                console.log('Trainee detected - waiting for trainer screen share signal');
                // Join room immediately
                setTimeout(() => {
                    this.joinRoom();
                }, 500);
            }
            
            this.debugLog("INIT_SUCCESS");
        } catch (error) {
            this.debugLog("INIT_ERROR", { error: error.message });
            console.error('Failed to initialize screen sharing:', error);
        }
    }
    
    async setupLocalStream() {
        this.debugLog("SETUP_LOCAL_STREAM_START", { role: this.role });

        // Anyone can get screen sharing stream when they have control
        const constraints = {
            video: {
                cursor: "always",
                displaySurface: "application",
                logicalSurface: true
            },
            audio: false
        };

        this.debugLog("REQUESTING_DISPLAY_MEDIA", { constraints, role: this.role });

        try {
            this.localStream = await navigator.mediaDevices.getDisplayMedia(constraints);
            this.debugLog("DISPLAY_MEDIA_ACQUIRED", { streamId: this.localStream.id, role: this.role });

            if (this.localVideo) {
                this.localVideo.srcObject = this.localStream;
                this.localVideo.style.display = "none";  // Hide local preview - user doesn't need to see their own share
                this.debugLog("LOCAL_VIDEO_SET");
            } else {
                this.debugLog("NO_LOCAL_VIDEO_ELEMENT");
            }
            console.log(`${this.role} screen sharing stream acquired`);

            // CRITICAL: Set isSharing BEFORE processing pending participants
            // This prevents race condition where new joins check isSharing
            this.isSharing = true;

            // Now process pending participants that joined while we were getting permission
            if (this.role === 'trainer') {
                this.processStreamReady();
            }
        } catch (error) {
            this.debugLog("DISPLAY_MEDIA_ERROR", { error: error.message });
            console.error('Failed to get display media:', error);
            throw error;
        }
    }
    
    setupSignaling() {
        this.debugLog("SETUP_SIGNALING_START");
        try {
            console.log("Using database-backed signaling for screen sharing (500ms polling)");

            // Database-backed signaling only - no legacy fallback
            this.useDBSignaling = true;
            this.setupTrainingPolling();

        } catch (error) {
            this.debugLog("SETUP_SIGNALING_ERROR", { error: error.message });
            console.error('🚨 CRITICAL: Failed to setup signaling:', error);
            this._showSignalingError('Signaling setup failed. Please refresh the page.');
        }
    }

    /**
     * Show signaling error to user
     */
    _showSignalingError(message) {
        console.error('🚨 Signaling Error:', message);
        // Try to show alert if available
        if (typeof alert === 'function') {
            alert(`Training Session Error: ${message}`);
        }
    }

    setupTrainingPolling() {
        this.debugLog("SETUP_TRAINING_POLLING_START");

        // Database-backed signaling with 500ms polling for faster WebRTC negotiation
        // NO legacy fallback - the file-based system is unreliable
        this.pollingUrl = `/trainingShare3/signalPollDB.php?roomId=${encodeURIComponent(this.roomId)}`;
        this.signalingUrl = `/trainingShare3/signalSend.php`;
        this.pollingIntervalMs = 500;
        console.log(`📡 Using DB-backed signaling (500ms polling) - NO LEGACY FALLBACK`);

        console.log(`📡 Polling URL: ${this.pollingUrl}`);
        console.log(`🔑 Current session auth:`, document.cookie.includes('PHPSESSID'));

        this.pollingActive = false;
        this.pollingInterval = null;
        this.pollingErrorCount = 0;
        this.maxPollingErrors = 10; // Show error after 10 consecutive failures

        // Setup send signal method
        this.setupSendSignal();

        // Start polling immediately
        this.startPolling();

        // Initial join room
        setTimeout(() => {
            this.joinRoom();
            if (this.role === 'trainee') {
                setTimeout(() => {
                    this.checkForExistingScreenShare();
                }, 2000);
            }
        }, 500);
    }

    startPolling() {
        if (this.pollingActive) {
            console.log(`⏭️ startPolling called but already active`);
            return; // Already polling
        }

        this.pollingActive = true;
        console.log(`🔄 Starting signal polling (${this.pollingIntervalMs}ms) for ${this.participantId}...`);
        console.log(`🔄 Polling URL: ${this.pollingUrl}`);

        // Poll immediately, then at configured interval
        this.pollForSignals();
        this.pollingInterval = setInterval(() => {
            this.pollForSignals();
        }, this.pollingIntervalMs);
    }
    
    stopPolling() {
        console.log(`⛔ stopPolling called for ${this.participantId}`);
        console.trace('stopPolling stack trace');  // Show who called stopPolling
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
        this.pollingActive = false;
        // Also stop health check when stopping all polling
        this.stopVideoHealthCheck();
        console.log("⏸️ Stopped signal polling and health check");
    }
    
    async pollForSignals() {
        if (!this.pollingActive) {
            return;
        }

        try {
            const response = await fetch(this.pollingUrl, {
                credentials: 'same-origin',
                cache: 'no-cache'
            });

            if (!response.ok) {
                this.pollingErrorCount++;
                console.error(`❌ Polling failed (${this.pollingErrorCount}/${this.maxPollingErrors}): ${response.status} ${response.statusText}`);

                if (this.pollingErrorCount >= this.maxPollingErrors) {
                    this._showSignalingError(`Signal polling failed ${this.maxPollingErrors} times. Training session communication may be impaired. Please refresh the page.`);
                    this.pollingErrorCount = 0; // Reset to allow continued attempts
                }
                return;
            }

            const data = await response.json();
            this.pollingErrorCount = 0; // Reset on success

            // Verify session version matches (structural race condition protection)
            if (data.sessionVersion && this.sessionVersion && data.sessionVersion !== this.sessionVersion) {
                console.warn(`⚠️ Session version mismatch (server: ${data.sessionVersion}, local: ${this.sessionVersion}) - session may have restarted`);
                // Update our version if server has a newer session
                this.sessionVersion = data.sessionVersion;
            }

            if (data.messages && data.messages.length > 0) {
                console.log(`📬 Received ${data.messages.length} messages`);
                // STRUCTURAL FIX: Queue messages for sequential processing
                // This eliminates race conditions from parallel message handling
                this.enqueueMessages(data.messages);
            }

        } catch (error) {
            this.pollingErrorCount++;
            console.error(`❌ Polling error (${this.pollingErrorCount}/${this.maxPollingErrors}):`, error);

            if (this.pollingErrorCount >= this.maxPollingErrors) {
                this._showSignalingError(`Signal polling error after ${this.maxPollingErrors} attempts. Please refresh the page.`);
                this.pollingErrorCount = 0;
            }
        }
    }

    /**
     * Add messages to the processing queue
     * Messages are processed sequentially to avoid race conditions
     */
    enqueueMessages(messages) {
        this.messageQueue.push(...messages);
        this.processMessageQueue();
    }

    /**
     * Process messages from the queue one at a time
     * This ensures handlers don't interleave and cause race conditions
     */
    async processMessageQueue() {
        // Prevent concurrent processing
        if (this.processingMessage || this.messageQueue.length === 0) {
            return;
        }

        this.processingMessage = true;

        while (this.messageQueue.length > 0) {
            const message = this.messageQueue.shift();
            try {
                // handleSignalMessage may be async, wait for it
                await this.handleSignalMessage(message);
            } catch (error) {
                console.error('❌ Error processing queued message:', error, message);
            }
        }

        this.processingMessage = false;
    }
    
    setupSendSignal() {
        // Track consecutive send failures
        this.sendErrorCount = 0;
        this.maxSendErrors = 5;

        // Create send method that uses the database-backed signaling server
        this.sendSignal = (message) => {
            this.debugLog("SENDING_SIGNAL", { message, url: this.signalingUrl });

            // Add room and participant info to message
            message.roomId = this.roomId;
            message.participantId = this.participantId;

            const jsonString = JSON.stringify(message);

            fetch(this.signalingUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: jsonString,
                credentials: 'same-origin'
            })
            .then(response => response.json().catch(() => response.text()))
            .then(responseData => {
                this.debugLog("SIGNAL_RESPONSE", { response: responseData });

                if (responseData && responseData.success) {
                    console.log(`📤 Signal sent: ${message.type}`);
                    this.sendErrorCount = 0; // Reset error count on success
                } else if (responseData && responseData.error) {
                    console.error(`🚨 Signal error: ${responseData.error}`);
                    this._handleSignalError(message.type, responseData.error);
                }
            })
            .catch(error => {
                this.debugLog("SIGNAL_ERROR", { error: error.message });
                console.error("🚨 Error sending signal:", error);
                this._handleSignalError(message.type, error.message);
            });
        };

        this.debugLog("TRAINING_SIGNALING_SETUP_COMPLETE");
        console.log(`🎓 Training screen sharing DB-backed signaling setup complete (NO LEGACY FALLBACK)`);
    }

    /**
     * Handle signal send errors - NO FALLBACK, just track and alert
     */
    _handleSignalError(signalType, errorMessage) {
        this.sendErrorCount++;
        console.error(`🚨 Signal send failed (${this.sendErrorCount}/${this.maxSendErrors}): ${signalType} - ${errorMessage}`);

        if (this.sendErrorCount >= this.maxSendErrors) {
            this._showSignalingError(`Signaling failed after ${this.maxSendErrors} attempts. Training session may not work properly. Please refresh the page.`);
            this.sendErrorCount = 0; // Reset to allow retry after alert
        }
    }
    
    handleSignalMessage(data) {
        // Data is always an object from DB-backed polling
        if (typeof data === 'object') {
            this.processSingleMessage(data);
        } else {
            // Unexpected string data - log error but try to process
            console.warn('⚠️ Received unexpected string data instead of object:', data);
            try {
                const parsed = JSON.parse(data);
                this.processSingleMessage(parsed);
            } catch (e) {
                console.error('🚨 Failed to parse signal data:', data);
            }
        }
    }
    
    processSingleMessage(data) {
        try {
            const message = typeof data === 'string' ? JSON.parse(data) : data;
            // Support both new and legacy sender field formats
            const senderId = message.senderId || message.from || message.participantId;
            console.log(`📥 Processing ${message.type} from ${senderId}`);
            this.debugLog("RECEIVED_MESSAGE", { type: message.type, senderId, message });
            
            switch (message.type) {
                case 'participant-joined':
                    console.log(`✅ Participant joined: ${message.participantId} as ${message.participantRole}`);
                    this.handleParticipantJoined(message);
                    break;
                case 'participant-left':
                    console.log(`👋 Participant left: ${message.participantId}`);
                    this.handleParticipantLeft(message);
                    break;
                case 'offer':
                    console.log(`📤 Received WebRTC offer from ${senderId}`);
                    this.handleOffer(message);
                    break;
                case 'answer':
                    console.log(`📥 Received WebRTC answer from ${senderId}`);
                    this.handleAnswer(message);
                    break;
                case 'ice-candidate':
                    console.log(`🧊 Received ICE candidate from ${senderId}`);
                    this.handleIceCandidate(message);
                    break;
                case 'screen-share-start':
                    console.log(`🖥️ Screen share started by ${senderId}`);
                    this.handleScreenShareStart(message);
                    break;
                case 'screen-share-stop':
                    console.log(`🛑 Screen share stopped by ${senderId}`);
                    this.handleScreenShareStop(message);
                    break;
                case 'control-change':
                    console.log(`🔄 Control change notification from ${senderId}`);
                    this.handleControlChange(message);
                    break;
                case 'conference-restart':
                    console.log(`🔄 Conference restart notification from ${senderId}`);
                    this.handleConferenceRestart(message);
                    break;
                case 'external-call-start':
                    console.log(`📞 External call start notification from ${senderId}`);
                    this.handleExternalCallStart(message);
                    break;
                case 'external-call-end':
                    console.log(`📞 External call end notification from ${senderId}`);
                    this.handleExternalCallEnd(message);
                    break;
                case 'reconnect-request':
                    console.log(`🔄 Reconnect request from ${senderId}`);
                    this.handleReconnectRequest(message);
                    break;
                case 'control-request':
                    console.log(`🎮 Control request from ${senderId}`);
                    if (window.trainingSession && typeof window.trainingSession.handleControlRequestNotification === 'function') {
                        window.trainingSession.handleControlRequestNotification(message);
                    }
                    break;
                case 'trainer-exited':
                    console.log(`🚪 Trainer exited: ${senderId}`);
                    if (window.trainingSession && typeof window.trainingSession.handleTrainerExited === 'function') {
                        window.trainingSession.handleTrainerExited(message);
                    }
                    break;
                case 'trainee-exited':
                    console.log(`🚪 Trainee exited: ${message.traineeId || senderId}`);
                    if (window.trainingSession && typeof window.trainingSession.handleTraineeExited === 'function') {
                        window.trainingSession.handleTraineeExited(message);
                    }
                    break;
                default:
                    console.log('❓ Unknown message type:', message.type);
            }
        } catch (error) {
            console.error('❌ Error processing message:', error, data);
        }
    }
    
    joinRoom() {
        // Prevent duplicate join calls
        if (this.joinInProgress || this.hasJoinedRoom) {
            console.log(`⏭️ Skipping duplicate joinRoom (inProgress: ${this.joinInProgress}, joined: ${this.hasJoinedRoom})`);
            return;
        }

        this.joinInProgress = true;
        this.debugLog("JOIN_ROOM_START");
        console.log(`🚪 ${this.role} joining room ${this.roomId}`);

        // Database-backed room join only - no legacy fallback
        fetch('/trainingShare3/roomJoin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                roomId: this.roomId
            }),
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            this.joinInProgress = false;
            if (data.success) {
                this.hasJoinedRoom = true;

                // Store session version - used to filter out stale signals structurally
                this.sessionVersion = data.sessionVersion;
                console.log(`✅ Joined room via DB (version: ${this.sessionVersion}):`, data);

                // Process any existing participants
                if (data.participants) {
                    console.log(`📋 Processing ${data.participants.length} existing participants`);
                    data.participants.forEach(p => {
                        console.log(`  - Participant: ${p.id} (${p.role})`);
                        if (p.id !== this.participantId) {
                            this.handleParticipantJoined({
                                participantId: p.id,
                                participantRole: p.role
                            });
                        }
                    });
                }

                // For trainees: Start connection timeout to detect if video doesn't arrive
                if (this.role === 'trainee') {
                    this.startConnectionTimeout();
                }
            } else {
                console.error(`🚨 Room join failed:`, data.error);
                this._showSignalingError(`Failed to join training room: ${data.error || 'Unknown error'}`);
            }
        })
        .catch(error => {
            this.joinInProgress = false;
            console.error(`🚨 Room join error:`, error);
            this._showSignalingError(`Failed to join training room: ${error.message}`);
        });
    }

    // NOTE: sendJoinRoomSignal() REMOVED - using DB-backed roomJoin.php only

    processStreamReady() {
        console.log('📺 Local stream ready - processing pending participants');
        this.debugLog("PROCESS_STREAM_READY", {
            pendingCount: this.pendingParticipants.length
        });

        // Process any participants that joined while we were getting permission
        if (this.pendingParticipants && this.pendingParticipants.length > 0) {
            console.log(`⏩ Processing ${this.pendingParticipants.length} pending participants`);
            this.pendingParticipants.forEach(({ participantId, participantRole }) => {
                console.log(`  ✅ Processing queued participant: ${participantId}`);
                this.handleTraineeJoined(participantId, participantRole);
            });
            this.pendingParticipants = [];
        }
    }

    handleTraineeJoined(participantId, participantRole) {
        console.log(`📋 Handling trainee ${participantId} after stream ready`);

        // Create peer connection if doesn't exist
        if (!this.participants.has(participantId)) {
            this.createPeerConnection(participantId, participantRole);
        }

        // Add stream to peer connection
        if (this.isSharing && this.localStream) {
            const participant = this.participants.get(participantId);
            if (participant && participant.peerConnection) {
                this.debugLog("ADDING_STREAM_TO_QUEUED_PEER", { participantId });

                // Check if tracks are already added to prevent duplicates
                const existingSenders = participant.peerConnection.getSenders();
                this.localStream.getTracks().forEach(track => {
                    const existingSender = existingSenders.find(sender => sender.track === track);
                    if (!existingSender) {
                        participant.peerConnection.addTrack(track, this.localStream);
                        this.debugLog("ADDED_TRACK_TO_QUEUED_PEER", { participantId, trackKind: track.kind });
                        console.log(`  ✅ Added ${track.kind} track to peer connection`);
                    } else {
                        this.debugLog("TRACK_ALREADY_EXISTS", { participantId, trackKind: track.kind });
                    }
                });
            }
        }

        // Initiate connection (create offer)
        this.initiateConnection(participantId);
    }
    
    checkForExistingScreenShare() {
        this.debugLog("CHECK_EXISTING_SCREEN_SHARE_START");
        
        // Check if there's already a screen sharer by looking at room participants
        // and checking if any trainer is in "sharing" state
        fetch(`/trainingShare3/roomManager.php?action=room-status&roomId=${this.roomId}`)
            .then(response => response.json())
            .then(data => {
                this.debugLog("ROOM_STATUS_RESPONSE", { data });
                
                if (data && data.participants) {
                    // Look for trainers in the room
                    for (const [participantId, participant] of Object.entries(data.participants)) {
                        if (participant.role === 'trainer') {
                            this.debugLog("FOUND_TRAINER_IN_ROOM", { participantId });
                            
                            // Create peer connection for trainer if not exists
                            if (!this.participants.has(participantId)) {
                                this.debugLog("CREATING_PEER_CONNECTION_FOR_EXISTING_TRAINER", { participantId });
                                this.createPeerConnection(participantId, 'trainer');
                            }
                            
                            // Assume trainer is sharing (since we're checking for existing sharing)
                            // Trigger the screen share display
                            this.debugLog("SIMULATING_SCREEN_SHARE_START_FOR_EXISTING");
                            this.handleScreenShareStart({
                                senderId: participantId,
                                senderRole: 'trainer',
                                type: 'screen-share-start'
                            });
                        }
                    }
                }
            })
            .catch(error => {
                this.debugLog("CHECK_EXISTING_SCREEN_SHARE_ERROR", { error: error.message });
            });
    }
    
    sendSignal(message) {
        // This method is now defined in setupSignaling()
        // It will be overridden when signaling is set up
        console.error('sendSignal called before signaling setup');
    }
    
    handleParticipantJoined(message) {
        // Handle various field names for participant ID
        // TrainingSignalingClient uses { id, role }, direct polling uses { participantId, participantRole, from }
        const participantId = message.participantId || message.id || message.from;
        const participantRole = message.participantRole || message.role || message.fromRole ||
                              (participantId === this.trainerId ? 'trainer' : 'trainee');

        if (participantId === this.participantId) {
            return; // Ignore our own join
        }

        console.log(`Participant ${participantId} (${participantRole}) joined`);

        // If we're the trainer and a trainee joined, handle connection
        if (this.role === 'trainer' && participantRole === 'trainee') {
            // CRITICAL FIX: Wait for local stream before creating peer connection
            if (this.isSharing && this.localStream) {
                // Stream is ready - proceed normally
                console.log(`✅ Trainer stream ready - handling trainee ${participantId} immediately`);
                this.handleTraineeJoined(participantId, participantRole);
            } else {
                // Stream not ready yet - queue the participant
                console.log(`⏳ Trainer stream not ready - queuing trainee ${participantId}`);
                this.pendingParticipants.push({ participantId, participantRole });
                this.debugLog("QUEUED_PARTICIPANT", {
                    participantId,
                    queueLength: this.pendingParticipants.length
                });
            }
        } else if (this.role === 'trainee' && participantRole === 'trainer') {
            // Trainee received notification that trainer joined
            // Create peer connection for trainer
            if (!this.participants.has(participantId)) {
                console.log(`📋 Trainee creating peer connection for trainer ${participantId}`);
                this.createPeerConnection(participantId, participantRole);
            }
        }
    }
    
    handleParticipantLeft(message) {
        const { participantId } = message;
        console.log(`Participant ${participantId} left`);
        
        if (this.participants.has(participantId)) {
            const participant = this.participants.get(participantId);
            if (participant.peerConnection) {
                participant.peerConnection.close();
            }
            this.participants.delete(participantId);
        }
    }
    
    createPeerConnection(participantId, participantRole) {
        // Guard against duplicate peer connection creation
        const existing = this.participants.get(participantId);
        if (existing && existing.peerConnection) {
            const state = existing.peerConnection.connectionState || existing.peerConnection.iceConnectionState;
            if (state !== 'closed' && state !== 'failed') {
                console.log(`⚠️ Peer connection for ${participantId} already exists (state: ${state}), skipping creation`);
                return existing.peerConnection;
            }
            // Close the failed/closed connection before creating new one
            console.log(`🔄 Closing stale peer connection for ${participantId} (state: ${state})`);
            existing.peerConnection.close();
            this.participants.delete(participantId);
        }

        const pc = new RTCPeerConnection(this.rtcConfiguration);
        
        // Add local stream if we're sharing screen (trainer or trainee with control)
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                const existingSenders = pc.getSenders();
                const existingSender = existingSenders.find(sender => sender.track === track);
                if (!existingSender) {
                    pc.addTrack(track, this.localStream);
                    this.debugLog("ADDED_TRACK_TO_PEER_CONNECTION", { participantId, trackKind: track.kind });
                }
            });
        }
        
        // Handle incoming stream
        pc.ontrack = (event) => {
            console.log('🎬 Received remote stream from:', participantId);
            console.log('🎬 Stream details:', event.streams[0]);
            console.log('🎬 Video tracks:', event.streams[0].getVideoTracks());

            // Cancel connection timeout since we received a stream
            this.cancelConnectionTimeout();

            // Set the remote video for any incoming stream (trainer or trainee)
            if (this.remoteVideo) {
                this.remoteVideo.srcObject = event.streams[0];
                this.currentSharer = participantId;

                console.log('🎬 Set remote video srcObject:', this.remoteVideo.srcObject);

                // Listen for video metadata to confirm video is actually playing
                this.remoteVideo.onloadedmetadata = () => {
                    console.log(`🎬 Video metadata loaded: ${this.remoteVideo.videoWidth}x${this.remoteVideo.videoHeight}`);
                    this.connectionAttempts = 0;
                    this.isReconnecting = false;
                };

                // Show shared screen UI for any role receiving a stream from someone else
                // Trainee receiving trainer screen OR trainer receiving trainee screen
                if ((this.role === 'trainee' && participantRole === 'trainer') ||
                    (this.role === 'trainer' && participantRole === 'trainee')) {

                    console.log(`🎬 ${this.role} received ${participantRole} screen - showing shared screen UI`);
                    this.showSharedScreen();

                    // Force video to be visible and play
                    this.remoteVideo.style.display = 'block';
                    this.remoteVideo.style.visibility = 'visible';
                    this.remoteVideo.style.opacity = '1';

                    // Ensure video plays
                    this.remoteVideo.play().then(() => {
                        console.log('🎬 Remote video started playing successfully');
                        // Start health check now that video is playing
                        this.startVideoHealthCheck();
                    }).catch(e => {
                        console.error('🎬 Video play failed:', e);
                        // Try to play with user interaction
                        this.remoteVideo.muted = true;
                        this.remoteVideo.play().catch(e2 => {
                            console.error('🎬 Muted video play also failed:', e2);
                        });
                    });
                }
            } else {
                console.error('🎬 No remoteVideo element found!');
            }
        };
        
        // Handle ICE candidates
        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignal({
                    type: 'ice-candidate',
                    candidate: event.candidate,
                    to: participantId
                });
            }
        };

        // Monitor ICE connection state for reliability
        pc.oniceconnectionstatechange = () => {
            const state = pc.iceConnectionState;
            console.log(`🔌 ICE connection state for ${participantId}: ${state}`);
            this.debugLog("ICE_STATE_CHANGE", { participantId, state });

            // Update participant status
            const participant = this.participants.get(participantId);
            if (participant) {
                participant.status = state;
            }

            switch (state) {
                case 'connected':
                case 'completed':
                    console.log(`✅ WebRTC connected to ${participantId}`);
                    this.connectionAttempts = 0;
                    this.isReconnecting = false;
                    // Start health check for trainees receiving video
                    if (this.role === 'trainee' && participantRole === 'trainer') {
                        this.startVideoHealthCheck();
                    }
                    break;
                case 'disconnected':
                    console.warn(`⚠️ WebRTC disconnected from ${participantId} - may recover`);
                    // Give it a moment to recover before reconnecting
                    setTimeout(() => {
                        if (pc.iceConnectionState === 'disconnected') {
                            this.handleConnectionFailure(participantId, participantRole, 'disconnected');
                        }
                    }, 3000);
                    break;
                case 'failed':
                    console.error(`❌ WebRTC connection failed to ${participantId}`);
                    this.handleConnectionFailure(participantId, participantRole, 'failed');
                    break;
                case 'closed':
                    console.log(`🚪 WebRTC connection closed to ${participantId}`);
                    break;
            }
        };

        // Also monitor overall connection state
        pc.onconnectionstatechange = () => {
            const state = pc.connectionState;
            console.log(`📡 Connection state for ${participantId}: ${state}`);
            this.debugLog("CONNECTION_STATE_CHANGE", { participantId, state });

            if (state === 'failed') {
                this.handleConnectionFailure(participantId, participantRole, 'connection-failed');
            }
        };

        // Store participant info
        this.participants.set(participantId, {
            role: participantRole,
            peerConnection: pc,
            status: 'connecting',
            pendingIceCandidates: []  // Queue for ICE candidates that arrive before remote description
        });

        return pc;
    }

    /**
     * Start video health check for trainees
     * Detects black screen / no video frames and triggers reconnection
     */
    startVideoHealthCheck() {
        if (this.healthCheckInterval) {
            return; // Already running
        }

        console.log('🏥 Starting video health check (every 3s)');
        this.lastVideoFrameTime = Date.now();

        this.healthCheckInterval = setInterval(() => {
            this.checkVideoHealth();
        }, this.healthCheckIntervalMs);

        // Also check immediately after a short delay
        setTimeout(() => this.checkVideoHealth(), 1000);
    }

    /**
     * Stop video health check
     */
    stopVideoHealthCheck() {
        if (this.healthCheckInterval) {
            clearInterval(this.healthCheckInterval);
            this.healthCheckInterval = null;
            console.log('🏥 Stopped video health check');
        }
    }

    /**
     * Start connection timeout for trainees
     * If no video received within 10 seconds, proactively request reconnection
     */
    startConnectionTimeout() {
        console.log('⏱️ Starting connection timeout (10s) - will reconnect if no video received');
        this.connectionTimeoutStart = Date.now();

        // Clear any existing timeout
        if (this.connectionTimeout) {
            clearTimeout(this.connectionTimeout);
        }

        this.connectionTimeout = setTimeout(() => {
            // Check if we have video
            const hasVideo = this.remoteVideo &&
                           this.remoteVideo.srcObject &&
                           this.remoteVideo.videoWidth > 0 &&
                           this.remoteVideo.videoHeight > 0;

            if (!hasVideo && !this.isReconnecting) {
                console.warn('⏱️ Connection timeout - no video received after 10s');
                console.log('📊 Current state:', {
                    hasRemoteVideo: !!this.remoteVideo,
                    srcObject: !!this.remoteVideo?.srcObject,
                    videoWidth: this.remoteVideo?.videoWidth,
                    videoHeight: this.remoteVideo?.videoHeight,
                    participants: Array.from(this.participants.keys()),
                    trainerId: this.trainerId,
                    currentSharer: this.currentSharer
                });

                // Find the trainer and request reconnection
                if (this.trainerId) {
                    this.handleConnectionFailure(this.trainerId, 'trainer', 'connection-timeout');
                } else {
                    // Try to find trainer from participants
                    for (const [id, p] of this.participants) {
                        if (p.role === 'trainer') {
                            this.handleConnectionFailure(id, 'trainer', 'connection-timeout');
                            break;
                        }
                    }
                }
            } else if (hasVideo) {
                console.log('✅ Video received within timeout - connection successful');
                // Start the ongoing health check
                this.startVideoHealthCheck();
            }
        }, 10000);
    }

    /**
     * Cancel connection timeout (called when video is received)
     */
    cancelConnectionTimeout() {
        if (this.connectionTimeout) {
            clearTimeout(this.connectionTimeout);
            this.connectionTimeout = null;
            console.log('⏱️ Connection timeout cancelled - video received');
        }
    }

    /**
     * Check if video is actually receiving frames
     */
    checkVideoHealth() {
        if (!this.remoteVideo || this.role !== 'trainee') {
            return;
        }

        const video = this.remoteVideo;
        const hasSource = video.srcObject !== null;
        const hasVideoTrack = hasSource && video.srcObject.getVideoTracks().length > 0;
        const trackActive = hasVideoTrack && video.srcObject.getVideoTracks()[0].readyState === 'live';
        const hasVideoSize = video.videoWidth > 0 && video.videoHeight > 0;
        const isPlaying = !video.paused && !video.ended && video.readyState >= 2;

        this.debugLog("VIDEO_HEALTH_CHECK", {
            hasSource,
            hasVideoTrack,
            trackActive,
            hasVideoSize,
            isPlaying,
            videoWidth: video.videoWidth,
            videoHeight: video.videoHeight,
            readyState: video.readyState,
            currentSharer: this.currentSharer
        });

        // Video is healthy if it has size and is playing
        if (hasVideoSize && isPlaying && trackActive) {
            this.lastVideoFrameTime = Date.now();
            this.connectionAttempts = 0;
            return;
        }

        // Check if we have a sharer but no video
        if (this.currentSharer && !hasVideoSize) {
            const timeSinceLastFrame = Date.now() - this.lastVideoFrameTime;

            // If no video for 5 seconds, attempt reconnection
            if (timeSinceLastFrame > 5000 && !this.isReconnecting) {
                console.warn(`⚠️ Black screen detected (no video for ${Math.round(timeSinceLastFrame/1000)}s) - attempting reconnection`);
                this.handleConnectionFailure(this.currentSharer, 'trainer', 'black-screen');
            }
        }

        // If we don't have a source at all and trainer should be sharing, request reconnect
        if (!hasSource && this.trainerId && !this.isReconnecting) {
            console.warn('⚠️ No video source - trainer may not be sharing or connection lost');
            // Try to reconnect to trainer
            this.requestReconnection(this.trainerId, 'trainer');
        }
    }

    /**
     * Handle connection failure - attempt reconnection
     */
    handleConnectionFailure(participantId, participantRole, reason) {
        if (this.isReconnecting) {
            console.log('🔄 Already reconnecting, skipping duplicate request');
            return;
        }

        this.connectionAttempts++;
        console.log(`🔄 Connection failure (${reason}) - attempt ${this.connectionAttempts}/${this.maxConnectionAttempts}`);

        if (this.connectionAttempts > this.maxConnectionAttempts) {
            console.error(`❌ Max reconnection attempts reached. Please refresh the page.`);
            this._showSignalingError('Screen sharing connection lost. Please refresh the page to reconnect.');
            return;
        }

        this.isReconnecting = true;
        this.requestReconnection(participantId, participantRole);
    }

    /**
     * Request reconnection to a participant
     */
    requestReconnection(participantId, participantRole) {
        console.log(`🔄 Requesting reconnection to ${participantId} (${participantRole})...`);

        // Close existing connection
        const existingParticipant = this.participants.get(participantId);
        if (existingParticipant && existingParticipant.peerConnection) {
            try {
                existingParticipant.peerConnection.close();
            } catch (e) {
                console.log('Error closing peer connection:', e);
            }
            this.participants.delete(participantId);
        }

        // Clear video source
        if (this.remoteVideo) {
            this.remoteVideo.srcObject = null;
        }

        // Wait a moment then reconnect
        setTimeout(() => {
            console.log(`🔄 Creating new peer connection to ${participantId}`);

            // Create new peer connection
            this.createPeerConnection(participantId, participantRole);

            // If we're trainee and trainer is sharing, send reconnect request
            if (this.role === 'trainee' && participantRole === 'trainer') {
                this.sendSignal({
                    type: 'reconnect-request',
                    to: participantId,
                    reason: 'connection-lost'
                });
            }

            // Also check for existing screen share again
            setTimeout(() => {
                this.checkForExistingScreenShare();
                this.isReconnecting = false;
            }, 1000);
        }, this.reconnectDelayMs);
    }

    /**
     * Handle reconnect request from a trainee
     */
    handleReconnectRequest(message) {
        const { from, reason } = message;
        console.log(`🔄 Received reconnect request from ${from} (${reason})`);

        if (this.role === 'trainer' && this.isSharing && this.localStream) {
            // Re-initiate connection with the trainee
            console.log(`🔄 Re-initiating connection with ${from}`);

            // Close existing connection
            const existingParticipant = this.participants.get(from);
            if (existingParticipant && existingParticipant.peerConnection) {
                try {
                    existingParticipant.peerConnection.close();
                } catch (e) {
                    console.log('Error closing peer connection:', e);
                }
                this.participants.delete(from);
            }

            // Create new connection and send offer
            setTimeout(() => {
                this.createPeerConnection(from, 'trainee');
                setTimeout(() => {
                    this.initiateConnection(from);
                }, 500);
            }, 500);
        }
    }

    async initiateConnection(participantId) {
        const participant = this.participants.get(participantId);
        if (!participant) {
            console.warn(`Cannot initiate connection: participant ${participantId} not found`);
            return;
        }

        const pc = participant.peerConnection;

        // Validate peer connection state before creating offer
        if (pc.signalingState === 'closed') {
            console.warn(`Cannot create offer: peer connection to ${participantId} is closed`);
            return;
        }

        if (pc.signalingState !== 'stable') {
            console.warn(`Peer connection to ${participantId} not in stable state (${pc.signalingState}), waiting...`);
            // Wait for state to stabilize
            await new Promise(resolve => setTimeout(resolve, 100));
            if (pc.signalingState !== 'stable') {
                console.warn(`Peer connection still not stable, aborting offer creation`);
                return;
            }
        }

        try {
            const offer = await pc.createOffer({
                offerToReceiveAudio: false,
                offerToReceiveVideo: true
            });

            await pc.setLocalDescription(offer);

            this.sendSignal({
                type: 'offer',
                offer: offer,
                to: participantId
            });

            console.log('Sent offer to:', participantId);
        } catch (error) {
            console.error('Error creating offer:', error);
        }
    }
    
    async handleOffer(message) {
        const { from, offer } = message;
        let participant = this.participants.get(from);

        if (!participant) {
            console.warn('Received offer from unknown participant:', from, '- creating peer connection');
            // Auto-create peer connection for unknown participant (likely the trainer)
            const participantRole = (from === this.trainerId) ? 'trainer' : 'trainee';
            this.createPeerConnection(from, participantRole);
            participant = this.participants.get(from);

            if (!participant) {
                console.error('Failed to create peer connection for:', from);
                return;
            }
        }

        let pc = participant.peerConnection;

        // CRITICAL FIX: Check peer connection state before setting remote description
        if (pc.signalingState === 'closed') {
            console.error('Peer connection closed - cannot set remote description');
            // Recreate peer connection
            this.createPeerConnection(from, participant.role);
            participant = this.participants.get(from);
            pc = participant.peerConnection;
        }

        try {
            console.log(`📥 Setting remote description from ${from}, current state: ${pc.signalingState}`);
            // Note: Modern WebRTC accepts plain objects - RTCSessionDescription constructor is deprecated
            await pc.setRemoteDescription(offer);

            // Apply any ICE candidates that arrived before the offer
            await this.applyPendingIceCandidates(from, participant);

            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);

            this.sendSignal({
                type: 'answer',
                answer: answer,
                to: from
            });

            console.log('✅ Sent answer to:', from);
        } catch (error) {
            console.error('❌ Error handling offer:', error);
            console.error('  Signaling state:', pc.signalingState);
            console.error('  Remote description:', pc.remoteDescription);

            // Attempt recovery from invalid state (with retry limit)
            if (error.name === 'InvalidStateError') {
                const retryCount = (message._retryCount || 0) + 1;
                const maxRetries = 3;

                if (retryCount <= maxRetries) {
                    console.log(`🔄 Attempting recovery (${retryCount}/${maxRetries})...`);
                    // Close and recreate peer connection
                    pc.close();
                    this.participants.delete(from);
                    this.createPeerConnection(from, participant.role);
                    // Retry handling the offer after a brief delay
                    setTimeout(() => {
                        console.log('🔄 Retrying offer handling after recovery');
                        this.handleOffer({ ...message, _retryCount: retryCount });
                    }, 100);
                } else {
                    console.error(`❌ Max retries (${maxRetries}) exceeded for offer from ${from}`);
                }
            }
        }
    }
    
    async handleAnswer(message) {
        const { from, answer } = message;
        let participant = this.participants.get(from);
        
        if (!participant) {
            console.warn('Received answer from unknown participant:', from, '- creating peer connection');
            // Auto-create peer connection for unknown participant
            const participantRole = (from === this.trainerId) ? 'trainer' : 'trainee';
            this.createPeerConnection(from, participantRole);
            participant = this.participants.get(from);
            
            if (!participant) {
                console.error('Failed to create peer connection for:', from);
                return;
            }
        }
        
        const pc = participant.peerConnection;
        
        try {
            // Note: Modern WebRTC accepts plain objects - RTCSessionDescription constructor is deprecated
            await pc.setRemoteDescription(answer);

            // Apply any ICE candidates that arrived before the answer
            await this.applyPendingIceCandidates(from, participant);

            participant.status = 'connected';
            console.log('Connection established with:', from);
        } catch (error) {
            console.error('Error handling answer:', error);
        }
    }

    /**
     * Apply any ICE candidates that were queued before remote description was set
     */
    async applyPendingIceCandidates(from, participant) {
        if (!participant.pendingIceCandidates || participant.pendingIceCandidates.length === 0) {
            return;
        }

        const pc = participant.peerConnection;
        const count = participant.pendingIceCandidates.length;
        console.log(`🧊 Applying ${count} queued ICE candidates from ${from}`);

        for (const candidate of participant.pendingIceCandidates) {
            try {
                await pc.addIceCandidate(candidate);
            } catch (error) {
                console.error('Error adding queued ICE candidate:', error);
            }
        }

        // Clear the queue
        participant.pendingIceCandidates = [];
        console.log(`✅ Applied ${count} queued ICE candidates from ${from}`);
    }
    
    async handleIceCandidate(message) {
        const { from, candidate } = message;
        let participant = this.participants.get(from);

        if (!participant) {
            console.warn('Received ICE candidate from unknown participant:', from, '- creating peer connection');
            // Auto-create peer connection for unknown participant (likely the trainer)
            // Determine their role based on whether they match our trainer ID
            const participantRole = (from === this.trainerId) ? 'trainer' : 'trainee';
            this.createPeerConnection(from, participantRole);
            participant = this.participants.get(from);

            if (!participant) {
                console.error('Failed to create peer connection for:', from);
                return;
            }
        }

        const pc = participant.peerConnection;

        // Queue ICE candidates if remote description not yet set (avoids InvalidStateError)
        if (!pc.remoteDescription) {
            console.log(`🧊 Queuing ICE candidate from ${from} (remote description not yet set)`);
            participant.pendingIceCandidates = participant.pendingIceCandidates || [];
            participant.pendingIceCandidates.push(candidate);
            return;
        }

        try {
            // Note: Modern WebRTC accepts plain objects - RTCIceCandidate constructor is deprecated
            await pc.addIceCandidate(candidate);
            console.log('Added ICE candidate from:', from);
        } catch (error) {
            console.error('Error adding ICE candidate:', error);
        }
    }
    
    handleScreenShareStart(message) {
        // Use senderId (new) or from (legacy) for sender identification
        const senderId = message.senderId || message.from;
        const senderRole = message.senderRole || message.fromRole;
        
        console.log('Screen sharing started by:', senderId, '(role:', senderRole + ')');
        this.debugLog("RECEIVED_SCREEN_SHARE_START", { senderId, senderRole, message });
        
        this.currentSharer = senderId;
        
        if (this.role === 'trainee') {
            this.showSharedScreen();
        }
    }
    
    handleScreenShareStop(message) {
        // Use senderId (new) or from (legacy) for sender identification
        const senderId = message.senderId || message.from;
        const senderRole = message.senderRole || message.fromRole;

        console.log('Screen sharing stopped by:', senderId, '(role:', senderRole + ')');
        this.debugLog("RECEIVED_SCREEN_SHARE_STOP", { senderId, senderRole, message });

        if (this.currentSharer === senderId) {
            this.currentSharer = null;
            // Stop health check since no one is sharing
            this.stopVideoHealthCheck();
            if (this.role === 'trainee') {
                this.hideSharedScreen();
            }
        }
    }

    handleControlChange(message) {
        console.log('Control change notification received:', message);
        
        // Forward to TrainingSession if available
        if (window.trainingSession && typeof window.trainingSession.handleControlChangeNotification === 'function') {
            window.trainingSession.handleControlChangeNotification(message);
        }
    }

    handleConferenceRestart(message) {
        console.log('Conference restart notification received:', message);
        
        // Forward to TrainingSession if available
        if (window.trainingSession && typeof window.trainingSession.handleConferenceRestart === 'function') {
            window.trainingSession.handleConferenceRestart(message);
        }
    }

    handleExternalCallStart(message) {
        console.log('External call start notification received:', message);
        
        // Forward to TrainingSession if available
        if (window.trainingSession && typeof window.trainingSession.handleExternalCallStart === 'function') {
            window.trainingSession.handleExternalCallStart(message);
        }
    }

    handleExternalCallEnd(message) {
        console.log('External call end notification received:', message);
        
        // Forward to TrainingSession if available
        if (window.trainingSession && typeof window.trainingSession.handleExternalCallEnd === 'function') {
            window.trainingSession.handleExternalCallEnd(message);
        }
    }
    
    // UI Control Methods
    async startScreenSharing() {
        this.debugLog("START_SCREEN_SHARING_CALLED", { role: this.role, isSharing: this.isSharing });
        
        // Anyone can start screen sharing when they have control
        console.log(`${this.role} attempting to start screen sharing`);
        
        try {
            this.debugLog("SETUP_LOCAL_STREAM_CHECK", { hasLocalStream: !!this.localStream });
            
            // Set up local stream if not already done
            if (!this.localStream) {
                this.debugLog("SETUP_LOCAL_STREAM_START");
                await this.setupLocalStream();
            }
            
            this.isSharing = true;
            
            // Add the new stream to all existing peer connections
            this.debugLog("ADDING_STREAM_TO_PEER_CONNECTIONS", { participantCount: this.participants.size });
            for (const [participantId, participant] of this.participants) {
                const pc = participant.peerConnection;
                if (pc) {
                    // Add each track from the local stream
                    this.localStream.getTracks().forEach(track => {
                        pc.addTrack(track, this.localStream);
                        this.debugLog("ADDED_TRACK_TO_PEER", { participantId, trackKind: track.kind });
                    });
                    
                    // Create new offer with the updated stream
                    this.debugLog("CREATING_NEW_OFFER_FOR_PEER", { participantId });
                    this.initiateConnection(participantId);
                }
            }
            
            this.debugLog("SENDING_SCREEN_SHARE_START_SIGNAL");
            this.sendSignal({
                type: 'screen-share-start'
            });
            
            this.debugLog("CALLING_SHARE_MY_SCREEN");
            this.shareMyScreen();
            
            this.debugLog("SCREEN_SHARING_STARTED_SUCCESS");
            console.log(`${this.role} screen sharing started successfully`);
        } catch (error) {
            this.debugLog("SCREEN_SHARING_START_ERROR", { error: error.message });
            console.error('Failed to start screen sharing:', error);
            alert('Failed to start screen sharing: ' + error.message);
        }
    }
    
    stopScreenSharing() {
        console.log(`${this.role} stopping screen sharing`);
        
        this.isSharing = false;
        this.sendSignal({
            type: 'screen-share-stop'
        });
        
        this.hideMyScreen();
    }
    
    shareMyScreen() {
        // Hide local preview - user doesn't need to see their own screen share
        if (this.localVideo) {
            this.localVideo.style.display = "none";
        }
    }
    
    hideMyScreen() {
        // Hide the preview window
        if (this.localVideo) {
            this.localVideo.style.display = "none";
            // Reset all positioning styles
            this.localVideo.style.position = "";
            this.localVideo.style.top = "";
            this.localVideo.style.right = "";
            this.localVideo.style.width = "";
            this.localVideo.style.height = "";
            this.localVideo.style.maxHeight = "";
            this.localVideo.style.zIndex = "";
            this.localVideo.style.border = "";
            this.localVideo.style.borderRadius = "";
            this.localVideo.style.boxShadow = "";
            this.localVideo.style.backgroundColor = "";
        }
    }
    
    showSharedScreen() {
        console.log('Showing shared screen UI');
        
        // Hide main UI elements for production
        const elementsToHide = ['mainPane', 'titleBar', 'sidePane', 'bottomPane'];
        elementsToHide.forEach(id => {
            const elem = document.getElementById(id);
            if (elem) {
                elem.style.display = "none";
                console.log(`Hidden element: ${id}`);
            }
        });
        
        // Show the remote video fullscreen
        if (this.remoteVideo) {
            this.remoteVideo.style.display = "block";
            this.remoteVideo.style.position = "fixed";
            this.remoteVideo.style.top = "0";
            this.remoteVideo.style.left = "0";
            this.remoteVideo.style.width = "100%";
            this.remoteVideo.style.height = "100%";
            this.remoteVideo.style.objectFit = "contain";
            this.remoteVideo.style.backgroundColor = "#000";
            this.remoteVideo.style.zIndex = "10000";
            this.remoteVideo.style.border = "none";
            console.log('Remote video element shown fullscreen');
        }
    }
    
    hideSharedScreen() {
        console.log('Hiding shared screen UI');
        
        // Restore main UI elements
        const elementsToShow = ['mainPane', 'titleBar', 'sidePane', 'bottomPane'];
        elementsToShow.forEach(id => {
            const elem = document.getElementById(id);
            if (elem) {
                elem.style.display = "";
                console.log(`Restored element: ${id}`);
            }
        });
        
        // Restore all divs
        const mainElements = document.body.getElementsByTagName("div");
        for (let i = 0; i < mainElements.length; i++) {
            mainElements[i].style.display = "";
        }
        
        // Hide the remote video
        if (this.remoteVideo) {
            this.remoteVideo.style.display = "none";
            this.remoteVideo.style.position = "";
            this.remoteVideo.style.zIndex = "";
            console.log('Remote video element hidden');
        }
    }
    
    // Cleanup
    closeConnection() {
        console.log(`🚪 closeConnection called for ${this.participantId}`);
        console.trace('closeConnection stack trace');
        // Stop polling
        this.stopPolling();
        
        // Close any remaining peer connections
        
        this.participants.forEach((participant, participantId) => {
            if (participant.peerConnection) {
                participant.peerConnection.close();
            }
        });
        
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
        }
        
        // Send leave message
        this.sendSignal({
            type: 'leave-room'
        });
    }
    
    // Public API
    async startScreenShare() {
        console.log(`${this.role} manually starting screen share...`);
        
        if (this.isSharing) {
            console.log('Screen sharing already active');
            return;
        }
        
        try {
            await this.setupLocalStream();
            this.isSharing = true;
            this.sendSignal({ type: 'screen-share-start' });
            console.log(`${this.role} screen sharing started successfully`);
        } catch (error) {
            console.error('Failed to start screen sharing:', error);
            throw error;
        }
    }
    
    stopScreenShare() {
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
            this.localStream = null;
        }
        this.isSharing = false;
        this.sendSignal({ type: 'screen-share-stop' });
        console.log('Screen sharing stopped');
    }
    
    getParticipants() {
        return Array.from(this.participants.keys());
    }
    
    getConnectionStatus(participantId) {
        const participant = this.participants.get(participantId);
        return participant ? participant.status : 'unknown';
    }
}

// Legacy compatibility wrapper
function SignalingServer(role) {
    // Create instance using legacy interface
    const screenSharing = new MultiTraineeScreenSharing({
        role: role
    });
    
    // Expose legacy methods
    this.shareMyScreen = () => screenSharing.startScreenSharing();
    this.getSharedScreen = () => screenSharing.showSharedScreen();
    this.closeConnection = () => screenSharing.closeConnection();
    this.reestablish = () => {
        // Reconnect by creating new instance
        screenSharing.init();
    };
    
    return screenSharing;
}