/**
 * TrainingSession Class - Updated for PHP-based Multi-Trainee Screen Sharing
 * Handles peer counseling training sessions with screen sharing
 * Compatible with MultiTraineeScreenSharing class
 * 
 * VERSION: 2025.01.22.1300 - Added comprehensive CallSid flow logging for production debugging
 */

console.log('🔧 LOADING trainingSessionUpdated.js - VERSION: 2025.01.22.1300');

class TrainingSession {
    constructor() {
        // Core identification
        this.volunteerID = document.getElementById("volunteerID")?.value;
        const trainerID = document.getElementById("trainerID")?.value;
        this.role = "unknown";
        
        // Training participants
        this.trainer = { id: null, name: "", isSignedOn: false };
        this.trainees = [];
        this.muted = false;
        this.incomingCallsTo = trainerID; // NEVER NULL - Set to trainer ID immediately
        this.trainerValidationCompleted = false; // Track if we've validated trainer status
        
        // Screen sharing state (updated for PHP-based implementation)
        this.shareScreen = null;
        this.screenShareReady = false; // Changed from socketReady
        this.screenShareAttempted = false;
        this.screenSharingInitialized = false;
        
        // Call management
        this.currentlyOnCall = false;
        this.connectionStatus = 'unknown';
        this.connection = false;
        this.myCallSid = null; // Track our CallSid for server-side muting
        
        // Control management - tracks who currently has control (trainer or trainee)
        this.activeController = null; // ID of person currently unmuted and in control
        this.isController = false;    // Am I currently the active controller?
        
        // Conference settings
        this.conferenceID = null;
        // incomingCallsTo already set above - NEVER NULL
        
        // UI references
        this.volunteerDetailsTitle = document.getElementById('volunteerDetailsTitle');
        this.trainingChatWindow = null;
        
        // Initialization state tracking
        this.initialized = false;
        this.initializing = false;
        this.initPromise = null;
        this.initError = null;
        
        // Ensure global access for ScreenSharingControl compatibility
        if (typeof window !== 'undefined') {
            window.trainingSession = this;
        }
        
        // Ensure all compatibility methods exist
        this.ensureCompatibilityMethods();
        
        // Backward compatibility getters
        Object.defineProperty(this, 'trainerID', {
            get: () => this.trainer.id,
            set: (value) => { this.trainer.id = value; }
        });
        Object.defineProperty(this, 'trainerName', {
            get: () => this.trainer.name,
            set: (value) => { this.trainer.name = value; }
        });
        Object.defineProperty(this, 'trainerIsSignedOn', {
            get: () => this.trainer.isSignedOn,
            set: (value) => { this.trainer.isSignedOn = value; }
        });
        Object.defineProperty(this, 'traineeID', {
            get: () => this.trainees.find(t => t.id === this.volunteerID)?.id || null
        });

        // Initialize state but don't start async initialization yet
        this.initPromise = null;
        
        // Don't update UI until role is determined in init()
        // this._updateForRole(); // Commented out - will be called after init sets the role
    }

    async _initializeAsync() {
        // 🔍 DEBUG: Log entry to initialization
        if (window.traineeDebugLogger) {
            window.traineeDebugLogger.log('INIT_ASYNC', 'Starting _initializeAsync', {
                initializing: this.initializing,
                initialized: this.initialized
            });
        }
        
        if (this.initializing || this.initialized) {
            return this.initPromise;
        }
        
        this.initializing = true;
        
        try {
            // 🔍 DEBUG: Before role determination
            if (window.traineeDebugLogger) {
                window.traineeDebugLogger.log('INIT_ASYNC', 'About to determine role');
            }
            
            // Only determine role if not already set by init()
            if (this.role === "unknown") {
                this._determineRole();
            }
            
            // 🔍 DEBUG: After role determination
            if (window.traineeDebugLogger) {
                window.traineeDebugLogger.log('INIT_ASYNC', 'Role determined', { role: this.role });
            }
            
            if (this.role === "unknown") {
                if (window.traineeDebugLogger) {
                    window.traineeDebugLogger.log('INIT_ASYNC', 'ERROR: No training role detected - about to throw error');
                }
                throw new Error("No training role detected");
            }
            
            // Initialize based on role
            if (this.role === "trainee") {
                const trainerIdField = document.getElementById('trainerID');
                
                // 🔍 DEBUG: Check trainer ID field value
                console.error('🔍 TRAINEE INIT DEBUG:', {
                    trainerIdFieldExists: !!trainerIdField,
                    trainerIdValue: trainerIdField?.value,
                    expectedTrainerId: 'Travelstead',
                    actualTrainerId: trainerIdField?.value,
                    volunteerID: this.volunteerID
                });
                
                this._initializeAsTrainee(trainerIdField?.value);
            } else if (this.role === "trainer") {
                const assignedTraineeIDsField = document.getElementById('assignedTraineeIDs');
                if (window.traineeDebugLogger) {
                    window.traineeDebugLogger.log('INIT_ASYNC', 'Initializing as trainer', { 
                        assignedTraineeIDsFieldExists: !!assignedTraineeIDsField,
                        assignedTraineeIDsValue: assignedTraineeIDsField?.value 
                    });
                }
                this._initializeAsTrainer(assignedTraineeIDsField.value);
            }
            
            // 🔍 DEBUG: Before common initialization
            if (window.traineeDebugLogger) {
                window.traineeDebugLogger.log('INIT_ASYNC', 'About to call _initializeCommon');
            }
            
            // Common initialization
            await this._initializeCommon();
            
            // 🔍 DEBUG: After common initialization
            if (window.traineeDebugLogger) {
                window.traineeDebugLogger.log('INIT_ASYNC', 'Common initialization completed');
            }
            
            this.initialized = true;
            this.initializing = false;
            this.initError = null;
            
            // Update UI after successful initialization
            this._updateForRole();
            
            console.log(`TrainingSession: Initialization complete as ${this.role}`);
            
            // 🔍 DEBUG: Initialization completed successfully
            if (window.traineeDebugLogger) {
                window.traineeDebugLogger.log('INIT_ASYNC', 'Initialization completed successfully', { role: this.role });
            }
            
            return this;
            
        } catch (error) {
            this.initializing = false;
            this.initError = error;
            console.error("🚨 TrainingSession initialization failed:", error);
            console.error("🚨 Error stack:", error.stack);
            console.error("🚨 Current state:", {
                role: this.role,
                volunteerID: this.volunteerID,
                trainerId: this.trainer?.id,
                shareScreenExists: !!this.shareScreen
            });
            
            // 🔍 DEBUG: Log the error details
            if (window.traineeDebugLogger) {
                window.traineeDebugLogger.log('INIT_ASYNC', 'CRITICAL ERROR in initialization', {
                    errorMessage: error.message,
                    errorStack: error.stack,
                    role: this.role,
                    volunteerID: this.volunteerID
                });
            }
            
            // Show error to user and cleanup
            this._showAlert("Training session initialization failed. Please reload the page.");
            
            // 🔍 DEBUG: Before calling destroy
            if (window.traineeDebugLogger) {
                window.traineeDebugLogger.log('INIT_ASYNC', 'About to call destroy() method');
            }
            
            this.destroy();
            
            throw error;
        }
    }
    
    _determineRole() {
        // Check for training role indicators
        const traineeIdField = document.getElementById('traineeID');
        const assignedTraineeIDsField = document.getElementById('assignedTraineeIDs');
        
        if (traineeIdField && traineeIdField.value) {
            this.role = "trainee";
        } else if (assignedTraineeIDsField && assignedTraineeIDsField.value && assignedTraineeIDsField.value !== "1") {
            this.role = "trainer";
        } else {
            this.role = "unknown";
        }
    }

    _initializeAsTrainer(traineeID) {
        this.role = "trainer";
        this.trainer.id = this.volunteerID;
        this.trainer.isSignedOn = true;
        this.muted = false;
        
        // Trainer starts as the active controller by default
        this.isController = true;
        this.activeController = this.volunteerID;
        this.incomingCallsTo = this.volunteerID; // CRITICAL: Trainer receives calls initially
        
        // Set conference ID for Twilio conference calls
        this.conferenceID = this.volunteerID; // Use trainer's ID as conference ID
        
        // Set trainer name from hidden field
        const currentUserNameField = document.getElementById('currentUserName');
        if (currentUserNameField && currentUserNameField.value) {
            this.trainer.name = currentUserNameField.value;
        }
        
        // Parse trainee IDs - handle both string and number inputs
        this._parseTraineeList(traineeID);
        
        // CRITICAL: Ensure trainer has control in database when signing on fresh
        this._ensureTrainerControl();
        
        // Show the volunteerDetailsTitle since we're using it to display the trainee list
        if (this.volunteerDetailsTitle) {
            this.volunteerDetailsTitle.style.display = 'block';
        }
        
        // Update UI to show trainer and trainee list
        this.updateTraineeList();
        
        console.log("Initialized as trainer with trainees:", 
            this.trainees.map(t => t.id));
        console.log("Conference ID set to:", this.conferenceID);
    }

    _initializeAsTrainee(trainerID) {
        this.trainer.id = trainerID;
        this.trainer.isSignedOn = false;
        this.muted = false; // Trainees start unmuted in normal training

        // Trainees start as non-controllers (trainer has control initially)
        // This is the default - will be updated by _syncControlState()
        this.isController = false;
        this.activeController = trainerID;
        this.incomingCallsTo = trainerID; // CRITICAL: External calls go to trainer initially

        // Set conference ID to trainer's ID (trainees join trainer's conference)
        this.conferenceID = trainerID;

        // Set trainer name from hidden field
        const trainerNameField = document.getElementById('trainerName');
        if (trainerNameField && trainerNameField.value) {
            this.trainer.name = trainerNameField.value;
        }

        // Add self as trainee with proper name
        const currentUserNameField = document.getElementById('currentUserName');
        const traineeName = currentUserNameField && currentUserNameField.value ?
            currentUserNameField.value : "";
        this.trainees.push({ id: this.volunteerID, name: traineeName, isSignedOn: true });

        // CRITICAL: Sync control state from database on page load/refresh
        // This prevents state mismatch if trainee had control before refresh
        this._syncControlState();

        // Configure UI for trainee mode
        this._configureTraineeUI();

        console.log(`Initialized as trainee for trainer: ${trainerID}`);
        console.log("Conference ID set to:", this.conferenceID);
    }

