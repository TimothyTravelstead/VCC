/**
 * Non-Authenticated Screen Sharing Control
 * Uses dedicated signaling server without authentication
 */
class NoAuthScreenSharing {
    constructor(options = {}) {
        this.participantId = options.participantId || 'participant_' + Math.random().toString(36).substr(2, 9);
        this.roomId = options.roomId || 'test_room';
        this.role = options.role || 'trainee'; // 'trainer' or 'trainee'
        
        // Debug logging
        this.debugLog("CONSTRUCTOR", {
            participantId: this.participantId,
            roomId: this.roomId,
            role: this.role
        });
        
        // UI elements
        this.localVideo = document.getElementById("localVideo");
        this.remoteVideo = document.getElementById("remoteVideo");
        if (this.remoteVideo) {
            this.remoteVideo.poster = "trainingShare3/poster.png";
        }
        
        // Signaling URLs
        this.signalingUrl = `/trainingShare3/noAuthSignalingServer.php?roomId=${this.roomId}&participantId=${this.participantId}`;
        
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
        this.participants = new Map(); // participantId -> {peerConnection, status}
        this.localStream = null;
        this.isSharing = false;
        this.eventSource = null;
        this.currentSharer = null;
        
        // Initialize
        this.init();
    }
    
    debugLog(stage, data = {}) {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = {
            timestamp,
            stage,
            role: this.role,
            participantId: this.participantId,
            roomId: this.roomId,
            data
        };
        console.log(`[${timestamp}] ${stage}:`, logEntry);
        
        // Also display in page if debug div exists
        const debugDiv = document.getElementById('debugLog');
        if (debugDiv) {
            const logLine = `[${timestamp}] ${stage}: ${JSON.stringify(data)}\n`;
            debugDiv.textContent += logLine;
            debugDiv.scrollTop = debugDiv.scrollHeight;
        }
        
        // Store in window for inspection
        if (!window.noAuthDebugLog) {
            window.noAuthDebugLog = [];
        }
        window.noAuthDebugLog.push(logEntry);
    }
    
    async init() {
        this.debugLog("INIT_START");
        console.log(`Initializing no-auth screen sharing as ${this.role} in room ${this.roomId}`);
        
        try {
            this.setupSignaling();
            this.debugLog("INIT_SUCCESS");
        } catch (error) {
            this.debugLog("INIT_ERROR", { error: error.message });
            console.error('Failed to initialize screen sharing:', error);
        }
    }
    
    setupSignaling() {
        this.debugLog("SETUP_SIGNALING_START");
        
        // Create EventSource connection to signaling server
        this.eventSource = new EventSource(this.signalingUrl);
        
        this.eventSource.onopen = () => {
            this.debugLog("EVENTSOURCE_CONNECTED");
            console.log("Connected to signaling server");
            
            // Join room after connection is established
            this.joinRoom();
        };
        
        this.eventSource.addEventListener('signal', (event) => {
            this.debugLog("RECEIVED_SIGNAL_EVENT", { eventData: event.data });
            this.handleSignalMessage(event.data);
        });
        
        this.eventSource.onerror = (error) => {
            this.debugLog("EVENTSOURCE_ERROR", { error });
            console.error("EventSource error:", error);
        };
        
        this.debugLog("SIGNALING_SETUP_COMPLETE");
    }
    
    sendSignal(message) {
        this.debugLog("SENDING_SIGNAL", { message });
        
        const jsonString = JSON.stringify(message);
        
        fetch(this.signalingUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: jsonString
        })
        .then(response => response.json())
        .then(data => {
            this.debugLog("SIGNAL_RESPONSE", { data });
        })
        .catch(error => {
            this.debugLog("SIGNAL_ERROR", { error: error.message });
            console.error('Error sending signal:', error);
        });
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
            const senderId = message.from;
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
    
    handleParticipantJoined(message) {
        const { participantId } = message;
        
        if (participantId === this.participantId) {
            return; // Ignore our own join
        }
        
        this.debugLog("PARTICIPANT_JOINED", { participantId });
        console.log(`Participant ${participantId} joined`);
        
        // Create peer connection for new participant
        this.createPeerConnection(participantId);
        
        // If we're the trainer and sharing, initiate connection
        if (this.role === 'trainer' && this.isSharing) {
            this.debugLog("TRAINER_INITIATING_CONNECTION", { participantId });
            this.initiateConnection(participantId);
        }
    }
    
    handleParticipantLeft(message) {
        const { participantId } = message;
        this.debugLog("PARTICIPANT_LEFT", { participantId });
        console.log(`Participant ${participantId} left`);
        
        if (this.participants.has(participantId)) {
            const participant = this.participants.get(participantId);
            if (participant.peerConnection) {
                participant.peerConnection.close();
            }
            this.participants.delete(participantId);
        }
    }
    
