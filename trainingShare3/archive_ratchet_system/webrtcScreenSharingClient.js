/**
 * WebRTC Screen Sharing Client for Training System
 * Uses Ratchet WebSocket server for signaling
 * Supports 1 trainer + multiple trainees with automatic screen sharing
 */

class WebRTCScreenSharingClient {
    constructor(options = {}) {
        this.options = {
            wsUrl: 'ws://localhost:8080',
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ],
            ...options
        };
        
        // Core properties
        this.role = null; // 'trainer' or 'trainee'
        this.userId = null;
        this.roomId = null;
        this.sessionId = null;
        
        // WebSocket client
        this.wsClient = null;
        this.isConnected = false;
        
        // WebRTC connections
        this.peerConnections = new Map(); // userId -> RTCPeerConnection
        this.isSharing = false;
        this.localStream = null;
        
        // Video elements
        this.localVideo = null;
        this.remoteVideo = null;
        
        // State tracking
        this.initialized = false;
        this.participants = new Set();
        
        // Event handlers
        this.eventHandlers = {};
        
        // UI management
        this.originalUIState = null;
        
        console.log('🎥 WebRTC Screen Sharing Client initialized');
    }

    /**
     * Initialize as trainer - automatically starts screen sharing
     */
    async initializeTrainer() {
        console.log('👨‍🏫 Initializing as trainer...');
        
        try {
            this.role = 'trainer';
            await this._initialize();
            
            console.log('✅ Trainer initialization complete');
            this.emit('trainer-ready');
            
            // Note: Auto-start screen sharing moved to separate method
            // This allows manual control and better error handling
            console.log('💡 Trainer ready. Call startScreenShare() to begin sharing.');
            
        } catch (error) {
            console.error('❌ Trainer initialization failed:', error);
            const errorMessage = error.message || error.toString() || 'Unknown error';
            this.emit('error', new Error(`Trainer initialization failed: ${errorMessage}`));
            throw new Error(`Trainer initialization failed: ${errorMessage}`);
        }
    }

    /**
     * Initialize as trainee - prepares to receive trainer's screen
     */
    async initializeTrainee() {
        console.log('👨‍🎓 Initializing as trainee...');
        
        try {
            this.role = 'trainee';
            await this._initialize();
            
            // Set up UI for receiving video
            this._setupVideoElements();
            this._configureTraineeUI();
            
            console.log('✅ Trainee initialization complete');
            this.emit('trainee-ready');
            
        } catch (error) {
            console.error('❌ Trainee initialization failed:', error);
            this.emit('error', error);
            throw error;
        }
    }

    /**
     * Common initialization logic
     */
    async _initialize() {
        if (this.initialized) {
            console.log('⚠️ Already initialized');
            return;
        }

        try {
            // Get session information from DOM
            console.log('📋 Extracting session information...');
            this._extractSessionInfo();
            
            // Validate extracted information
            if (!this.userId) {
                throw new Error('User ID not found. Make sure volunteerID or currentUserName field exists.');
            }
            if (!this.roomId) {
                throw new Error('Room ID not found. Make sure trainerID field exists.');
            }
            
            // Initialize WebSocket client
            console.log('🔌 Initializing WebSocket...');
            await this._initializeWebSocket();
            
            // Set up video elements
            console.log('📺 Setting up video elements...');
            this._setupVideoElements();
            
            this.initialized = true;
            console.log(`✅ WebRTC client initialized as ${this.role}`);
            
        } catch (error) {
            console.error('❌ Initialization failed:', error);
            this.initialized = false;
            throw new Error(`Initialization failed: ${error.message || error}`);
        }
    }

    /**
     * Extract session information from DOM elements
     */
    _extractSessionInfo() {
        this.userId = document.getElementById('volunteerID')?.value || 
                     document.getElementById('currentUserName')?.value;
        
        // For trainers, use their ID as room. For trainees, use trainer ID
        const trainerIdField = document.getElementById('trainerID');
        const traineeIdField = document.getElementById('traineeID');
        
        if (this.role === 'trainer') {
            this.roomId = this.userId;
        } else if (this.role === 'trainee' && trainerIdField) {
            this.roomId = trainerIdField.value;
        }
        
        // Get session ID (simplified for now)
        this.sessionId = 'training-session-' + Date.now();
        
        console.log('📋 Session info extracted:', {
            role: this.role,
            userId: this.userId,
            roomId: this.roomId
        });
    }

    /**
     * Initialize WebSocket connection
     */
    async _initializeWebSocket() {
        console.log('🔌 Initializing WebSocket connection...');
        
        // Check if TrainingWebSocketClient is available
        if (typeof TrainingWebSocketClient === 'undefined') {
            throw new Error('TrainingWebSocketClient is not available. Make sure trainingWebSocketClient.js is loaded.');
        }
        
        try {
            this.wsClient = new TrainingWebSocketClient({
                url: this.options.wsUrl
            });
            
            // Set up WebSocket event handlers
            this._setupWebSocketHandlers();
            
            // Connect to WebSocket server
            await new Promise((resolve, reject) => {
                const timeout = setTimeout(() => {
                    reject(new Error('WebSocket connection timeout after 10 seconds'));
                }, 10000);
                
                this.wsClient.on('connected', () => {
                    clearTimeout(timeout);
                    this.isConnected = true;
                    console.log('✅ WebSocket connected');
                    resolve();
                });
                
                this.wsClient.on('error', (error) => {
                    clearTimeout(timeout);
                    console.error('🔌 WebSocket connection error:', error);
                    reject(new Error(`WebSocket connection failed: ${error.message || error}`));
                });
                
                this.wsClient.on('disconnected', (event) => {
                    clearTimeout(timeout);
                    console.error('🔌 WebSocket disconnected unexpectedly:', event);
                    reject(new Error(`WebSocket disconnected: ${event.reason || 'Unknown reason'}`));
                });
                
                console.log(`🔌 Connecting to WebSocket: ${this.options.wsUrl}`);
                console.log(`📋 Connection details: Room=${this.roomId}, User=${this.userId}, Session=${this.sessionId}`);
                
                this.wsClient.connect(this.roomId, this.userId, this.sessionId);
            });
            
        } catch (error) {
            console.error('❌ WebSocket initialization error:', error);
            throw new Error(`Failed to initialize WebSocket: ${error.message || error}`);
        }
    }

    /**
     * Set up WebSocket event handlers for WebRTC signaling
     */
    _setupWebSocketHandlers() {
        // Handle new participants joining
        this.wsClient.on('participant-joined', (data) => {
            console.log('👥 Participant joined:', data.userId);
            this.participants.add(data.userId);
            
            // If we're a trainer and sharing, create connection to new trainee
            if (this.role === 'trainer' && this.isSharing && data.role === 'trainee') {
                this._createPeerConnection(data.userId);
            }
        });
        
        // Handle participants leaving
        this.wsClient.on('participant-left', (data) => {
            console.log('👥 Participant left:', data.userId);
            this.participants.delete(data.userId);
            this._closePeerConnection(data.userId);
        });
        
        // Handle WebRTC signaling
        this.wsClient.on('offer', (data) => this._handleOffer(data));
        this.wsClient.on('answer', (data) => this._handleAnswer(data));
        this.wsClient.on('ice-candidate', (data) => this._handleIceCandidate(data));
        
        // Handle screen sharing signals
        this.wsClient.on('screen-share-start', (data) => {
            console.log('🖥️ Screen sharing started by:', data.from);
            if (this.role === 'trainee') {
                this._prepareToReceiveScreen(data.from);
            }
        });
        
        this.wsClient.on('screen-share-stop', (data) => {
            console.log('🖥️ Screen sharing stopped by:', data.from);
            if (this.role === 'trainee') {
                this._handleScreenShareStop(data.from);
            }
        });
    }

    /**
     * Set up video elements in the DOM
     */
    _setupVideoElements() {
        // Local video (for trainer's own screen preview)
        this.localVideo = document.getElementById('localVideo');
        if (!this.localVideo) {
            this.localVideo = document.createElement('video');
            this.localVideo.id = 'localVideo';
            this.localVideo.autoplay = true;
            this.localVideo.muted = true;
            this.localVideo.style.display = 'none'; // Hidden by default
            document.body.appendChild(this.localVideo);
        }
        
        // Remote video (for trainee to see trainer's screen)
        this.remoteVideo = document.getElementById('remoteVideo');
        if (!this.remoteVideo) {
            this.remoteVideo = document.createElement('video');
            this.remoteVideo.id = 'remoteVideo';
            this.remoteVideo.autoplay = true;
            this.remoteVideo.playsInline = true;
            this.remoteVideo.setAttribute('poster', 'trainingShare3/poster.png');
            document.body.appendChild(this.remoteVideo);
        }
        
        console.log('📺 Video elements set up');
    }

    /**
     * Start screen sharing (trainer only)
     */
    async startScreenShare() {
        if (this.role !== 'trainer') {
            console.warn('⚠️ Only trainers can start screen sharing');
            return;
        }
        
        if (this.isSharing) {
            console.log('⚠️ Already sharing screen');
            return;
        }
        
        try {
            console.log('🖥️ Starting screen share...');
            
            // Get screen capture stream
            this.localStream = await navigator.mediaDevices.getDisplayMedia({
                video: {
                    cursor: 'always',
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                },
                audio: false // Audio handled by Twilio conference
            });
            
            // Show local preview (small)
            if (this.localVideo) {
                this.localVideo.srcObject = this.localStream;
                this.localVideo.style.display = 'block';
                this.localVideo.style.position = 'fixed';
                this.localVideo.style.top = '10px';
                this.localVideo.style.right = '10px';
                this.localVideo.style.width = '200px';
                this.localVideo.style.height = 'auto';
                this.localVideo.style.zIndex = '10000';
                this.localVideo.style.border = '2px solid #007bff';
                this.localVideo.style.borderRadius = '5px';
            }
            
            // Create peer connections to all trainees
            for (const participantId of this.participants) {
                if (participantId !== this.userId) {
                    await this._createPeerConnection(participantId);
                }
            }
            
            // Handle stream end (user stops sharing)
            this.localStream.getVideoTracks()[0].addEventListener('ended', () => {
                console.log('🛑 Screen sharing ended by user');
                this.stopScreenShare();
            });
            
            this.isSharing = true;
            
            // Notify all participants
            this.wsClient.sendScreenShareStart();
            
            // Configure trainer UI
            this._configureTrainerUI();
            
            console.log('✅ Screen sharing started successfully');
            this.emit('sharing-started');
            
        } catch (error) {
            console.error('❌ Failed to start screen sharing:', error);
            this.emit('error', error);
            
            // Show user-friendly error
            if (error.name === 'NotAllowedError') {
                this._showAlert('Screen sharing permission denied. Please allow screen sharing to continue with training.');
            } else {
                this._showAlert('Failed to start screen sharing. Please try again.');
            }
        }
    }

    /**
     * Stop screen sharing
     */
    async stopScreenShare() {
        if (!this.isSharing) {
            console.log('⚠️ Not currently sharing');
            return;
        }
        
        console.log('🛑 Stopping screen share...');
        
        try {
            // Stop local stream
            if (this.localStream) {
                this.localStream.getTracks().forEach(track => track.stop());
                this.localStream = null;
            }
            
            // Hide local video
            if (this.localVideo) {
                this.localVideo.srcObject = null;
                this.localVideo.style.display = 'none';
            }
            
            // Close all peer connections
            for (const [participantId, pc] of this.peerConnections) {
                pc.close();
            }
            this.peerConnections.clear();
            
            this.isSharing = false;
            
            // Notify participants
            this.wsClient.sendScreenShareStop();
            
            // Restore UI
            this._restoreUI();
            
            console.log('✅ Screen sharing stopped');
            this.emit('sharing-stopped');
            
        } catch (error) {
            console.error('❌ Error stopping screen share:', error);
        }
    }

    /**
     * Create WebRTC peer connection to a participant
     */
    async _createPeerConnection(participantId) {
        console.log(`🔗 Creating peer connection to ${participantId}`);
        
        try {
            const pc = new RTCPeerConnection({
                iceServers: this.options.iceServers
            });
            
            // Set up event handlers
            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    this.wsClient.sendIceCandidate(event.candidate, participantId);
                }
            };
            
            pc.onconnectionstatechange = () => {
                console.log(`🔗 Connection state with ${participantId}:`, pc.connectionState);
                
                if (pc.connectionState === 'connected') {
                    console.log(`✅ Successfully connected to ${participantId}`);
                } else if (pc.connectionState === 'failed') {
                    console.log(`❌ Connection failed with ${participantId}`);
                    this._closePeerConnection(participantId);
                }
            };
            
            // Add local stream (for trainers)
            if (this.localStream && this.role === 'trainer') {
                this.localStream.getTracks().forEach(track => {
                    pc.addTrack(track, this.localStream);
                });
            }
            
            // Handle incoming stream (for trainees)
            if (this.role === 'trainee') {
                pc.ontrack = (event) => {
                    console.log('📺 Received remote stream from trainer');
                    if (this.remoteVideo) {
                        this.remoteVideo.srcObject = event.streams[0];
                        this._showTrainerScreen();
                    }
                };
            }
            
            this.peerConnections.set(participantId, pc);
            
            // Create and send offer (trainers initiate)
            if (this.role === 'trainer') {
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                this.wsClient.sendOffer(offer, participantId);
            }
            
        } catch (error) {
            console.error(`❌ Failed to create peer connection to ${participantId}:`, error);
        }
    }

    /**
     * Close peer connection to a participant
     */
    _closePeerConnection(participantId) {
        const pc = this.peerConnections.get(participantId);
        if (pc) {
            pc.close();
            this.peerConnections.delete(participantId);
            console.log(`🔗 Closed peer connection to ${participantId}`);
        }
    }

    /**
     * Handle incoming WebRTC offer
     */
    async _handleOffer(data) {
        console.log(`📩 Received offer from ${data.from}`);
        
        try {
            if (!this.peerConnections.has(data.from)) {
                await this._createPeerConnection(data.from);
            }
            
            const pc = this.peerConnections.get(data.from);
            await pc.setRemoteDescription(data.offer);
            
            // Create and send answer
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            this.wsClient.sendAnswer(answer, data.from);
            
        } catch (error) {
            console.error('❌ Failed to handle offer:', error);
        }
    }

    /**
     * Handle incoming WebRTC answer
     */
    async _handleAnswer(data) {
        console.log(`📩 Received answer from ${data.from}`);
        
        try {
            const pc = this.peerConnections.get(data.from);
            if (pc) {
                await pc.setRemoteDescription(data.answer);
            }
        } catch (error) {
            console.error('❌ Failed to handle answer:', error);
        }
    }

    /**
     * Handle incoming ICE candidate
     */
    async _handleIceCandidate(data) {
        console.log(`🧊 Received ICE candidate from ${data.from}`);
        
        try {
            const pc = this.peerConnections.get(data.from);
            if (pc) {
                await pc.addIceCandidate(data.candidate);
            }
        } catch (error) {
            console.error('❌ Failed to handle ICE candidate:', error);
        }
    }

    /**
     * Configure UI for trainer (show all elements)
     */
    _configureTrainerUI() {
        console.log('🎨 Configuring trainer UI...');
        
        // Save original state
        this._saveOriginalUIState();
        
        // Show all training interface elements
        this._showAllElements();
        
        // Hide remote video for trainer
        if (this.remoteVideo) {
            this.remoteVideo.style.display = 'none';
        }
        
        this.emit('ui-configured', { role: 'trainer' });
    }

    /**
     * Configure UI for trainee (focus on video)
     */
    _configureTraineeUI() {
        console.log('🎨 Configuring trainee UI...');
        
        // Save original state
        this._saveOriginalUIState();
        
        // Initially show normal interface
        this._showAllElements();
        
        // Set up remote video for receiving trainer screen
        if (this.remoteVideo) {
            this.remoteVideo.style.display = 'block';
            this.remoteVideo.style.position = 'fixed';
            this.remoteVideo.style.top = '50%';
            this.remoteVideo.style.left = '50%';
            this.remoteVideo.style.transform = 'translate(-50%, -50%)';
            this.remoteVideo.style.width = '90%';
            this.remoteVideo.style.height = 'auto';
            this.remoteVideo.style.maxHeight = '90vh';
            this.remoteVideo.style.zIndex = '1000';
            this.remoteVideo.style.display = 'none'; // Hidden until trainer shares
        }
        
        this.emit('ui-configured', { role: 'trainee' });
    }

    /**
     * Show trainer's screen (trainee only)
     */
    _showTrainerScreen() {
        console.log('🖥️ Showing trainer screen...');
        
        // Hide interface elements
        const elementsToHide = [
            'volunteerListTable', 'newSearchPane', 'callPane',
            'volunteerMessage', 'infoCenterPane', 'volunteerDetailsTitle'
        ];
        
        elementsToHide.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.style.display = 'none';
            }
        });
        
        // Show and configure video for full screen viewing
        if (this.remoteVideo) {
            this.remoteVideo.style.display = 'block';
            this.remoteVideo.style.position = 'fixed';
            this.remoteVideo.style.top = '0';
            this.remoteVideo.style.left = '0';
            this.remoteVideo.style.width = '100vw';
            this.remoteVideo.style.height = '100vh';
            this.remoteVideo.style.objectFit = 'contain';
            this.remoteVideo.style.zIndex = '9999';
            this.remoteVideo.style.backgroundColor = '#000';
        }
        
        this.emit('trainer-screen-shown');
    }

    /**
     * Show all interface elements
     */
    _showAllElements() {
        const elementsToShow = [
            'volunteerListTable', 'newSearchPane', 'callPane',
            'volunteerMessage', 'infoCenterPane', 'volunteerDetailsTitle'
        ];
        
        elementsToShow.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.style.display = 'block';
                element.style.visibility = 'visible';
            }
        });
    }

    /**
     * Save original UI state
     */
    _saveOriginalUIState() {
        if (this.originalUIState) return; // Already saved
        
        this.originalUIState = {};
        const elementsToSave = [
            'volunteerListTable', 'newSearchPane', 'callPane',
            'volunteerMessage', 'infoCenterPane', 'volunteerDetailsTitle',
            'localVideo', 'remoteVideo'
        ];
        
        elementsToSave.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                this.originalUIState[id] = {
                    display: element.style.display,
                    position: element.style.position,
                    top: element.style.top,
                    left: element.style.left,
                    width: element.style.width,
                    height: element.style.height,
                    zIndex: element.style.zIndex
                };
            }
        });
    }

    /**
     * Restore original UI state
     */
    _restoreUI() {
        if (!this.originalUIState) return;
        
        Object.keys(this.originalUIState).forEach(id => {
            const element = document.getElementById(id);
            const originalStyle = this.originalUIState[id];
            
            if (element && originalStyle) {
                Object.keys(originalStyle).forEach(prop => {
                    element.style[prop] = originalStyle[prop] || '';
                });
            }
        });
        
        this.originalUIState = null;
    }

    /**
     * Handle screen sharing stop signal
     */
    _handleScreenShareStop(trainerId) {
        if (this.role === 'trainee') {
            console.log('🛑 Trainer stopped sharing screen');
            
            // Hide video and restore UI
            if (this.remoteVideo) {
                this.remoteVideo.srcObject = null;
                this.remoteVideo.style.display = 'none';
            }
            
            this._restoreUI();
            this._showAllElements();
            
            this.emit('trainer-screen-stopped');
        }
    }

    /**
     * Prepare to receive screen from trainer
     */
    _prepareToReceiveScreen(trainerId) {
        if (this.role === 'trainee') {
            console.log(`📺 Preparing to receive screen from trainer: ${trainerId}`);
            // Connection will be established when trainer sends offer
        }
    }

    /**
     * Add event listener
     */
    on(event, handler) {
        if (!this.eventHandlers[event]) {
            this.eventHandlers[event] = [];
        }
        this.eventHandlers[event].push(handler);
    }

    /**
     * Emit event
     */
    emit(event, data) {
        if (this.eventHandlers[event]) {
            this.eventHandlers[event].forEach(handler => {
                try {
                    handler(data);
                } catch (error) {
                    console.error(`Error in event handler for ${event}:`, error);
                }
            });
        }
    }

    /**
     * Show alert message
     */
    _showAlert(message) {
        if (typeof showAlert === 'function') {
            showAlert(message);
        } else {
            alert(message);
        }
    }

    /**
     * Clean up and destroy
     */
    destroy() {
        console.log('🧹 Destroying WebRTC Screen Sharing Client...');
        
        // Stop screen sharing
        if (this.isSharing) {
            this.stopScreenShare();
        }
        
        // Close all peer connections
        for (const [participantId, pc] of this.peerConnections) {
            pc.close();
        }
        this.peerConnections.clear();
        
        // Stop local stream
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
            this.localStream = null;
        }
        
        // Disconnect WebSocket
        if (this.wsClient) {
            this.wsClient.disconnect();
            this.wsClient = null;
        }
        
        // Restore UI
        this._restoreUI();
        
        // Clean up video elements
        if (this.localVideo && this.localVideo.parentNode) {
            this.localVideo.parentNode.removeChild(this.localVideo);
        }
        if (this.remoteVideo && this.remoteVideo.parentNode) {
            this.remoteVideo.parentNode.removeChild(this.remoteVideo);
        }
        
        this.initialized = false;
        this.isConnected = false;
        
        console.log('✅ WebRTC Screen Sharing Client destroyed');
    }

    /**
     * Get current status
     */
    getStatus() {
        return {
            role: this.role,
            userId: this.userId,
            roomId: this.roomId,
            initialized: this.initialized,
            isConnected: this.isConnected,
            isSharing: this.isSharing,
            participantCount: this.participants.size,
            connectionCount: this.peerConnections.size
        };
    }
}

// Make globally available (backward compatibility with trainingSessionUpdated.js)
window.webrtcScreenSharingClient = new WebRTCScreenSharingClient();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WebRTCScreenSharingClient;
}