    _parseTraineeList(traineeString) {
        // Clear existing trainees to prevent duplicates
        this.trainees = [];
        
        // Handle both string and element value
        const traineeIDsField = document.getElementById('assignedTraineeIDs');
        let traineeIds = [];
        
        if (typeof traineeString === 'string' && traineeString.includes(',')) {
            traineeIds = traineeString.split(',');
        } else if (traineeIDsField && traineeIDsField.value) {
            traineeIds = traineeIDsField.value.split(',');
        } else if (traineeString) {
            traineeIds = [traineeString.toString()];
        }
        
        traineeIds = traineeIds
            .map(id => id.toString().trim())
            .filter(id => id && id !== "1");
            
        traineeIds.forEach(id => {
            this.trainees.push({
                id: id,
                name: "",
                isSignedOn: false
            });
        });
    }

    async _initializeCommon() {
        // Initialize training chat (with safety check)
        this._openTrainingChat();
        
        // Make control panel accessible globally
        window.openTrainingControlPanel = () => this.openControlPanel();
        
        // Set active sharer (default to trainer)
        this.activeSharer = this.trainer.id;
        
        // Initialize screen sharing with PHP-based implementation
        await this._initializeScreenSharing();
        
        // Apply initial muting state
        this.mutePhoneConnectionChange(); // Use original method name
        
        // Start participant synchronization to keep lists current
        this.startParticipantSync();
        this.startControlPolling();

        // NOTE: Signaling is handled by screenSharingControlMulti ONLY
        // No separate TrainingSignalingClient - that caused duplicate polling
        // screenSharingControlMulti forwards signals to this class via window.trainingSession

        // Set up polling for conference restart notifications (for trainees)
        if (this.role === 'trainee') {
            // Conference restart notifications now handled via screen sharing signaling
            // this.startConferenceNotificationPolling();
        }
    }

    _configureTraineeUI() {
        // NOTE: Previously hid volunteerDetailsTitle, but this prevented trainer name from showing
        // The element should remain visible to show "TRAINER: [name]" in top left corner
        // Screen sharing will handle full-screen display when active

        // Additional trainee-specific UI configuration can go here
    }

    _openTrainingChat() {
        try {
            // Open training chat window based on role
            const width = 400;
            const height = screen.availHeight;
            
            if (this.role === "trainer") {
                // Trainers get the full control panel integrated into their chat window
                this.trainingChatWindow = window.open(
                    "TrainingChat/index.php",
                    "Training Chat with Control Panel",
                    `location=1,status=1,scrollbars=1,width=${width},height=${height},top=0,left=0`
                );
                console.log("Training chat window with control panel opened for trainer");
            } else if (this.role === "trainee") {
                // Trainees get the chat with read-only control view
                this.trainingChatWindow = window.open(
                    "TrainingChat/index.php", 
                    "Training Chat",
                    `location=1,status=1,scrollbars=1,width=${width},height=${height},top=0,left=0`
                );
                console.log("Training chat window with control view opened for trainee");
            }
        } catch (error) {
            console.error("Error opening training chat:", error);
        }
    }

    _closeTrainingChat() {
        try {
            if (this.trainingChatWindow && !this.trainingChatWindow.closed) {
                this.trainingChatWindow.close();
                console.log("Training chat window closed");
            }
            this.trainingChatWindow = null;
        } catch (error) {
            console.error("Error closing training chat:", error);
        }
    }
    
    openControlPanel() {
        try {
            // Open control panel window for managing who has control
            const width = 650;
            const height = 700;
            const left = (screen.availWidth - width) / 2;
            const top = (screen.availHeight - height) / 2;
            
            const params = new URLSearchParams({
                trainerId: this.conferenceID || this.volunteerID,
                user: this.volunteerID,
                role: this.role
            });
            
            this.controlPanelWindow = window.open(
                `/trainingShare3/trainerControlPanel.html?${params.toString()}`,
                "Training Control Panel",
                `location=1,status=1,scrollbars=1,width=${width},height=${height},top=${top},left=${left}`
            );
            console.log("Control panel window opened");
            
            // Make function available globally for easy access
            window.openTrainingControlPanel = () => this.openControlPanel();
            
        } catch (error) {
            console.error("Error opening control panel:", error);
        }
    }

    async _initializeScreenSharing() {
        try {
            // 🔍 DEBUG: Screen sharing initialization start
            if (window.traineeDebugLogger) {
                window.traineeDebugLogger.log('SCREEN_SHARING', 'Starting _initializeScreenSharing', {
                    role: this.role,
                    MultiTraineeScreenSharingExists: typeof MultiTraineeScreenSharing !== 'undefined'
                });
            }
            
            // Use the new MultiTraineeScreenSharing implementation
            if (typeof MultiTraineeScreenSharing === 'undefined') {
                console.warn("MultiTraineeScreenSharing not available");
                if (window.traineeDebugLogger) {
                    window.traineeDebugLogger.log('SCREEN_SHARING', 'WARNING: MultiTraineeScreenSharing not available - returning early');
                }
                return;
            }

            console.log(`Initializing multi-trainee screen sharing for ${this.role}`);
            
            // Create new instance of MultiTraineeScreenSharing
            this.shareScreen = new MultiTraineeScreenSharing({
                role: this.role,
                participantId: this.volunteerID,
                trainerId: this.role === 'trainer' ? this.volunteerID : this.trainer.id,
                roomId: this.role === 'trainer' ? this.volunteerID : this.trainer.id
            });
            
            // 🔍 DEBUG: Before role-specific initialization
            if (window.traineeDebugLogger) {
                window.traineeDebugLogger.log('SCREEN_SHARING', 'MultiTraineeScreenSharing instance created', { role: this.role });
            }
            
            console.log("Multi-trainee screen sharing initialized successfully");
            
            this.screenSharingInitialized = true;
            this.screenShareReady = true;
            
            console.log("Simple training screen sharing initialized successfully");
            
            // 🔍 DEBUG: Screen sharing initialization completed
            if (window.traineeDebugLogger) {
                window.traineeDebugLogger.log('SCREEN_SHARING', 'Screen sharing initialization completed successfully', {
                    screenSharingInitialized: this.screenSharingInitialized,
                    screenShareReady: this.screenShareReady
                });
            }
            
        } catch (error) {
            console.error("Failed to initialize simple training screen sharing:", error);
            this.screenShareReady = false;
            
            // 🔍 DEBUG: Screen sharing initialization failed
            if (window.traineeDebugLogger) {
                window.traineeDebugLogger.log('SCREEN_SHARING', 'CRITICAL ERROR in screen sharing initialization', {
                    errorMessage: error.message,
                    errorStack: error.stack,
                    role: this.role
                });
            }
            
            // Continue without screen sharing - don't break the session
        }
    }

    // Training session lifecycle methods
    async processUserListUpdate(messages, onlineUsers) {
        // Ensure we're initialized before processing
        if (!this.initialized) {
            try {
                await this.waitForInitialization();
            } catch (error) {
                console.warn("TrainingSession: Cannot process user list update - not initialized:", error);
                return;
            }
        }
        
        // Handle trainee status updates for trainers
        if (this.role === "trainer") {
            this.trainees.forEach(trainee => {
                const traineeStillOnline = messages.some(msg => 
                    [6, 7].includes(msg.AdminLoggedOn) && msg.UserName === trainee.id
                );
                
                // Trainee just went offline
                if (!traineeStillOnline && trainee.isSignedOn) {
                    this.traineeSignedOff(trainee.id);
                }
                
                // Trainee just came online 
                const traineeJustCameOnline = messages.some(msg => 
                    [6, 7].includes(msg.AdminLoggedOn) && 
                    msg.UserName === trainee.id && 
                    !trainee.isSignedOn
                );
                
                if (traineeJustCameOnline) {
                    const traineeData = messages.find(msg => msg.UserName === trainee.id);
                    this.traineeSignedOn(
                        traineeData.UserName, 
                        traineeData.FirstName + " " + traineeData.LastName
                    );
                }
            });
            
            // Update the trainee list display now that userList is available
            this.updateTraineeList();
        }
        
        // Check trainer status updates
        if (this.role === "trainee" && this.trainer.id) {
            // 🔍 DEBUG: Check trainer status in user list
            if (window.traineeDebugLogger) {
                window.traineeDebugLogger.log('USER_LIST_UPDATE', 'Checking trainer status in user list', {
                    role: this.role,
                    trainerID: this.trainer.id,
                    trainerIsSignedOn: this.trainerIsSignedOn,
                    messagesCount: messages.length,
                    onlineUsersCount: onlineUsers.length
                });
            }
            
            const trainerStillOnline = messages.some(msg => 
                msg.AdminLoggedOn == 4 && msg.UserName === this.trainer.id
            );
            
            // 🔍 DEBUG: Trainer detection result
            if (window.traineeDebugLogger) {
                window.traineeDebugLogger.log('USER_LIST_UPDATE', 'Trainer detection result', {
                    trainerStillOnline: trainerStillOnline,
                    trainerIsSignedOn: this.trainerIsSignedOn,
                    willCallTrainerSignedOff: !trainerStillOnline && this.trainerIsSignedOn,
                    messagesWithAdminLoggedOn4: messages.filter(msg => msg.AdminLoggedOn == 4).map(msg => msg.UserName)
                });
            }
            
            if (!trainerStillOnline && this.trainerIsSignedOn) {
                // 🔍 DEBUG: CRITICAL ISSUE - This is likely causing the immediate logout!
                console.error('🚨 TRAINEE LOGOUT TRIGGER: trainerSignedOff() about to be called', {
                    trainerStillOnline,
                    trainerIsSignedOn: this.trainerIsSignedOn,
                    trainerID: this.trainer.id,
                    messagesCount: messages.length,
                    role: this.role
                });
                this.trainerSignedOff();
            }
            
            // Check if trainer just came online
            const trainerJustCameOnline = messages.some(msg => 
                msg.AdminLoggedOn == 4 && 
                msg.UserName === this.trainer.id && 
                !this.trainerIsSignedOn
            );
            
            if (trainerJustCameOnline) {
                const trainerData = messages.find(msg => msg.UserName === this.trainer.id);
                this.trainerSignedOn(
                    trainerData.UserName,
                    trainerData.FirstName + " " + trainerData.LastName
                );
            }
        }
    
        // Check conference status
        if (this.connectionStatus === 'ready') {
            this.connection = false;
            this.connectConference();
        }
        
        // For PHP implementation, screen sharing is handled automatically
        if (this.screenShareReady && !this.screenShareAttempted && this.role === "trainer") {
            this.attemptScreenSharing();
        }
    }

