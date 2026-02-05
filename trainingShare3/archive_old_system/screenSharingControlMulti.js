/**
 * Multi-Trainee Screen Sharing Control
 * Supports 1 trainer + multiple trainees using PHP-based signaling
 */
class MultiTraineeScreenSharing {
    constructor(options = {}) {
        // Get participant info from page - try multiple field names for compatibility
        this.trainerId = options.trainerId || 
                        document.getElementById("trainer")?.value || 
                        document.getElementById("trainerID")?.value;
        this.participantId = options.participantId || 
                           document.getElementById("volunteerID")?.value;
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
        
        // Signaling URLs
        this.signalingUrl = `/trainingShare3/signalingServerMulti.php?trainingShareRoom=${this.roomId}&role=${this.role}`;
        
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
        // Try multiple field names for compatibility
        const trainerId = document.getElementById("trainer")?.value || 
                         document.getElementById("trainerID")?.value;
        const participantId = document.getElementById("volunteerID")?.value;
        return participantId === trainerId ? 'trainer' : 'trainee';
    }
    
    async init() {
        this.debugLog("INIT_START");
        console.log(`Initializing multi-trainee screen sharing as ${this.role} in room ${this.roomId}`);
        
        try {
            // Don't setup local stream immediately to avoid permission prompts
            // Will be set up when user actually starts sharing
            this.setupSignaling();
            // joinRoom() is now called after signaling is ready
            this.debugLog("INIT_SUCCESS");
        } catch (error) {
            this.debugLog("INIT_ERROR", { error: error.message });
            console.error('Failed to initialize screen sharing:', error);
        }
    }
    
    async setupLocalStream() {
        this.debugLog("SETUP_LOCAL_STREAM_START", { role: this.role });
        
        if (this.role === 'trainer') {
            // Trainer gets screen sharing stream
            const constraints = {
                video: {
                    cursor: "always",
                    displaySurface: "application",
                    logicalSurface: true
                },
                audio: false
            };
            
            this.debugLog("REQUESTING_DISPLAY_MEDIA", { constraints });
            
            try {
                this.localStream = await navigator.mediaDevices.getDisplayMedia(constraints);
                this.debugLog("DISPLAY_MEDIA_ACQUIRED", { streamId: this.localStream.id });
                
                if (this.localVideo) {
                    this.localVideo.srcObject = this.localStream;
                    this.debugLog("LOCAL_VIDEO_SET");
                } else {
                    this.debugLog("NO_LOCAL_VIDEO_ELEMENT");
                }
                console.log('Local screen sharing stream acquired');
            } catch (error) {
                this.debugLog("DISPLAY_MEDIA_ERROR", { error: error.message });
                console.error('Failed to get display media:', error);
                throw error;
            }
        } else {
            // Trainees don't need local stream for viewing
            this.debugLog("TRAINEE_NO_LOCAL_STREAM");
            console.log('Trainee - no local stream needed');
        }
    }
    
    setupSignaling() {
        this.debugLog("SETUP_SIGNALING_START");
        try {
            console.log("Using main EventSource from ChatMonitor for screen sharing");
            
            // Try to get EventSource, with retry mechanism for timing issues
            this.waitForEventSource();
            
        } catch (error) {
            this.debugLog("SETUP_SIGNALING_ERROR", { error: error.message });
            console.error('Failed to setup signaling:', error);
        }
    }
    
