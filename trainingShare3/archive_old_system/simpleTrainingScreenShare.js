// Simple Training Screen Share - Based on working simple_caller.html and simple_receiver.html
// No authentication required - uses simpleTrainingSignaling.php

class SimpleTrainingScreenShare {
    constructor() {
        this.myId = null;
        this.role = null; // 'trainer' or 'trainee'
        this.peerConnections = new Map();
        this.localStream = null;
        this.lastMessageTime = 0;
        this.isSharing = false;
        this.hasJoined = false;
        this.signalingUrl = 'trainingShare3/simpleTrainingSignaling.php';
    }

    log(msg) {
        const time = new Date().toLocaleTimeString();
        console.log(`[${time}] SimpleTraining: ${msg}`);
        
        // Also add to debug log if available
        if (window.debugLog) {
            window.debugLog(`[${time}] SimpleTraining: ${msg}`);
        }
    }

    async sendMessage(message, targetId = 'broadcast') {
        message.from = this.myId;
        message.to = targetId;
        
        try {
            const response = await fetch(this.signalingUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(message)
            });
            const result = await response.json();
            this.log(`SENT: ${message.type} to ${targetId}`);
        } catch (error) {
            this.log(`ERROR sending: ${error.message}`);
        }
    }

    async pollMessages() {
        try {
            const response = await fetch(`${this.signalingUrl}?to=${this.myId}&since=${this.lastMessageTime}`);
            const result = await response.json();
            
            if (result.messages) {
                for (const msg of result.messages) {
                    this.lastMessageTime = Math.max(this.lastMessageTime, msg.timestamp);
                    this.handleMessage(msg);
                }
            }
        } catch (error) {
            // Silent error - polling fails are normal
        }
    }

    async handleMessage(msg) {
        this.log(`RECEIVED: ${msg.type} from ${msg.from}`);
        
        switch (msg.type) {
            case 'join-request':
                if (this.role === 'trainer') {
                    await this.handleJoinRequest(msg.from);
                }
                break;
            case 'offer':
                if (this.role === 'trainee') {
                    await this.handleOffer(msg.offer);
                }
                break;
            case 'answer':
                if (this.role === 'trainer') {
                    await this.handleAnswer(msg.from, msg.answer);
                }
                break;
            case 'ice-candidate':
                await this.handleIceCandidate(msg.from, msg.candidate);
                break;
        }
    }

    // TRAINER METHODS
    async initializeTrainer() {
        this.myId = 'trainer';
        this.role = 'trainer';
        this.log('Initializing trainer...');
        
        // Start polling for messages
        setInterval(() => this.pollMessages(), 1000);
        
        // Send heartbeat every 30 seconds
        setInterval(() => {
            if (this.isSharing) {
                this.sendMessage({
                    type: 'trainer-active'
                }, 'broadcast');
            }
        }, 30000);
        
        // Auto-start screen sharing
        await this.startScreenShare();
    }

    async startScreenShare() {
        this.log('Starting screen share...');
        
        try {
            this.localStream = await navigator.mediaDevices.getDisplayMedia({
                video: true,
                audio: false
            });
            
            this.log(`Screen capture successful - got ${this.localStream.getTracks().length} tracks`);
            this.localStream.getTracks().forEach(track => {
                this.log(`Track: ${track.kind} - ${track.label} - enabled: ${track.enabled}`);
            });
            
            // Set local video if element exists
            const localVideo = document.getElementById('localVideo');
            if (localVideo) {
                localVideo.srcObject = this.localStream;
                this.log('Local video element set with stream');
            } else {
                this.log('Local video element not found');
            }
            
            this.isSharing = true;
            
            // Handle stream end
            this.localStream.getVideoTracks()[0].onended = () => {
                this.log('Screen share ended');
                this.isSharing = false;
                this.localStream = null;
                if (localVideo) {
                    localVideo.srcObject = null;
                }
            };
            
            this.log('Screen share active - trainees can now join');
            
            // Send active signal
            this.sendMessage({
                type: 'trainer-active'
            }, 'broadcast');
            
            this.log('Trainer-active signal sent');
            
        } catch (error) {
            this.log(`ERROR starting screen share: ${error.message}`);
        }
    }

    async handleJoinRequest(traineeId) {
        this.log(`Creating connection for ${traineeId}`);
        
        const pc = new RTCPeerConnection({
            iceServers: [{'urls': 'stun:stun.l.google.com:19302'}]
        });
        
        this.peerConnections.set(traineeId, pc);
        
        // Add ICE candidate handler
        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendMessage({
                    type: 'ice-candidate',
                    candidate: event.candidate
                }, traineeId);
            }
        };
        
        // Add local stream tracks if available
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                pc.addTrack(track, this.localStream);
                this.log(`Added ${track.kind} track to connection for ${traineeId}`);
            });
        }
        
        // Create and send offer
        try {
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            
            this.sendMessage({
                type: 'offer',
                offer: offer
            }, traineeId);
            
            this.log(`Sent offer to ${traineeId}`);
        } catch (error) {
            this.log(`Error creating offer: ${error.message}`);
        }
    }

    async handleAnswer(traineeId, answer) {
        const pc = this.peerConnections.get(traineeId);
        if (pc) {
            try {
                await pc.setRemoteDescription(new RTCSessionDescription(answer));
                this.log(`Set remote description for ${traineeId}`);
            } catch (error) {
                this.log(`Error setting remote description: ${error.message}`);
            }
        }
    }

    // TRAINEE METHODS
    async initializeTrainee() {
        this.myId = 'trainee_' + Math.random().toString(36).substr(2, 5);
        this.role = 'trainee';
        this.log(`Initializing trainee (ID: ${this.myId})...`);
        
        // Start polling for messages
        setInterval(() => this.pollMessages(), 1000);
        
        // Check for trainer immediately
        this.checkForTrainer();
        
        // Check for trainer every 2 seconds
        setInterval(() => this.checkForTrainer(), 2000);
    }

    async checkForTrainer() {
        if (this.hasJoined) return;
        
        try {
            const response = await fetch(`${this.signalingUrl}?action=check-trainer`);
            const result = await response.json();
            
            this.log(`Check trainer result: ${JSON.stringify(result)}`);
            
            if (result.trainerActive) {
                this.log('🎯 Trainer is active - joining session...');
                this.hasJoined = true;
                await this.joinSession();
            } else {
                this.log('⏳ Trainer not active yet, waiting...');
            }
        } catch (error) {
            this.log(`❌ Error checking for trainer: ${error.message}`);
        }
    }

    async joinSession() {
        this.log('Connecting to trainer...');
        
        // Create peer connection
        const pc = new RTCPeerConnection({
            iceServers: [{'urls': 'stun:stun.l.google.com:19302'}]
        });
        
        this.peerConnections.set('trainer', pc);
        
        // Handle incoming stream from trainer
        // FIXED Jan 28, 2025: Enhanced video handling to ensure proper display
        pc.ontrack = (event) => {
            this.log(`Remote stream received - ${event.streams.length} stream(s)`);
            const stream = event.streams[0];
            this.log(`Stream tracks: ${stream.getTracks().length}`);
            stream.getTracks().forEach(track => {
                this.log(`Track: ${track.kind} - ${track.label} - enabled: ${track.enabled}`);
            });
            
            const remoteVideo = document.getElementById('remoteVideo');
            if (remoteVideo) {
                remoteVideo.srcObject = stream;
                remoteVideo.style.display = 'block';
                
                // FIXED: Ensure video plays automatically when metadata loads
                remoteVideo.onloadedmetadata = () => {
                    this.log('Video metadata loaded, attempting to play');
                    remoteVideo.play().then(() => {
                        this.log('Video playing successfully');
                    }).catch(e => {
                        this.log(`Video play failed: ${e.message}`);
                    });
                };
                
                this.log('Remote video element set with stream');
            } else {
                this.log('ERROR: Remote video element not found');
            }
            
            // Hide trainee interface, show trainer screen
            this.showTrainerScreen();
        };
        
        // Handle ICE candidates
        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendMessage({
                    type: 'ice-candidate',
                    candidate: event.candidate
                }, 'trainer');
            }
        };
        
        // Connection state monitoring
        pc.onconnectionstatechange = () => {
            this.log(`🔗 Connection state: ${pc.connectionState}`);
            if (pc.connectionState === 'connected') {
                this.log('✅ WebRTC connection established successfully');
            } else if (pc.connectionState === 'failed') {
                this.log('❌ WebRTC connection failed');
            }
        };
        
        // Ice connection state monitoring
        pc.oniceconnectionstatechange = () => {
            this.log(`🧊 ICE connection state: ${pc.iceConnectionState}`);
        };
        
        // Send join request
        this.sendMessage({
            type: 'join-request'
        }, 'trainer');
    }

    async handleOffer(offer) {
        const pc = this.peerConnections.get('trainer');
        if (!pc) {
            this.log('ERROR: No peer connection for trainer');
            return;
        }
        
        try {
            await pc.setRemoteDescription(new RTCSessionDescription(offer));
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            
            this.sendMessage({
                type: 'answer',
                answer: answer
            }, 'trainer');
            
            this.log('Answer sent to trainer');
        } catch (error) {
            this.log(`Error handling offer: ${error.message}`);
        }
    }

    async handleIceCandidate(senderId, candidate) {
        const pc = this.peerConnections.get(senderId);
        if (pc) {
            try {
                await pc.addIceCandidate(new RTCIceCandidate(candidate));
            } catch (error) {
                this.log(`Error adding ICE candidate: ${error.message}`);
            }
        }
    }

    showTrainerScreen() {
        this.log('Switching to trainer screen view - hiding normal interface');
        
        // FIXED Jan 28, 2025: Updated element selectors to target actual DOM elements in index2.php
        // Hide main interface elements for trainees when viewing trainer screen
        const elementsToHide = [
            '#titleBar',
            '#videoWindow', 
            '#volunteerDetailsTitle',
            '#mainPane',
            '#newSearchPaneControls',
            '#resourceDetailControl',
            '#chatPane',
            '#callHistoryPane',
            '#logPane',
            '#oneChatOnlyDiv',
            '#ExitButton',
            '#statsButton'
        ];
        
        elementsToHide.forEach(selector => {
            const element = document.querySelector(selector);
            if (element) {
                element.style.display = 'none';
                this.log(`Hidden element: ${selector}`);
            } else {
                this.log(`Element not found: ${selector}`);
            }
        });
        
        // FIXED Jan 28, 2025: Enhanced video styling for proper full-screen display
        // Ensure remote video is visible and prominent
        const remoteVideo = document.getElementById('remoteVideo');
        if (remoteVideo) {
            remoteVideo.style.display = 'block';
            remoteVideo.style.position = 'fixed';
            remoteVideo.style.top = '0';
            remoteVideo.style.left = '0';
            remoteVideo.style.width = '100vw';
            remoteVideo.style.height = '100vh';
            remoteVideo.style.zIndex = '9999';
            remoteVideo.style.backgroundColor = 'black';
            remoteVideo.style.objectFit = 'contain';  // FIXED: Maintain aspect ratio
            remoteVideo.style.visibility = 'visible'; // FIXED: Ensure visibility
            
            // FIXED: Ensure video is not hidden by any CSS
            remoteVideo.removeAttribute('hidden');
            
            // Force the video to be visible and on top
            document.body.style.overflow = 'hidden';
            
            this.log('Remote video styled for full-screen trainer view');
        } else {
            this.log('ERROR: Remote video element not found for styling');
        }
        
        // Add a small indicator that trainee is viewing trainer screen
        let indicator = document.getElementById('trainer-screen-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'trainer-screen-indicator';
            indicator.innerHTML = '📺 Viewing Trainer Screen';
            indicator.style.position = 'fixed';
            indicator.style.top = '10px';
            indicator.style.right = '10px';
            indicator.style.background = 'rgba(0,0,0,0.8)';
            indicator.style.color = 'white';
            indicator.style.padding = '8px 12px';
            indicator.style.borderRadius = '4px';
            indicator.style.zIndex = '10000';
            indicator.style.fontSize = '14px';
            document.body.appendChild(indicator);
        }
        
        this.log('Switched to trainer screen view');
    }
}

// Global instance
window.simpleTrainingScreenShare = new SimpleTrainingScreenShare();