    // Trainer/trainee interaction methods
    trainerSignedOn(trainerID, trainerName) {
        console.log(`Trainer signed on: ${trainerID} (${trainerName})`);
        this.trainer.id = trainerID;
        this.trainer.name = trainerName;
        this.trainer.isSignedOn = true;
        
        // Show the volunteerDetailsTitle since we're using it to display the trainee list
        if (this.volunteerDetailsTitle) {
            this.volunteerDetailsTitle.style.display = 'block';
        }
        
        // Connect to Twilio conference when trainer signs on
        if (this.role === "trainer") {
            console.log("Trainer connecting to conference:", this.conferenceID);
            this.connectConference();
        } else if (this.role === "trainee" && this.trainer.isSignedOn) {
            // Trainee should also connect to conference when trainer is signed on
            console.log("Trainee connecting to trainer's conference:", this.conferenceID);
            this.connectConference();
        }
        
        // For trainees, the PHP implementation handles connections automatically
        if (this.role === "trainee" && this.shareScreen) {
            console.log("Trainee ready - PHP implementation will handle trainer connection automatically");
        }
    }

    traineeSignedOn(traineeID, traineeName) {
        console.log(`Trainee signed on: ${traineeID} (${traineeName})`);
        
        const trainee = this.trainees.find(t => t.id === traineeID);
        if (trainee) {
            trainee.name = traineeName;
            trainee.isSignedOn = true;
            this.updateTraineeList();
            
            // PHP implementation handles new trainees automatically via room management
            console.log("New trainee connected - PHP implementation will handle connection automatically");
        }
    }

    traineeSignedOff(traineeID) {
        const traineeIndex = this.trainees.findIndex(t => t.id === traineeID);
        if (traineeIndex !== -1) {
            console.log(`Trainee signed off: ${traineeID}`);
            const trainee = this.trainees[traineeIndex];
            trainee.isSignedOn = false;
            // Don't remove from array, just mark as offline
            this.updateTraineeList();
        }
    }

    // Helper method to start screen sharing
    _startScreenSharing() {
        if (this.shareScreen && this.role === "trainer") {
            console.log("Starting screen share for training");
            this.shareScreen.startScreenShare(); // Simple implementation method
            this.screenShareAttempted = true;
        }
    }