    waitForEventSource(attempts = 0) {
        const maxAttempts = 10;
        const retryDelay = 500; // 500ms
        
        this.debugLog("WAIT_FOR_EVENTSOURCE", { 
            attempts, 
            newChatExists: typeof newChat !== 'undefined',
            newChatValue: !!newChat,
            newChatSource: !!(newChat && newChat.source)
        });
        
        // Check if ChatMonitor EventSource is ready
        if (typeof newChat !== 'undefined' && newChat && newChat.source) {
            this.debugLog("EVENTSOURCE_FOUND");
            console.log("Found existing ChatMonitor EventSource");
            
            // Store reference to the ChatMonitor's EventSource
            this.eventSource = newChat.source;
            
            // Add our own listener for screenShare events
            this.eventSource.addEventListener('screenShare', (event) => {
                this.debugLog("RECEIVED_SCREENSHARE_EVENT", { eventData: event.data });
                console.log("Received screenShare event:", event);
                this.handleSignalMessage(event.data);
            });
            
            // Create send method that uses the signaling server via Ajax
            this.sendSignal = (message) => {
                this.debugLog("SENDING_SIGNAL", { message, url: this.signalingUrl });
                
                // Add room and participant info to message
                message.roomId = this.roomId;
                message.participantId = this.participantId;
                
                const jsonString = JSON.stringify(message);
                
                // Send via Ajax to the signaling server
                new AjaxRequest(this.signalingUrl, jsonString, (response) => {
                    this.debugLog("SIGNAL_RESPONSE", { response });
                    if (response && response !== "OK") {
                        console.log("Signal response:", response);
                    }
                });
            };
            
            this.debugLog("SIGNALING_SETUP_COMPLETE");
            console.log("Screen sharing signaling setup complete");
            
            // Now that signaling is ready, join the room
            this.joinRoom();
            
            // Check if trainer is already sharing (for late joiners)
            if (this.role === 'trainee') {
                this.debugLog("CHECKING_EXISTING_SCREEN_SHARE");
                // Small delay to ensure room join is processed first
                setTimeout(() => {
                    this.checkForExistingScreenShare();
                }, 1000);
            }
            
            return;
            
        } else if (attempts < maxAttempts) {
            this.debugLog("EVENTSOURCE_RETRY", { attempts, maxAttempts });
            console.log(`ChatMonitor EventSource not ready, retrying in ${retryDelay}ms (attempt ${attempts + 1}/${maxAttempts})`);
            setTimeout(() => {
                this.waitForEventSource(attempts + 1);
            }, retryDelay);
            
        } else {
            this.debugLog("EVENTSOURCE_FAILED");
            console.error("ChatMonitor EventSource not available after maximum retries - screen sharing will not work");
            // Could fallback to creating own EventSource here if needed
        }
    }
    
    handleSignalMessage(data) {
        // Handle multiple events in one message
        if (data.includes("_MULTIPLEVENTS_")) {
            const events = data.split("_MULTIPLEVENTS_");
            events.forEach(eventData => {
                if (eventData.trim()) {
                    this.processSingleMessage(eventData);
                }
            });
        } else {
            this.processSingleMessage(data);
        }
    }
    
    processSingleMessage(data) {
        try {
            const message = JSON.parse(data);
            // Support both new and legacy sender field formats
            const senderId = message.senderId || message.from;
            console.log('Received message:', message.type, 'from:', senderId);
            this.debugLog("RECEIVED_MESSAGE", { type: message.type, senderId, message });
            
            switch (message.type) {
                case 'participant-joined':
                    this.handleParticipantJoined(message);
                    break;
                case 'participant-left':
                    this.handleParticipantLeft(message);
                    break;
                case 'offer':
                    this.handleOffer(message);
                    break;
                case 'answer':
                    this.handleAnswer(message);
                    break;
                case 'ice-candidate':
                    this.handleIceCandidate(message);
                    break;
                case 'screen-share-start':
                    this.handleScreenShareStart(message);
                    break;
                case 'screen-share-stop':
                    this.handleScreenShareStop(message);
                    break;
                default:
                    console.log('Unknown message type:', message.type);
            }
        } catch (error) {
            console.error('Error processing message:', error, data);
        }
    }
    
    joinRoom() {
        this.debugLog("JOIN_ROOM_START");
        this.sendSignal({
            type: 'join-room'
        });
    }
    