    createPeerConnection(participantId) {
        this.debugLog("CREATE_PEER_CONNECTION", { participantId });
        const pc = new RTCPeerConnection(this.rtcConfiguration);
        
        // Add local stream if we're the trainer and sharing
        if (this.role === 'trainer' && this.localStream) {
            this.debugLog("ADDING_LOCAL_STREAM_TO_PEER", { participantId });
            this.localStream.getTracks().forEach(track => {
                pc.addTrack(track, this.localStream);
            });
        }
        
        // Handle incoming stream
        pc.ontrack = (event) => {
            this.debugLog("RECEIVED_REMOTE_STREAM", { participantId });
            console.log('Received remote stream from:', participantId);
            if (this.remoteVideo) {
                this.remoteVideo.srcObject = event.streams[0];
                this.currentSharer = participantId;
            }
        };
        
        // Handle ICE candidates
        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this.debugLog("SENDING_ICE_CANDIDATE", { participantId });
                this.sendSignal({
                    type: 'ice-candidate',
                    candidate: event.candidate,
                    to: participantId
                });
            }
        };
        
        // Store participant info
        this.participants.set(participantId, {
            peerConnection: pc,
            status: 'connecting'
        });
        
        return pc;
    }
    
    async initiateConnection(participantId) {
        this.debugLog("INITIATE_CONNECTION", { participantId });
        const participant = this.participants.get(participantId);
        if (!participant) return;
        
        const pc = participant.peerConnection;
        
        try {
            const offer = await pc.createOffer({
                offerToReceiveAudio: false,
                offerToReceiveVideo: true
            });
            
            await pc.setLocalDescription(offer);
            
            this.debugLog("SENDING_OFFER", { participantId });
            this.sendSignal({
                type: 'offer',
                offer: offer,
                to: participantId
            });
            
            console.log('Sent offer to:', participantId);
        } catch (error) {
            this.debugLog("OFFER_ERROR", { participantId, error: error.message });
            console.error('Error creating offer:', error);
        }
    }
    
    async handleOffer(message) {
        const { from, offer } = message;
        this.debugLog("RECEIVED_OFFER", { from });
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
            
            this.debugLog("SENDING_ANSWER", { from });
            this.sendSignal({
                type: 'answer',
                answer: answer,
                to: from
            });
            
            console.log('Sent answer to:', from);
        } catch (error) {
            this.debugLog("ANSWER_ERROR", { from, error: error.message });
            console.error('Error handling offer:', error);
        }
    }
    
    async handleAnswer(message) {
        const { from, answer } = message;
        this.debugLog("RECEIVED_ANSWER", { from });
        const participant = this.participants.get(from);
        
        if (!participant) {
            console.error('Received answer from unknown participant:', from);
            return;
        }
        
        const pc = participant.peerConnection;
        
        try {
            await pc.setRemoteDescription(new RTCSessionDescription(answer));
            participant.status = 'connected';
            this.debugLog("CONNECTION_ESTABLISHED", { from });
            console.log('Connection established with:', from);
        } catch (error) {
            this.debugLog("ANSWER_HANDLE_ERROR", { from, error: error.message });
            console.error('Error handling answer:', error);
        }
    }
    
    async handleIceCandidate(message) {
        const { from, candidate } = message;
        this.debugLog("RECEIVED_ICE_CANDIDATE", { from });
        const participant = this.participants.get(from);
        
        if (!participant) {
            console.error('Received ICE candidate from unknown participant:', from);
            return;
        }
        
        const pc = participant.peerConnection;
        
        try {
            await pc.addIceCandidate(new RTCIceCandidate(candidate));
            this.debugLog("ICE_CANDIDATE_ADDED", { from });
        } catch (error) {
            this.debugLog("ICE_CANDIDATE_ERROR", { from, error: error.message });
            console.error('Error adding ICE candidate:', error);
        }
    }
    
    handleScreenShareStart(message) {
        const senderId = message.from;
        this.debugLog("RECEIVED_SCREEN_SHARE_START", { senderId });
        console.log('Screen sharing started by:', senderId);
        
        this.currentSharer = senderId;
        
        if (this.role === 'trainee') {
            this.showSharedScreen();
        }
    }
    
    handleScreenShareStop(message) {
        const senderId = message.from;
        this.debugLog("RECEIVED_SCREEN_SHARE_STOP", { senderId });
        console.log('Screen sharing stopped by:', senderId);
        
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
            // Set up local stream
            if (!this.localStream) {
                this.debugLog("SETUP_LOCAL_STREAM_START");
                await this.setupLocalStream();
            }
            
            this.isSharing = true;
            
            // Add stream to all existing peer connections
            this.debugLog("ADDING_STREAM_TO_PEER_CONNECTIONS", { participantCount: this.participants.size });
            for (const [participantId, participant] of this.participants) {
                const pc = participant.peerConnection;
                if (pc) {
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
    
    async setupLocalStream() {
        this.debugLog("SETUP_LOCAL_STREAM_START", { role: this.role });
        
        if (this.role === 'trainer') {
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
        // Show small preview window in corner
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
        if (this.localVideo) {
            this.localVideo.style.display = "none";
        }
    }
    
    showSharedScreen() {
        // Hide all UI elements except remote video
        const mainElements = document.body.getElementsByTagName("div");
        for (let i = 0; i < mainElements.length; i++) {
            if (mainElements[i].id !== 'debugLog') { // Keep debug log visible
                mainElements[i].style.display = "none";
            }
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
    
    // Public API methods
    getParticipants() {
        return Array.from(this.participants.keys());
    }
    
    getConnectionStatus(participantId) {
        const participant = this.participants.get(participantId);
        return participant ? participant.status : 'unknown';
    }
    
    // Cleanup
    closeConnection() {
        if (this.eventSource) {
            this.eventSource.close();
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
}