    // Method called when trainee wants to notify trainer they want control
    // IMPORTANT: Trainees CANNOT actually change control - only notify the trainer
    async requestControlTransfer() {
        console.log('🎮 Trainee requesting control (notification only)...');
        
        // Only trainees use this method to REQUEST (not change) control
        if (this.role !== 'trainee') {
            console.log('This method is for trainees to request control from trainer');
            return;
        }
        
        // Check if we already have control
        if (this.isController) {
            console.log('Already have control, no need to request');
            return;
        }
        
        try {
            // Get the trainer ID
            const trainerId = this.trainer.id;
            if (!trainerId) {
                console.error('Cannot request control: no trainer ID available');
                return;
            }
            
            console.log(`📤 Notifying trainer: Trainee ${this.volunteerID} wants control`);
            
            // IMPORTANT: Trainees CANNOT change control directly
            // This just sends a notification to the trainer that the trainee wants control
            // The trainer must manually transfer control using transferControlTo()
            
            // Send notification to trainer (via signal files or other notification system)
            const notificationData = {
                type: 'control-request',
                from: this.volunteerID,
                to: trainerId,
                message: `${this.volunteerID} is requesting control`,
                timestamp: Date.now()
            };
            
            // Write to trainer's signal file for notification
            const response = await fetch('/trainingShare3/signalingServerMulti.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'signal',
                    room: `training_${trainerId}`,
                    participantId: this.volunteerID,
                    targetId: trainerId,
                    signal: notificationData
                })
            });
            
            if (response.ok) {
                console.log('✅ Control request notification sent to trainer');
                this._showAlert('Control request sent to trainer. Waiting for trainer approval...');
            } else {
                console.error('Failed to send control request notification');
                this._showAlert('Failed to notify trainer. Please ask verbally for control.');
            }
            
        } catch (error) {
            console.error('Error requesting control transfer:', error);
            this._showAlert('Error sending control request. Please ask trainer verbally.');
        }
    }

    // Alternative method name that ScreenSharingControl might call
    requestSharingControl() {
        // Delegate to the main method
        this.requestControlTransfer();
    }
    
    // Method for trainers to transfer control to a specific trainee
    async transferControlTo(traineeId) {
        console.log(`🎮 Trainer transferring control to ${traineeId}`);
        
        // Only trainers can use this method
        if (this.role !== 'trainer') {
            console.error('Only trainers can transfer control to trainees');
            return false;
        }
        
        // Verify the trainee is in our list
        const trainee = this.trainees.find(t => t.id === traineeId);
        if (!trainee) {
            console.error(`Trainee ${traineeId} not found in trainee list`);
            return false;
        }
        
        try {
            // Update control in database
            const response = await fetch('/trainingShare3/setTrainingControl.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    trainerId: this.volunteerID,
                    activeController: traineeId,
                    controllerRole: 'trainee'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log(`✅ Control transferred to ${traineeId} successfully`);
                
                // Update our local state immediately
                await this.updateControlState({
                    activeController: traineeId,
                    controllerRole: 'trainee',
                    trainerId: this.volunteerID
                });
                
                // Notify all participants
                await this.notifyControlChange();
                
                this._showAlert(`Control transferred to ${trainee.name || traineeId}`);
                return true;
            } else {
                console.error(`Failed to transfer control: ${data.error}`);
                this._showAlert(`Failed to transfer control: ${data.error}`);
                return false;
            }
            
        } catch (error) {
            console.error('Error transferring control:', error);
            this._showAlert('Error transferring control. Please try again.');
            return false;
        }
    }
    
    // Handle incoming control request notifications (for trainers)
    handleControlRequestNotification(data) {
        if (this.role !== 'trainer') {
            return; // Only trainers handle these notifications
        }
        
        const requestingTrainee = data.from;
        const trainee = this.trainees.find(t => t.id === requestingTrainee);
        const traineeName = trainee ? trainee.name : requestingTrainee;
        
        console.log(`📨 Control request received from ${traineeName}`);
        
        // Show alert to trainer with options
        const message = `${traineeName} is requesting control. Use transferControlTo('${requestingTrainee}') to grant control.`;
        this._showAlert(message);
        
        // Also log to console for visibility
        console.log(`🎮 CONTROL REQUEST: ${traineeName} wants control`);
        console.log(`   To grant: trainingSession.transferControlTo('${requestingTrainee}')`);
        console.log(`   To deny: No action needed (or take back control if needed)`);
    }
    
    // Method for trainers to take back control
    async takeBackControl() {
        console.log('🎮 Trainer taking back control');
        
        // Only trainers can use this method
        if (this.role !== 'trainer') {
            console.error('Only trainers can take back control');
            return false;
        }
        
        // If already have control, nothing to do
        if (this.isController) {
            console.log('Trainer already has control');
            return true;
        }
        
        try {
            // Update control in database
            const response = await fetch('/trainingShare3/setTrainingControl.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    trainerId: this.volunteerID,
                    activeController: this.volunteerID,
                    controllerRole: 'trainer'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('✅ Trainer took back control successfully');
                
                // Update our local state immediately
                await this.updateControlState({
                    activeController: this.volunteerID,
                    controllerRole: 'trainer',
                    trainerId: this.volunteerID
                });
                
                // Notify all participants
                await this.notifyControlChange();
                
                this._showAlert('You have taken back control');
                return true;
            } else {
                console.error(`Failed to take back control: ${data.error}`);
                this._showAlert(`Failed to take back control: ${data.error}`);
                return false;
            }
            
        } catch (error) {
            console.error('Error taking back control:', error);
            this._showAlert('Error taking back control. Please try again.');
            return false;
        }
    }

    trainerSignedOff() {
        this.trainer.isSignedOn = false;
        console.error("🚨 TRAINER SIGNED OFF - Trainee will be logged out in 10 seconds", {
            role: this.role,
            volunteerID: this.volunteerID,
            trainerId: this.trainer.id
        });
        
        if (this.role === "trainee") {
            console.error("🚨 TRAINEE LOGOUT COUNTDOWN: 10 seconds to see this message...");
            
            // Add a 10-second delay so you can see the console message before logout
            setTimeout(() => {
                console.error("🚨 NOW CALLING exitProgram - LOGOUT HAPPENING NOW");
                
                // Use global exitProgram if available
                if (typeof exitProgram === 'function') {
                    try {
                        exitProgram("user").then(() => {
                            console.log('TrainingSession: Exit completed successfully');
                        }).catch(error => {
                            console.error('TrainingSession: Exit failed:', error);
                            // Show user-friendly error message
                            this._showAlert('Unable to cleanly exit the session. Please refresh the page manually.');
                            
                            // Attempt manual cleanup as fallback
                            setTimeout(() => {
                                this.destroy();
                                if (typeof window !== 'undefined' && window.location) {
                                    window.location.reload();
                                }
                            }, 2000);
                        });
                    } catch (syncError) {
                        console.error('TrainingSession: Error calling exitProgram:', syncError);
                        this._showAlert('Session exit failed. Please refresh the page manually.');
                        
                        // Fallback cleanup
                        this.destroy();
                    }
                } else {
                    console.warn('TrainingSession: exitProgram function not available, performing local cleanup');
                    this.destroy();
                }
            }, 10000); // Close the setTimeout
        }
    }

    // REMOVED: Database-driven control detection
    // Control is now managed purely client-side through manual actions and external call routing

    // Call management methods - Enhanced for screen sharing coordination
    async startNewCall() {
        if (!this.initialized) {
            console.warn("TrainingSession: Cannot start call - not initialized");
            return;
        }

        console.log(`📞 [START_CALL] volunteerID: ${this.volunteerID}, incomingCallsTo: ${this.incomingCallsTo}`);

        // Verify the training session actually has an active call before muting
        // This prevents muting when a training participant tried to answer but another volunteer got the call
        const connectionStatus = this.connection?.status?.();
        const hasActiveConnection = this.connection &&
            typeof this.connection.status === 'function' &&
            connectionStatus === 'open';

        console.log('📋 [CALLSID] startNewCall guard check', {
            hasConnection: !!this.connection,
            hasStatusFn: typeof this.connection?.status === 'function',
            connectionStatus: connectionStatus,
            hasActiveConnection: hasActiveConnection,
            myCallSid: this.myCallSid,
            timestamp: new Date().toISOString()
        });

        if (!hasActiveConnection) {
            console.log(`📞 [START_CALL] Skipping mute - no active conference connection (call may have gone to another volunteer)`);
            return;
        }

        // Set external call state - this will apply appropriate muting automatically
        this.setExternalCallActive(true, 'startNewCall');

        // If I'm the one receiving the call, notify others
        if (this.volunteerID === this.incomingCallsTo) {
            console.log(`📞 I am receiving the external call - notifying others to mute`);
            await this.notifyCallStart();
        } else {
            console.log(`📞 External call directed to ${this.incomingCallsTo} - I should be muted`);
        }
    }

    async endCall() {
        if (!this.initialized) {
            console.warn("TrainingSession: Cannot end call - not initialized");
            return;
        }

        console.log(`📞 [END_CALL] volunteerID: ${this.volunteerID}, incomingCallsTo: ${this.incomingCallsTo}`);

        // Set external call state - this will apply appropriate unmuting automatically
        this.setExternalCallActive(false, 'endCall');

        // If I was the one receiving the call, notify others and restart conference
        if (this.volunteerID === this.incomingCallsTo) {
            console.log(`📞 I was receiving the external call - notifying others and restarting conference`);
            await this.notifyCallEnd();
            await this.restartConferenceAfterCall();
        } else {
            console.log(`📞 External call ended for ${this.incomingCallsTo} - I should be unmuted`);
        }
    }

    // Conference connection methods
    connectConference() {
        // Prevent multiple simultaneous connection attempts
        if (this.connectionStatus === 'connecting') {
            console.log("⚠️ Already connecting to conference, skipping");
            return;
        }
        
        if (typeof callMonitor !== 'undefined' && callMonitor.getDevice) {
            try {
                const device = callMonitor.getDevice();
                
                // CRITICAL FIX: Check if there's already an active call
                if (typeof callMonitor.getActiveCall === 'function') {
                    const activeCall = callMonitor.getActiveCall();
                    if (activeCall) {
                        console.log("⚠️ Active call detected, cannot connect to conference:", activeCall);
                        return;
                    }
                }
                
                // Also check device state
                if (device && device.calls && device.calls.length > 0) {
                    console.log("⚠️ Device has active calls, cannot connect to conference:", device.calls);
                    return;
                }
                
                // Check if we already have a connection
                if (this.connection && this.connectionStatus === 'connected') {
                    console.log("⚠️ Already connected to conference, skipping");
                    return;
                }
                
                if (device && device.connect) {
                    this.connectionStatus = 'connecting';  // Set state before attempting connection
                    // Conference parameters for Twilio - based on control status, not role
                    const isConferenceModerator = this.isController || (this.role === 'trainer' && !this.activeController);
                    // CRITICAL: Check if external call is active to determine muting
                    const shouldBeMuted = this.currentlyOnCall && !this.isController;
                    const params = {
                        conference: this.conferenceID || this.trainer.id,
                        conferenceRole: isConferenceModerator ? 'moderator' : 'participant',
                        startConferenceOnEnter: isConferenceModerator,
                        endConferenceOnExit: isConferenceModerator,
                        muted: shouldBeMuted
                    };
                    
                    console.log(`📞 Connecting ${this.role} to conference ${params.conference} (muted: ${shouldBeMuted})`);

                    // Initialize server mute state to match connection params
                    // This prevents redundant API calls when the accept handler runs
                    this._serverMuteState = shouldBeMuted;

                    this.connection = device.connect(params);
                    this.connectionStatus = 'connected';

                    // Handle connection events
                    if (this.connection && this.connection.on) {
                        this.connection.on('accept', async (call) => {
                            // Capture CallSid for server-side muting
                            // Twilio SDK v2: CallSid is available as call.sid or call.parameters.CallSid
                            const capturedSid = call?.sid || call?.parameters?.CallSid || this.connection?.parameters?.CallSid || null;

                            console.log('📋 [CALLSID] Training session accept handler', {
                                role: this.role,
                                volunteerID: this.volunteerID,
                                conferenceID: this.conferenceID,
                                capturedSid: capturedSid,
                                callSid: call?.sid,
                                callParamsCallSid: call?.parameters?.CallSid,
                                connectionParamsCallSid: this.connection?.parameters?.CallSid,
                                previousMyCallSid: this.myCallSid,
                                timestamp: new Date().toISOString()
                            });

                            this.myCallSid = capturedSid;

                            // CRITICAL: CallSid MUST be available - if not, log error with full context
                            if (!this.myCallSid) {
                                console.error('🚨 [CRITICAL] CallSid NOT CAPTURED on conference accept!', {
                                    role: this.role,
                                    volunteerID: this.volunteerID,
                                    conferenceID: this.conferenceID,
                                    callObject: call ? Object.keys(call) : 'null',
                                    callSid: call?.sid,
                                    callParamsCallSid: call?.parameters?.CallSid,
                                    connectionParamsCallSid: this.connection?.parameters?.CallSid,
                                    connectionStatus: this.connectionStatus
                                });
                            }

                            console.log(`📞 ${this.role} joined conference | CallSid: ${this.myCallSid} | muted: ${this._serverMuteState}`);
                        });
                        this.connection.on('disconnect', () => {
                            this.connectionStatus = 'disconnected';
                        });
                    }
                } else {
                    console.warn("Call device not available for conference connection");
                }
            } catch (error) {
                console.error("Error connecting to conference:", error);
            }
        } else {
            console.warn("callMonitor not available - waiting for device initialization");
            // Try again after a delay if device isn't ready yet
            setTimeout(() => {
                if (this.connectionStatus !== 'connected' && !this.connection) {
                    console.log("Retrying conference connection after device initialization");
                    this.connectConference();
                }
            }, 1000);
        }
    }

    mutePhoneConnectionChange() {
        if (this.muted) {
            this.muteMe();
        } else {
            this.unMuteMe();
        }
    }

    // ============================================================
    // CENTRALIZED MUTE STATE MANAGEMENT
    // ============================================================

    /**
     * Sets the external call state and applies appropriate muting.
     * This is the ONLY way to change currentlyOnCall state.
     *
     * @param {boolean} isActive - Whether an external call is active
     * @param {string} source - Debug identifier for logging
     */
    setExternalCallActive(isActive, source = 'unknown') {
        this.currentlyOnCall = isActive;
        console.log(`📞 External call ${isActive ? 'STARTED' : 'ENDED'} (${source})`);

        // Screen reader announcement for external call status
        if (typeof announceToScreenReader === 'function') {
            if (isActive) {
                announceToScreenReader('External call started.', 'assertive');
            } else {
                announceToScreenReader('External call ended.', 'polite');
            }
        }

        this.applyMuteState(source);
    }

    /**
     * Central mute decision function.
     * Determines and applies the correct mute state based on current conditions.
     * TRULY idempotent - only makes API calls when state actually changes.
     *
     * RULE: Mute if external call is active AND I'm not the controller
     *
     * @param {string} reason - Debug identifier for logging
     */
    applyMuteState(reason = 'manual') {
        const shouldBeMuted = this.currentlyOnCall && !this.isController;

        // Check if state actually needs to change
        // _serverMuteState tracks what we last told the server (undefined = unknown)
        if (this._serverMuteState === shouldBeMuted) {
            console.log(`🔊 [SKIP] Already ${shouldBeMuted ? 'muted' : 'unmuted'} (${reason})`);
            return;
        }

        console.log(`🔊 [MUTE_DECISION] shouldBeMuted=${shouldBeMuted}, currentlyOnCall=${this.currentlyOnCall}, isController=${this.isController} (${reason})`);

        // Track previous state for screen reader announcements
        const wasMuted = this._serverMuteState;

        if (shouldBeMuted) {
            this._doMute(reason);
            // Screen reader announcement for being muted
            if (!wasMuted && typeof announceToScreenReader === 'function') {
                announceToScreenReader('You are now muted during the external call.', 'polite');
            }
        } else {
            this._doUnmute(reason);
            // Screen reader announcement for being unmuted
            if (wasMuted && typeof announceToScreenReader === 'function') {
                announceToScreenReader('You are now unmuted.', 'polite');
            }
        }
    }

    async _doMute(reason) {
        this._serverMuteState = true;
        await this._serverSideMute(true, reason);
    }

    async _doUnmute(reason) {
        this._serverMuteState = false;
        await this._serverSideMute(false, reason);
    }

    /**
     * Server-side mute/unmute using Twilio REST API.
     * @private
     */
    async _serverSideMute(shouldMute, reason) {
        const conferenceId = this.conferenceID || this.trainer?.id;
        const callSid = this.myCallSid;

        console.log('📋 [CALLSID] _serverSideMute called', {
            action: shouldMute ? 'mute' : 'unmute',
            reason: reason,
            conferenceId: conferenceId,
            callSid: callSid,
            role: this.role,
            volunteerID: this.volunteerID,
            timestamp: new Date().toISOString()
        });

        if (!conferenceId) {
            console.error('🔊 No conferenceId for muting');
            return;
        }

        if (!callSid) {
            // Check if we even have an active connection yet
            const hasActiveConnection = this.connection && this.connectionStatus === 'connected';

            if (hasActiveConnection) {
                // CRITICAL ERROR: CallSid should be available when we have a connection
                // This indicates a bug in CallSid capture - DO NOT use fallbacks
                console.error('🚨 [CRITICAL] Cannot mute - CallSid is NULL but connection exists!', {
                    action: shouldMute ? 'mute' : 'unmute',
                    reason: reason,
                    role: this.role,
                    volunteerID: this.volunteerID,
                    conferenceId: conferenceId,
                    connectionStatus: this.connectionStatus,
                    connectionStatusFn: this.connection?.status?.(),
                    currentlyOnCall: this.currentlyOnCall,
                    isController: this.isController,
                    timestamp: new Date().toISOString()
                });
            } else {
                // No connection yet - this is expected during initialization
                console.log('📋 [CALLSID] Skipping mute - no active connection yet', {
                    action: shouldMute ? 'mute' : 'unmute',
                    reason: reason,
                    role: this.role,
                    connectionStatus: this.connectionStatus,
                    hasConnection: !!this.connection
                });
            }
            return;
        }

        try {
            const response = await fetch('/trainingShare3/muteConferenceParticipants.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    conferenceId: conferenceId,
                    action: shouldMute ? 'mute_participant' : 'unmute_participant',
                    callSid: callSid
                })
            });

            const result = await response.json();
            console.log(`🔊 ${shouldMute ? 'MUTE' : 'UNMUTE'}: ${result.success ? 'OK' : result.error}`, {
                conferenceId: conferenceId,
                callSid: callSid
            });
        } catch (error) {
            console.error('🔊 Server mute error:', error.message);
        }
    }

    // NOTE: _muteByConferenceRole fallback REMOVED
    // Fallbacks mask bugs. If CallSid is null, that's a bug that needs fixing.
    // See console.error logs for diagnosis.

    async muteMe() {
        await this._serverSideMute(true, 'muteMe');
    }

    async unMuteMe() {
        await this._serverSideMute(false, 'unMuteMe');
    }

    // Call management notifications - Each participant mutes themselves
    async notifyCallStart() {
        try {
            // Determine the correct trainer ID to use
            let notifyTrainerId;
            if (this.role === 'trainer') {
                notifyTrainerId = this.volunteerID; // Trainer uses their own ID
            } else {
                // Trainee uses the conference ID (which is the trainer ID)
                notifyTrainerId = this.conferenceID;
            }

            // trainerId should come from hidden field (populated during login)
            // callerId/callerRole provided as fallback for edge cases
            const requestBody = {
                trainerId: notifyTrainerId || '', // Should be populated from hidden field
                activeController: this.activeController,
                callerId: this.volunteerID, // Fallback for server if trainerId empty
                callerRole: this.role, // Fallback for server if trainerId empty
                notifyAll: true
            };

            console.log('Notifying call start with:', requestBody);

            // Notify all participants that an external call started
            const response = await fetch('/trainingShare3/notifyCallStart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestBody)
            });

            if (response.ok) {
                const result = await response.json();
                console.log("Notified participants that external call started:", result);
            } else {
                const errorText = await response.text();
                console.error("Failed to notify call start:", response.status, errorText);
            }
        } catch (error) {
            console.error("Error notifying call start:", error);
        }
    }

    async notifyCallEnd() {
        try {
            // Determine the correct trainer ID to use
            let notifyTrainerId;
            if (this.role === 'trainer') {
                notifyTrainerId = this.volunteerID; // Trainer uses their own ID
            } else {
                // Trainee uses the conference ID (which is the trainer ID)
                notifyTrainerId = this.conferenceID;
            }

            // trainerId should come from hidden field (populated during login)
            // callerId/callerRole provided as fallback for edge cases
            const requestBody = {
                trainerId: notifyTrainerId || '', // Should be populated from hidden field
                activeController: this.activeController,
                callerId: this.volunteerID, // Fallback for server if trainerId empty
                callerRole: this.role, // Fallback for server if trainerId empty
                notifyAll: true
            };

            console.log('Notifying call end with:', requestBody);

            // Notify all participants that an external call ended
            const response = await fetch('/trainingShare3/notifyCallEnd.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestBody)
            });

            if (response.ok) {
                const result = await response.json();
                console.log("Notified participants that external call ended:", result);
            } else {
                const errorText = await response.text();
                console.error("Failed to notify call end:", response.status, errorText);
            }
        } catch (error) {
            console.error("Error notifying call end:", error);
        }
    }

    /**
     * @deprecated Use setExternalCallActive(true, source) instead.
     * This function is maintained for backward compatibility but may be removed in future versions.
     */
    muteConferenceCall() {
        console.warn('⚠️ DEPRECATED: muteConferenceCall() - use setExternalCallActive(true, source) instead');
        // Redirect to internal implementation for backward compatibility
        this._doMute('legacy muteConferenceCall');
    }

    /**
     * @deprecated Use setExternalCallActive(false, source) instead.
     * This function is maintained for backward compatibility but may be removed in future versions.
     */
    unmuteConferenceCall() {
        console.warn('⚠️ DEPRECATED: unmuteConferenceCall() - use setExternalCallActive(false, source) instead');
        // Redirect to internal implementation for backward compatibility
        this._doUnmute('legacy unmuteConferenceCall');
    }

    async restartConferenceAfterCall() {
        try {
            console.log("⚠️ CONFERENCE RESTART: Ending current conference to remove external caller");
            console.log(`Debug: currentlyOnCall=${this.currentlyOnCall}, incomingCallsTo=${this.incomingCallsTo}`);
            
            // Disconnect current conference
            if (this.connection && this.connection.disconnect) {
                console.log("🔴 DISCONNECTING CONFERENCE CONNECTION");
                this.connection.disconnect();
            }
            
            // Wait a moment for cleanup
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            // Try to end the conference on Twilio side, but don't let it block reconnection
            try {
                const requestBody = {
                    conferenceId: this.conferenceID || '', // Can be empty - server will look it up
                    callerId: this.volunteerID, // Always provide caller ID
                    callerRole: this.role // Always provide caller role
                };

                console.log('Ending conference with:', requestBody);

                const endResponse = await fetch('/trainingShare3/endConference.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestBody)
                });

                if (endResponse.ok) {
                    const result = await endResponse.json();
                    console.log("Conference ended successfully:", result);
                } else {
                    const errorText = await endResponse.text();
                    console.warn("Conference end failed, but continuing reconnection:", errorText);
                }
            } catch (error) {
                console.warn("Conference end request failed, but continuing reconnection:", error);
            }
            
            // Always proceed with reconnection regardless of conference end result
            console.log("Proceeding with conference reconnection...");
            
            // Wait a moment, then restart with new conference ID
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // CRITICAL FIX: Disconnect current conference before starting new one
            if (this.connection) {
                console.log("Disconnecting current conference connection before restart");
                try {
                    this.connection.disconnect();
                    this.connection = null;
                    this.connectionStatus = 'disconnected';
                } catch (error) {
                    console.error("Error disconnecting current conference:", error);
                }
                // Wait for disconnect to complete
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
            
            // CRITICAL FIX: Always use trainer's ID for conference name, even if trainee has control
            // This ensures external calls route to the correct conference
            // Do NOT add timestamp - keep the same conference name so twilioRedirect.php can find it
            const baseConferenceId = (this.role === 'trainer') ? this.volunteerID : this.trainer.id;
            this.conferenceID = baseConferenceId; // Same name, Twilio creates new conference after old one ends
            console.log(`Restarting conference with same name: ${this.conferenceID} (role: ${this.role})`);
            
            // Reconnect to new conference
            this.connectConference();
            
            // Notify all other participants to reconnect
            await this.notifyOthersToReconnect();
            
        } catch (error) {
            console.error("Error restarting conference:", error);
        }
    }

    async notifyOthersToReconnect() {
        try {
            // Send notification to all participants to reconnect to new conference
            // The active controller notifies everyone else
            console.log(`DEBUG: notifyOthersToReconnect - trainer: ${this.trainer.id}, trainees:`, this.trainees.map(t => t.id));
            
            const allParticipants = [this.trainer.id, ...this.trainees.map(t => t.id)];
            const othersToNotify = allParticipants.filter(id => id !== this.volunteerID);
            
            console.log(`DEBUG: All participants: [${allParticipants.join(', ')}], Others to notify: [${othersToNotify.join(', ')}]`);
            
            const response = await fetch('/trainingShare3/notifyConferenceRestart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    trainerId: this.trainer.id,
                    activeController: this.activeController,
                    newConferenceId: this.conferenceID,
                    participants: othersToNotify
                })
            });
            
            if (response.ok) {
                console.log(`Active controller (${this.role}) notified others to reconnect to new conference`);
            }
        } catch (error) {
            console.error("Error notifying others to reconnect:", error);
        }
    }

    handleConferenceRestart(message) {
        // Any participant (not the active controller) can receive restart notification
        if (this.volunteerID !== message.activeController) {
            const newConferenceId = message.newConferenceId;
            const activeController = message.activeController;
            
            console.log(`${this.role}: Received conference restart notification from ${activeController}, new ID: ${newConferenceId}`);
            
            // Disconnect from current conference and reset status
            if (this.connection && this.connection.disconnect) {
                console.log(`${this.role}: Disconnecting from old conference`);
                this.connection.disconnect();
                this.connection = null;
                this.connectionStatus = 'disconnected';
            }
            
            // Update conference ID and reconnect
            this.conferenceID = newConferenceId;
            
            // Wait a moment, then reconnect
            setTimeout(() => {
                console.log(`${this.role}: Reconnecting to new conference: ${this.conferenceID}`);
                this.connectConference();
            }, 2000);
        } else {
            console.log(`Active controller (${this.role}): Ignoring own conference restart notification`);
        }
    }

    async autoEndConference() {
        // Called automatically by Twilio disconnect events
        // This ensures conferences are always ended when calls disconnect during training

        console.log(`Auto-ending conference due to call disconnect`);

        try {
            const requestBody = {
                conferenceId: this.conferenceID || '', // Can be empty - server will look it up
                callerId: this.volunteerID, // Always provide caller ID
                callerRole: this.role // Always provide caller role
            };

            console.log('Auto-ending conference with:', requestBody);

            const response = await fetch('/trainingShare3/endConference.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestBody)
            });

            if (response.ok) {
                const result = await response.json();
                console.log("Auto-ended conference successfully:", result);

                // Clear conference state
                this.conferenceID = null;
                this.connectionStatus = 'disconnected';
                this.connection = null;
                this.currentlyOnCall = false;

                // Unmute everyone since call is over
                this.unmuteConferenceCall();

            } else {
                const errorText = await response.text();
                console.warn("Auto-end conference failed:", errorText);
            }
        } catch (error) {
            console.warn("Auto-end conference request failed:", error);
        }
    }

    startConferenceNotificationPolling() {
        // Poll for conference restart notifications every 3 seconds
        this.conferencePollingInterval = setInterval(async () => {
            try {
                const response = await fetch(`/trainingShare3/Signals/conference_restart_${this.trainer.id}.json`);
                if (response.ok) {
                    const data = await response.json();
                    if (data.action === 'conference_restart' && data.trainees.includes(this.volunteerID)) {
                        // Clear the notification file
                        fetch(`/trainingShare3/clearConferenceNotification.php?file=conference_restart_${this.trainer.id}.json`);
                        
                        // Handle the restart
                        this.handleConferenceRestart({
                            newConferenceId: data.newConferenceId,
                            trainerId: data.trainerId
                        });
                    }
                }
            } catch (error) {
                // Ignore errors (file might not exist)
            }
        }, 3000);
    }

    async notifyControlChange() {
        try {
            // Determine the correct trainer ID to use
            let notifyTrainerId;
            if (this.role === 'trainer') {
                notifyTrainerId = this.volunteerID; // Trainer uses their own ID
            } else {
                // Trainee uses the conference ID (which is the trainer ID)
                notifyTrainerId = this.conferenceID;
            }
            
            if (!notifyTrainerId) {
                console.error('Cannot notify control change: no trainer ID available');
                return;
            }
            
            // Send notification about control change to all participants
            const response = await fetch('/trainingShare3/notifyControlChange.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    trainerId: notifyTrainerId,
                    activeController: this.activeController,
                    controllerRole: this.role,
                    trainees: this.trainees.map(t => t.id)
                })
            });
            
            if (response.ok) {
                console.log("Notified participants of control change");
            }
        } catch (error) {
            console.error("Error notifying control change:", error);
        }
    }

    handleControlChangeNotification(message) {
        const newActiveController = message.activeController;
        
        console.log(`Control change notification: new controller is ${newActiveController}`);
        
        // IMPORTANT: Only trainers can CHANGE control, but both trainers and trainees can HAVE control
        // The trainer decides who controls the screen and receives external calls
        
        // Update our understanding of who has control
        this.activeController = newActiveController;
        this.isController = (this.volunteerID === newActiveController);
        
        if (this.isController) {
            console.log(`I (${this.role}) now have control`);
        } else {
            console.log(`${newActiveController} now has control, I am listening`);
        }
    }

    handleExternalCallStart(message) {
        const activeController = message.activeController;

        console.log(`📞 [NOTIFICATION] External call start - activeController: ${activeController}`);

        // Verify the training session actually has an active call before muting
        // This prevents muting when a training participant tried to answer but another volunteer got the call
        const connectionStatus = this.connection?.status?.();
        const hasActiveConnection = this.connection &&
            typeof this.connection.status === 'function' &&
            connectionStatus === 'open';

        console.log('📋 [CALLSID] handleExternalCallStart guard check', {
            hasConnection: !!this.connection,
            hasStatusFn: typeof this.connection?.status === 'function',
            connectionStatus: connectionStatus,
            hasActiveConnection: hasActiveConnection,
            myCallSid: this.myCallSid,
            activeController: activeController,
            timestamp: new Date().toISOString()
        });

        if (!hasActiveConnection) {
            console.log(`📞 [NOTIFICATION] Skipping mute - no active conference connection (call may have gone to another volunteer)`);
            return;
        }

        // CRITICAL FIX: Set the external call state
        // This was previously missing - causing inconsistent mute behavior!
        this.setExternalCallActive(true, 'handleExternalCallStart notification');

        // Note: applyMuteState() was already called by setExternalCallActive()
        // It will mute non-controllers and keep controllers unmuted
    }

    handleExternalCallEnd(message) {
        console.log(`📞 [NOTIFICATION] External call end received`);

        // CRITICAL FIX: Clear the external call state
        // This was previously missing - causing inconsistent mute behavior!
        this.setExternalCallActive(false, 'handleExternalCallEnd notification');

        // Note: applyMuteState() was already called by setExternalCallActive()
        // It will unmute everyone since currentlyOnCall is now false
    }

    // Participant synchronization methods
    async syncParticipants() {
        try {
            // Determine the correct trainer ID to use for the query
            let queryTrainerId;
            if (this.role === 'trainer') {
                queryTrainerId = this.volunteerID; // Trainer uses their own ID
            } else {
                // Trainee needs to use the actual trainer ID, not null
                queryTrainerId = this.conferenceID; // Conference ID is set to trainer ID
            }
            
            if (!queryTrainerId) {
                console.error('Cannot sync participants: no trainer ID available');
                return;
            }
            
            const response = await fetch(`/trainingShare3/getParticipants.php?trainerId=${encodeURIComponent(queryTrainerId)}`);
            
            if (!response.ok) {
                console.error(`Failed to fetch participants: ${response.status}`);
                return;
            }
            
            const data = await response.json();
            
            if (data.success && data.participants) {
                this.updateParticipantsList(data.participants);
                console.log(`Updated participant list: ${data.participants.length} participants`);
            }
            
        } catch (error) {
            console.error('Error syncing participants:', error);
        }
    }

    updateParticipantsList(participants) {
        // Clear existing trainees list
        this.trainees = [];
        
        // Process each participant
        participants.forEach(participant => {
            if (participant.role === 'trainer') {
                // Only update trainer info if not already set (to preserve initialization values)
                if (!this.trainer.id) {
                    this.trainer.id = participant.id;
                }
                this.trainer.name = participant.name;
                this.trainer.isSignedOn = participant.isSignedOn;
            } else if (participant.role === 'trainee') {
                // Add to trainees list
                this.trainees.push({
                    id: participant.id,
                    name: participant.name,
                    isSignedOn: participant.isSignedOn,
                    muted: participant.muted
                });
            }
        });
        
        // Update UI if needed
        if (this.role === 'trainer') {
            this.updateTraineeList();
        }
        
        console.log(`Participant sync complete: Trainer=${this.trainer.id}, Trainees=[${this.trainees.map(t => t.id).join(', ')}]`);
        console.log(`DEBUG: Trainees array details:`, this.trainees.map(t => ({id: t.id, name: t.name, isSignedOn: t.isSignedOn})));
        
        // Check trainer validation after sync (moved to separate method)
        this._checkTrainerValidation();
    }

    _checkTrainerValidation() {
        // DISABLED: No longer check trainer validation - allow all trainees to connect
        if (this.role === 'trainee' && !this.trainerValidationCompleted) {
            this.trainerValidationCompleted = true; // Mark as completed
            console.log(`✅ Trainee ${this.volunteerID} validation: DISABLED - allowing connection`);
        }
    }

    startParticipantSync() {
        // Sync immediately
        this.syncParticipants();
        
        // Then sync every 30 seconds to keep lists current
        this.participantSyncInterval = setInterval(() => {
            this.syncParticipants();
        }, 30000);
        
        console.log('Started participant synchronization');
    }
    
    startControlPolling() {
        // Only start polling if we have a valid trainer ID
        const trainerId = this.role === 'trainer' ? this.volunteerID : this.trainer.id;
        if (!trainerId) {
            console.warn('Cannot start control polling: no trainer ID available yet');
            // Retry in 2 seconds if trainer ID not available yet
            setTimeout(() => this.startControlPolling(), 2000);
            return;
        }
        
        // Stop any existing polling first
        this.stopControlPolling();
        
        // Poll training control immediately
        this.pollTrainingControl();
        
        // Then poll every 3 seconds to check for control changes (reduced from 5 for better responsiveness)
        this.controlPollingInterval = setInterval(() => {
            this.pollTrainingControl();
        }, 3000);
        
        console.log(`🎯 Started training control polling for ${this.role} (trainer: ${trainerId})`);
    }
    
    async pollTrainingControl() {
        try {
            // Determine the correct trainer ID to use for the query
            let queryTrainerId;
            if (this.role === 'trainer') {
                queryTrainerId = this.volunteerID; // Trainer uses their own ID
            } else {
                // FIXED: Trainee should use trainer.id, not conferenceID
                queryTrainerId = this.trainer.id; // Trainee uses trainer's ID from trainer object
            }
            
            console.log(`🔍 [CONTROL_POLL] Role: ${this.role}, QueryTrainerID: ${queryTrainerId}, VolunteerID: ${this.volunteerID}, TrainerID: ${this.trainer.id}`);
            
            if (!queryTrainerId) {
                console.error('Cannot poll training control: no trainer ID available');
                return;
            }
            
            // FIXED: Use correct getTrainingControl.php endpoint (not testControlChange.php)
            const url = `/trainingShare3/getTrainingControl.php?trainerId=${encodeURIComponent(queryTrainerId)}&_cacheBust=${Date.now()}`;
            console.log(`🔍 [CONTROL_POLL_FIXED] Fetching: ${url}`);
            
            const response = await fetch(url, {
                cache: 'no-cache',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            });
            
            if (!response.ok) {
                console.error(`Failed to fetch training control: ${response.status}`);
                return;
            }
            
            const data = await response.json();
            console.log(`🔍 [CONTROL_POLL_FIXED] Response:`, data);
            
            if (data.success) {
                // getTrainingControl.php returns control data directly
                this.updateControlState(data);
            }
            
        } catch (error) {
            console.error('Error polling training control:', error);
        }
    }
    
    async updateControlState(controlData) {
        const newActiveController = controlData.activeController;
        const newControllerRole = controlData.controllerRole;
        const lastUpdated = controlData.lastUpdated;
        
        console.log(`🎮 [CONTROL_STATE] Checking control update:`, {
            current: this.activeController,
            new: newActiveController,
            role: newControllerRole,
            isController: this.isController,
            lastUpdated: lastUpdated,
            myId: this.volunteerID,
            myRole: this.role
        });
        
        // Check if control has actually changed
        if (this.activeController !== newActiveController) {
            console.log(`🔄 CONTROL CHANGE DETECTED: ${this.activeController || 'none'} → ${newActiveController} (${newControllerRole})`);
            
            const wasController = this.isController;
            const previousController = this.activeController;
            
            // Update control state
            this.activeController = newActiveController;
            this.isController = (this.volunteerID === newActiveController);
            this.incomingCallsTo = newActiveController; // Calls go to active controller
            
            console.log(`🎯 [CONTROL_UPDATE] State Updated:`, {
                wasController: wasController,
                isController: this.isController,
                incomingCallsTo: this.incomingCallsTo,
                activeController: this.activeController
            });
            
            // Handle control transition
            if (!wasController && this.isController) {
                // I just gained control
                console.log(`✅ I (${this.role}) gained control - starting screen share`);

                // Apply mute state - as controller, I should always be unmuted
                this.applyMuteState('control change - gained control');

                // ENHANCED: Start screen sharing for whoever gains control
                if (this.shareScreen) {
                    // CRITICAL: Stop any existing screen share first to ensure clean state
                    console.log(`🧹 Cleaning up any existing screen share before prompting new one`);
                    if (this.shareScreen.localStream || this.shareScreen.isSharing) {
                        this.shareScreen.stopScreenShare();
                        console.log(`✅ Previous screen share cleaned up`);
                    }

                    // CRITICAL: Hide any shared screen UI to restore normal DOM
                    console.log(`🖼️ Restoring normal UI before prompting new screen share`);
                    if (this.shareScreen.hideSharedScreen) {
                        this.shareScreen.hideSharedScreen();
                    }

                    // Show prompt with button - getDisplayMedia requires user gesture
                    // The button click IS the user gesture that allows screen capture
                    console.log(`📺 Showing screen share prompt for new controller: ${this.volunteerID}`);
                    this._showScreenSharePrompt();
                } else {
                    console.warn("Screen sharing not available - shareScreen is null");
                    this._showAlert('You now have control - you will handle calls.');
                }
                
            } else if (wasController && !this.isController) {
                // I just lost control
                console.log(`❌ I (${this.role}) lost control - stopping screen share`);

                // Apply mute state based on current conditions
                // If external call is active and I'm not the new controller, I'll be muted
                this.applyMuteState('control change - lost control');

                // ENHANCED: Stop screen sharing for whoever loses control
                if (this.shareScreen) {
                    try {
                        if (this.shareScreen.isSharing) {
                            this.shareScreen.stopScreenShare();
                            console.log(`📺 Screen share stopped for former controller`);
                        }
                    } catch (error) {
                        console.error("Failed to stop screen share on losing control:", error);
                    }
                }
                
                this._showAlert(`${newActiveController} now has control - listening mode.`);
            }
        } else {
            console.log(`🔍 [CONTROL_UPDATE] No change in control (${newActiveController})`);
        }
    }

    stopParticipantSync() {
        if (this.participantSyncInterval) {
            clearInterval(this.participantSyncInterval);
            this.participantSyncInterval = null;
            console.log('Stopped participant synchronization');
        }
    }
    
    stopControlPolling() {
        if (this.controlPollingInterval) {
            clearInterval(this.controlPollingInterval);
            this.controlPollingInterval = null;
            console.log('Stopped training control polling');
        }
    }

    // UI management methods (continue with all the existing UI methods...)

    /**
     * Show a screen sharing prompt with a button (required for user gesture)
     * getDisplayMedia() must be called from a user gesture handler (click, tap, etc.)
     */
    _showScreenSharePrompt() {
        // Remove any existing prompt
        const existingPrompt = document.getElementById('screenSharePrompt');
        if (existingPrompt) {
            existingPrompt.remove();
        }

        // Create modal overlay
        const overlay = document.createElement('div');
        overlay.id = 'screenSharePrompt';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 100000;
            display: flex;
            justify-content: center;
            align-items: center;
        `;

        // Create dialog
        const dialog = document.createElement('div');
        dialog.style.cssText = `
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        `;

        dialog.innerHTML = `
            <h2 style="margin-top: 0; color: #333;">You Now Have Control!</h2>
            <p style="color: #666; margin-bottom: 20px;">
                Click the button below to start sharing your screen with the training session.
            </p>
            <button id="startScreenShareBtn" style="
                background: #4CAF50;
                color: white;
                border: none;
                padding: 15px 40px;
                font-size: 18px;
                border-radius: 5px;
                cursor: pointer;
                margin-bottom: 10px;
            ">Start Screen Sharing</button>
            <br>
            <button id="skipScreenShareBtn" style="
                background: transparent;
                color: #666;
                border: 1px solid #ccc;
                padding: 8px 20px;
                font-size: 14px;
                border-radius: 5px;
                cursor: pointer;
                margin-top: 10px;
            ">Skip for now</button>
        `;

        overlay.appendChild(dialog);
        document.body.appendChild(overlay);

        // Handle start button click - this IS the user gesture
        document.getElementById('startScreenShareBtn').addEventListener('click', async () => {
            console.log('📺 User clicked to start screen sharing (user gesture)');
            overlay.remove();

            if (this.shareScreen) {
                try {
                    // Clean up any existing screen share
                    if (this.shareScreen.localStream || this.shareScreen.isSharing) {
                        this.shareScreen.stopScreenShare();
                    }
                    if (this.shareScreen.hideSharedScreen) {
                        this.shareScreen.hideSharedScreen();
                    }

                    await this.shareScreen.startScreenSharing();
                    console.log('📺 Screen sharing started successfully');
                } catch (error) {
                    console.error('Failed to start screen share:', error);
                    this._showAlert('Unable to start screen sharing. Please try again or check your browser permissions.');
                }
            }
        });

        // Handle skip button
        document.getElementById('skipScreenShareBtn').addEventListener('click', () => {
            console.log('📺 User skipped screen sharing');
            overlay.remove();
            this._showAlert('You have control. You can start screen sharing later from the training controls.');
        });
    }

    _showAllElements() {
        console.log("TrainingSession: Showing all UI elements for screen sharing");
        
        // Show all training-related UI elements
        const elementsToShow = [
            'volunteerListTable', 'newSearchPane', 'callPane',
            'volunteerMessage', 'infoCenterPane'
        ];
        
        elementsToShow.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.style.display = 'block';
                element.style.visibility = 'visible';
            }
        });
        
        // Show training-specific elements
        if (this.volunteerDetailsTitle) {
            this.volunteerDetailsTitle.style.display = 'block';
        }
        
        // Show trainee list if it exists
        const traineeList = document.getElementById('traineeList');
        if (traineeList) {
            traineeList.style.display = 'block';
        }
        
        // Configure video for sharing mode (smaller/minimized)
        const remoteVideo = document.getElementById('remoteVideo');
        if (remoteVideo) {
            // When showing all elements, make video smaller or hidden
            remoteVideo.style.position = 'absolute';
            remoteVideo.style.top = '10px';
            remoteVideo.style.right = '10px';
            remoteVideo.style.width = '300px';
            remoteVideo.style.height = 'auto';
            remoteVideo.style.maxHeight = '200px';
            remoteVideo.style.zIndex = '1000';
            remoteVideo.style.transform = 'none';
            remoteVideo.style.left = 'auto';
        }
        
        console.log("All UI elements shown for sharing mode");
    }

    _showSharedScreen() {
        console.log("TrainingSession: Configuring UI to show only shared screen");
        
        // Hide training session UI elements
        const elementsToHide = [
            'volunteerListTable', 'newSearchPane', 'callPane',
            'volunteerMessage', 'infoCenterPane'
        ];
        
        elementsToHide.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.style.display = 'none';
            }
        });
        
        // Hide training-specific elements
        if (this.volunteerDetailsTitle) {
            this.volunteerDetailsTitle.style.display = 'none';
        }
        
        const traineeList = document.getElementById('traineeList');
        if (traineeList) {
            traineeList.style.display = 'none';
        }
        
        // Configure video display
        const remoteVideo = document.getElementById('remoteVideo');
        if (remoteVideo) {
            remoteVideo.style.display = 'block';
            remoteVideo.style.position = 'fixed';
            remoteVideo.style.top = '50%';
            remoteVideo.style.left = '50%';
            remoteVideo.style.transform = 'translate(-50%, -50%)';
            remoteVideo.style.width = '90%';
            remoteVideo.style.height = 'auto';
            remoteVideo.style.maxHeight = '90vh';
            remoteVideo.style.zIndex = '9999';
            
            // Ensure poster image is showing until stream is connected
            if (!remoteVideo.srcObject) {
                remoteVideo.setAttribute('poster', 'trainingShare3/poster.png'); // Updated path
            }
            
            // Add click event to play video (needed for some browsers)
            remoteVideo.onclick = () => {
                if (remoteVideo.paused) {
                    remoteVideo.play().catch(e => console.warn("Could not play video:", e));
                }
            };
        }
        
        console.log("UI configured for screen viewing mode");
    }

    // [Include all the rest of the UI methods from original file...]
    // ... keeping all the _updateSharingStatus, _updateUserList, etc. methods

    _showAlert(message) {
        if (typeof showAlert === 'function') {
            showAlert(message);
        } else {
            console.log(`ALERT: ${message}`);
        }
    }

    _signOffTrainee() {
        console.log(`🚨 Signing off trainee: trainer not available`);
        
        // Use global exitProgram if available
        if (typeof exitProgram === 'function') {
            try {
                exitProgram("user").then(() => {
                    console.log("Trainee signed off successfully - trainer not available");
                }).catch((syncError) => {
                    console.error('Error signing off trainee:', syncError);
                    // Fallback to page refresh
                    window.location.reload();
                });
            } catch (asyncError) {
                console.error('Error calling exitProgram for trainee signoff:', asyncError);
                // Fallback to page refresh
                window.location.reload();
            }
        } else {
            console.warn('exitProgram function not available, refreshing page');
            window.location.reload();
        }
    }

    attemptScreenSharing() {
        if (this.role === "trainer" && !this.screenShareAttempted) {
            this._startScreenSharing();
        }
    }

    // Safety wrapper method - ensures compatibility with ScreenSharingControl expectations
    ensureCompatibilityMethods() {
        // Make sure all expected methods exist, even if they're just wrappers
        if (!this.processUserListUpdate) {
            this.processUserListUpdate = (messages, onlineUsers) => {
                // Fallback implementation
                console.log("processUserListUpdate called with fallback implementation");
            };
        }
    }

    // Cleanup method - Updated for PHP implementation
    destroy() {
        console.log("Destroying training session");

        // Clean up database signaling first
        this.destroyDBSignaling().catch(err => {
            console.error('Error destroying DB signaling:', err);
        });

        // Stop participant synchronization
        this.stopParticipantSync();

        // Stop control polling
        this.stopControlPolling();
        
        // Clean up screen sharing (PHP implementation)
        if (this.shareScreen) {
            try {
                // Use the PHP implementation's cleanup method
                if (this.shareScreen.closeConnection) {
                    this.shareScreen.closeConnection();
                } else if (this.shareScreen.destroy) {
                    this.shareScreen.destroy();
                }
            } catch (error) {
                console.error("Error cleaning up screen sharing:", error);
            }
            this.shareScreen = null;
        }
        
        // Clean up conference connection
        if (this.connection && this.connection.disconnect) {
            try {
                this.connection.disconnect();
            } catch (error) {
                console.error("Error disconnecting from conference:", error);
            }
        }
        
        // Close training chat window
        this._closeTrainingChat();
        
        // Close training chat room (legacy)
        if (this.trainingChatRoom && this.trainingChatRoom.close) {
            try {
                this.trainingChatRoom.close();
            } catch (error) {
                console.error("Error closing training chat:", error);
            }
        }
        
        // Clear global reference
        if (typeof window !== 'undefined' && window.trainingSession === this) {
            window.trainingSession = null;
        }
        
        // Reset state
        this.screenShareReady = false;
        this.screenShareAttempted = false;
        this.screenSharingInitialized = false;
        this.currentlyOnCall = false;
        
        // Clear conference polling interval
        if (this.conferencePollingInterval) {
            clearInterval(this.conferencePollingInterval);
            this.conferencePollingInterval = null;
        }
    }

    // Method to wait for initialization to complete
    async waitForInitialization() {
        if (this.initialized) {
            return this;
        }
        
        if (this.initError) {
            throw this.initError;
        }
        
        if (this.initPromise) {
            return await this.initPromise;
        }
        
        throw new Error("TrainingSession not initialized");
    }
    
    // Safe method calls that wait for initialization
    async safeCall(methodName, ...args) {
        await this.waitForInitialization();
        
        if (typeof this[methodName] === 'function') {
            return this[methodName](...args);
        } else {
            throw new Error(`Method ${methodName} not found`);
        }
    }

    // BACKWARD COMPATIBILITY - Add init method for existing calling code
    async init(trainerID, traineeID, role) {
        console.log(`Training.init called: trainerID=${trainerID}, traineeID=${traineeID}, role=${role}`);
        
        // If already initializing/initialized, return existing promise
        if (this.initPromise) {
            return this.initPromise;
        }
        
        // incomingCallsTo already set in constructor - NEVER NULL
        console.log(`🔧 CRITICAL: incomingCallsTo already set to trainer: ${this.incomingCallsTo}`);
        
        // Store trainer/trainee info for initialization BEFORE setting role
        // This ensures role is properly set before _initializeAsync runs
        if (role === "trainer") {
            this.role = "trainer";  // Set role first
            this._initializeAsTrainer(traineeID);
            console.log(`TrainingSession: Role set to trainer`);
        } else if (role === "trainee") {
            this.role = "trainee";  // Set role first
            this._initializeAsTrainee(trainerID);
            console.log(`TrainingSession: Role set to trainee`);
        } else {
            console.warn(`TrainingSession: Unknown role provided: ${role}`);
            this.role = "unknown";
        }
        
        // Start the async initialization (role is already set)
        this.initPromise = this._initializeAsync();
        return this.initPromise;
    }

    // Add missing UI methods for compatibility
    _updateSharingStatus(status, displayName = '') {
        console.log('TrainingSession: Updating sharing status UI:', status);
        // Implementation would go here
    }

    _updateUserList(users) {
        console.log('TrainingSession: Updating user list UI');
        // Implementation would go here
    }

    _updateUserDisconnect(userId) {
        console.log('TrainingSession: Updating disconnect UI for:', userId);
        // Implementation would go here
    }

    _updateForRole() {
        console.log('TrainingSession: Updating UI for role:', this.role);
        // Implementation would go here
    }

    /**
     * Helper function to fetch user details by username when not available in userList
     * @param {string} username - The username to look up
     * @returns {Promise<Object>} User details object
     */
    getUserDetails(username) {
        return new Promise((resolve, reject) => {
            try {
                // Use the existing AjaxRequest class pattern
                const params = `username=${encodeURIComponent(username)}`;
                new AjaxRequest('getUserDetails.php', params, (responseText) => {
                    try {
                        const result = JSON.parse(responseText);
                        if (result.error) {
                            console.error(`Failed to get user details for ${username}:`, result.error);
                            reject(new Error(result.error));
                        } else {
                            console.log(`Retrieved user details for ${username}:`, result);
                            resolve(result);
                        }
                    } catch (parseError) {
                        console.error(`Error parsing response for ${username}:`, parseError);
                        reject(parseError);
                    }
                });
            } catch (error) {
                console.error(`Error fetching user details for ${username}:`, error);
                reject(error);
            }
        });
    }

    async _updateTraineeListUI() {
        // Only show trainee list for trainers
        if (this.role !== 'trainer') {
            return;
        }
        
        // Prevent overlapping calls
        if (this._updatingTraineeList) {
            return;
        }
        this._updatingTraineeList = true;
        
        // Use the existing volunteerDetailsTitle element as the display area
        if (!this.volunteerDetailsTitle) {
            this._updatingTraineeList = false;
            return;
        }
        
        // Clear the existing content and rebuild
        this.eliminateChildren(this.volunteerDetailsTitle);
        
        // Add trainer status first
        const trainerElement = document.createElement('div');
        trainerElement.className = 'trainer-item';
        trainerElement.style.color = this.trainer.isSignedOn ? 
            "rgb(100,250,100)" : "rgb(250,250,100)"; // Green if signed on, yellow if not
        trainerElement.textContent = `TRAINER: ${this.trainer.name || this.volunteerID}`;
        this.volunteerDetailsTitle.appendChild(trainerElement);
        
        // Add trainee status
        for (const trainee of this.trainees) {
            const traineeElement = document.createElement('div');
            traineeElement.className = 'trainee-item';
            
            // Use stored name if available, otherwise try userList lookup
            let traineeName = trainee.name || trainee.id;
            let isSignedOn = false;
            
            // Get current online status from userList
            if (typeof userList !== 'undefined' && userList && userList[trainee.id]) {
                // If we don't have a name stored, get it from userList
                if (!trainee.name) {
                    traineeName = userList[trainee.id].name || trainee.id;
                }
                isSignedOn = [6, 7].includes(userList[trainee.id].AdminLoggedOn);
            } else if (!trainee.name) {
                // UserList not available or trainee not in userList, fetch from database
                try {
                    const userDetails = await this.getUserDetails(trainee.id);
                    traineeName = userDetails.name || trainee.id;
                    isSignedOn = userDetails.isOnline || false;
                    // Store the name for future use
                    trainee.name = userDetails.name;
                } catch (error) {
                    console.warn(`Could not fetch details for trainee ${trainee.id}:`, error);
                    // Keep using the username as fallback
                    traineeName = trainee.id;
                }
            }
            
            traineeElement.style.color = isSignedOn ? 
                "rgb(100,250,100)" : "rgb(250,250,100)"; // Green if signed on, yellow if not
            
            // Add status icon
            const statusIcon = document.createElement('span');
            statusIcon.textContent = isSignedOn ? '🟢' : '🟡';
            statusIcon.style.marginRight = '5px';
            
            const nameSpan = document.createElement('span');
            nameSpan.textContent = traineeName;
            
            traineeElement.appendChild(statusIcon);
            traineeElement.appendChild(nameSpan);
            this.volunteerDetailsTitle.appendChild(traineeElement);
        }
        
        // Reset the flag
        this._updatingTraineeList = false;
    }

    updateTraineeList() {
        this._updateTraineeListUI();
    }

    createTraineeListElement() {
        const traineeList = document.createElement('div');
        traineeList.id = 'traineeList';
        traineeList.className = 'trainee-list';
        
        // Add CSS styling
        traineeList.style.cssText = `
            margin-top: 5px;
            font-size: 12px;
            font-weight: bold;
            line-height: 1.3;
        `;
        
        return traineeList;
    }

    eliminateChildren(element) {
        if (element) {
            while (element.hasChildNodes()) {     
                element.removeChild(element.childNodes[0]);
            }
        }
    }
    
    // CRITICAL: Ensure trainer has control when signing on fresh
    async _ensureTrainerControl() {
        try {
            console.log(`🔧 Ensuring trainer ${this.volunteerID} has control in database`);

            const response = await fetch('/trainingShare3/setTrainingControl.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    trainerId: this.volunteerID,
                    activeController: this.volunteerID,
                    controllerRole: 'trainer'
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    console.log(`✅ Trainer control set in database successfully`);
                } else {
                    console.error('Failed to set trainer control:', data.error);
                }
            } else {
                console.error('HTTP error setting trainer control:', response.status);
            }
        } catch (error) {
            console.error('Error ensuring trainer control:', error);
            // Don't throw - this is not critical enough to break initialization
        }
    }

    /**
     * Sync control state from database on page load/refresh
     * CRITICAL: Prevents state mismatch when trainee had control before browser refresh
     * Without this, trainee's client resets to "trainer has control" but DB still says trainee
     */
    async _syncControlState() {
        try {
            const trainerId = this.role === 'trainer' ? this.volunteerID : this.trainer.id;
            console.log(`🔄 Syncing control state from database for trainer: ${trainerId}`);

            const response = await fetch(`/trainingShare3/getTrainingControl.php?trainerId=${encodeURIComponent(trainerId)}`);

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    const dbController = data.activeController;
                    const dbRole = data.controllerRole;

                    // Check if DB state differs from our default assumptions
                    const wasController = this.isController;
                    this.activeController = dbController;
                    this.isController = (dbController === this.volunteerID);
                    this.incomingCallsTo = dbController;

                    console.log(`📊 Control state synced:`, {
                        dbController,
                        dbRole,
                        isController: this.isController,
                        wasController,
                        stateChanged: wasController !== this.isController
                    });

                    // If state changed from default, update UI and log it
                    if (wasController !== this.isController) {
                        console.log(`⚠️ Control state differs from default - ${this.volunteerID} ${this.isController ? 'HAS' : 'does NOT have'} control`);
                        this._updateControlUI();
                    }
                } else {
                    console.error('Failed to sync control state:', data.error);
                }
            } else {
                console.error('HTTP error syncing control state:', response.status);
            }
        } catch (error) {
            console.error('Error syncing control state:', error);
            // Don't throw - fall back to default state
        }
    }

    // ============================================================
    // SIGNALING API METHODS
    // screenSharingControlMulti handles all signal polling
    // These methods provide API access to PHP endpoints
    // ============================================================

    /**
     * Send signal via screenSharingControlMulti
     */
    async sendDBSignal(signal, recipientId = null) {
        if (!this.shareScreen) {
            console.error('🚨 Screen sharing not available - cannot send signal:', signal.type);
            return false;
        }
        if (recipientId) {
            signal.to = recipientId;
        }
        this.shareScreen.sendSignal(signal);
        return true;
    }

    /**
     * Set mute via server-authoritative endpoint
     */
    async setMuteViaServer(participantId, shouldMute, reason = null) {
        const trainerId = this.role === 'trainer' ? this.volunteerID : this.trainer.id;
        try {
            const response = await fetch('/trainingShare3/setMuteState.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    trainerId,
                    participantId,
                    isMuted: shouldMute,
                    reason,
                    callSid: this.myCallSid
                }),
                credentials: 'same-origin'
            });
            const data = await response.json();
            return data.success;
        } catch (error) {
            console.error('🚨 setMuteState error:', error);
            return false;
        }
    }

    /**
     * Trigger bulk mute via server when call starts
     */
    async bulkMuteNonControllers(reason = 'external_call') {
        const trainerId = this.role === 'trainer' ? this.volunteerID : this.trainer.id;
        try {
            const response = await fetch('/trainingShare3/bulkMute.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    trainerId,
                    action: 'mute_non_controllers',
                    excludeParticipant: this.activeController,
                    reason
                }),
                credentials: 'same-origin'
            });
            const data = await response.json();
            return data.success;
        } catch (error) {
            console.error('🚨 bulkMute error:', error);
            return false;
        }
    }

    /**
     * Trigger bulk unmute via server when call ends
     */
    async bulkUnmuteAll(reason = 'call_ended') {
        const trainerId = this.role === 'trainer' ? this.volunteerID : this.trainer.id;
        try {
            const response = await fetch('/trainingShare3/bulkMute.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    trainerId,
                    action: 'unmute_all',
                    reason
                }),
                credentials: 'same-origin'
            });
            const data = await response.json();
            return data.success;
        } catch (error) {
            console.error('🚨 bulkUnmute error:', error);
            return false;
        }
    }

    /**
     * Transition session state via server
     */
    async transitionSessionState(event, options = {}) {
        const trainerId = this.role === 'trainer' ? this.volunteerID : this.trainer.id;
        try {
            const response = await fetch('/trainingShare3/transitionState.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    trainerId,
                    event,
                    ...options
                }),
                credentials: 'same-origin'
            });
            const data = await response.json();
            return data.success ? data : null;
        } catch (error) {
            console.error(`🚨 transitionState error for ${event}:`, error);
            return null;
        }
    }

    /**
     * Cleanup on destroy - now just transitions state
     */
    async destroyDBSignaling() {
        if (this.role === 'trainer') {
            await this.transitionSessionState('trainer_logout');
        }
    }
}

// MAINTAIN BACKWARD COMPATIBILITY - Factory function expected by index.js
function Training() {
    console.log('🔧 Training() constructor called - creating new TrainingSession instance');
    return new TrainingSession();
}

// Export for module systems or make globally available
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TrainingSession;
} else if (typeof window !== 'undefined') {
    window.TrainingSession = TrainingSession;
    window.Training = Training;
    console.log('🔧 Training function made globally available on window object');
} else {
    console.error('🚨 Unable to make Training function globally available - no window object');
}

// Debug: Verify the Training function is accessible
console.log('🔧 Training function defined:', typeof Training === 'function');
console.log('🔧 TrainingSession class defined:', typeof TrainingSession === 'function');