    checkForExistingScreenShare() {
        this.debugLog("CHECK_EXISTING_SCREEN_SHARE_START");
        
        // Check if there's already a screen sharer by looking at room participants
        // and checking if any trainer is in "sharing" state
        fetch(`roomManager.php?action=room-status&roomId=${this.roomId}`)
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
        const { participantId, participantRole } = message;
        
        if (participantId === this.participantId) {
            return; // Ignore our own join
        }
        
        console.log(`Participant ${participantId} (${participantRole}) joined`);
        
        // Create peer connection for new participant
        this.createPeerConnection(participantId, participantRole);
        
        // If we're the trainer and a trainee joined, handle connection
        if (this.role === 'trainer' && participantRole === 'trainee') {
            // If we're already sharing, add our stream to the new peer connection
            if (this.isSharing && this.localStream) {
                const participant = this.participants.get(participantId);
                if (participant && participant.peerConnection) {
                    this.debugLog("ADDING_STREAM_TO_NEW_PEER", { participantId });
                    this.localStream.getTracks().forEach(track => {
                        participant.peerConnection.addTrack(track, this.localStream);
                        this.debugLog("ADDED_TRACK_TO_NEW_PEER", { participantId, trackKind: track.kind });
                    });
                }
            }
            
            // Initiate connection (create offer)
            this.initiateConnection(participantId);
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
        const pc = new RTCPeerConnection(this.rtcConfiguration);
        
        // Add local stream if we're the trainer
        if (this.role === 'trainer' && this.localStream) {
            this.localStream.getTracks().forEach(track => {
                pc.addTrack(track, this.localStream);
            });
        }
        
        // Handle incoming stream
        pc.ontrack = (event) => {
            console.log('Received remote stream from:', participantId);
            if (this.remoteVideo && participantRole === 'trainer') {
                this.remoteVideo.srcObject = event.streams[0];
                this.currentSharer = participantId;
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
        
        // Store participant info
        this.participants.set(participantId, {
            role: participantRole,
            peerConnection: pc,
            status: 'connecting'
        });
        
        return pc;
    }
    
    async initiateConnection(participantId) {
        const participant = this.participants.get(participantId);
        if (!participant) return;
        
        const pc = participant.peerConnection;
        
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
        const participant = this.participants.get(from);
        
        if (!participant) {
            console.error('Received offer from unknown participant:', from);
            return;
        }
        
        const pc = participant.peerConnection;
        
        try {
            await pc.setRemoteDescription(new RTCSessionDescription(offer));
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            
            this.sendSignal({
                type: 'answer',
                answer: answer,
                to: from
            });
            
            console.log('Sent answer to:', from);
        } catch (error) {
            console.error('Error handling offer:', error);
        }
    }
    
    async handleAnswer(message) {
        const { from, answer } = message;
        const participant = this.participants.get(from);
        
        if (!participant) {
            console.error('Received answer from unknown participant:', from);
            return;
        }
        
        const pc = participant.peerConnection;
        
        try {
            await pc.setRemoteDescription(new RTCSessionDescription(answer));
            participant.status = 'connected';
            console.log('Connection established with:', from);
        } catch (error) {
            console.error('Error handling answer:', error);
        }
    }
    
    async handleIceCandidate(message) {
        const { from, candidate } = message;
        const participant = this.participants.get(from);
        
        if (!participant) {
            console.error('Received ICE candidate from unknown participant:', from);
            return;
        }
        
        const pc = participant.peerConnection;
        
        try {
            await pc.addIceCandidate(new RTCIceCandidate(candidate));
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
            if (this.role === 'trainee') {
                this.hideSharedScreen();
            }
        }
    }
    
    // UI Control Methods
    async startScreenSharing() {
        this.debugLog("START_SCREEN_SHARING_CALLED", { role: this.role, isSharing: this.isSharing });
        
        if (this.role !== 'trainer') {
            this.debugLog("START_SCREEN_SHARING_REJECTED", { reason: "not_trainer" });
            console.warn('Only trainers can start screen sharing');
            return;
        }
        
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
            console.log('Screen sharing started successfully');
        } catch (error) {
            this.debugLog("SCREEN_SHARING_START_ERROR", { error: error.message });
            console.error('Failed to start screen sharing:', error);
            alert('Failed to start screen sharing: ' + error.message);
        }
    }
    
    stopScreenSharing() {
        if (this.role !== 'trainer') {
            console.warn('Only trainers can stop screen sharing');
            return;
        }
        
        this.isSharing = false;
        this.sendSignal({
            type: 'screen-share-stop'
        });
        
        this.hideMyScreen();
    }
    
    shareMyScreen() {
        // Show small preview window in corner - don't hide interface
        if (this.localVideo) {
            this.localVideo.style.display = "block";
            this.localVideo.style.position = "fixed";
            this.localVideo.style.top = "10px";
            this.localVideo.style.right = "10px";
            this.localVideo.style.width = "200px";
            this.localVideo.style.height = "auto";
            this.localVideo.style.maxHeight = "150px";
            this.localVideo.style.zIndex = "10000";
            this.localVideo.style.border = "2px solid #007bff";
            this.localVideo.style.borderRadius = "8px";
            this.localVideo.style.boxShadow = "0 4px 8px rgba(0,0,0,0.3)";
            this.localVideo.style.backgroundColor = "#000";
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
        // Hide all UI elements except remote video
        const mainElements = document.body.getElementsByTagName("div");
        for (let i = 0; i < mainElements.length; i++) {
            mainElements[i].style.display = "none";
        }
        if (this.remoteVideo) {
            this.remoteVideo.style.display = "block";
        }
    }
    
    hideSharedScreen() {
        // Restore all UI elements
        const mainElements = document.body.getElementsByTagName("div");
        for (let i = 0; i < mainElements.length; i++) {
            mainElements[i].style.display = null;
        }
        if (this.remoteVideo) {
            this.remoteVideo.style.display = "none";
        }
    }
    
    // Cleanup
    closeConnection() {
        // Don't close the EventSource since it's shared with ChatMonitor
        // Just remove our event listener
        if (this.eventSource) {
            this.eventSource.removeEventListener('screenShare', this.handleSignalMessage);
        }
        
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