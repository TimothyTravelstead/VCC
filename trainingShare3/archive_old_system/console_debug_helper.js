// Console Debug Helper for Training WebRTC Integration
// Add this to browser console to test the integration

window.trainingDebug = {
    checkStatus: function() {
        console.log("=== Training WebRTC Integration Status ===");
        
        // Check if scripts are loaded
        if (typeof window.simpleTrainingScreenShare !== 'undefined') {
            console.log("✓ SimpleTrainingScreenShare loaded");
        } else {
            console.log("✗ SimpleTrainingScreenShare NOT loaded");
            return;
        }
        
        if (typeof window.trainingSession !== 'undefined') {
            console.log("✓ TrainingSession available");
            console.log("  Role:", window.trainingSession.role);
            console.log("  VolunteerID:", window.trainingSession.volunteerID);
            console.log("  Trainer:", window.trainingSession.trainer);
            console.log("  Trainees:", window.trainingSession.trainees);
        } else {
            console.log("✗ TrainingSession NOT available");
        }
        
        // Check HTML elements
        const localVideo = document.getElementById('localVideo');
        const remoteVideo = document.getElementById('remoteVideo');
        console.log("Local video element:", localVideo ? "✓ Found" : "✗ Missing");
        console.log("Remote video element:", remoteVideo ? "✓ Found" : "✗ Missing");
        
        // Check simple training instance
        const simple = window.simpleTrainingScreenShare;
        if (simple) {
            console.log("Simple Training State:");
            console.log("  Role:", simple.role);
            console.log("  MyID:", simple.myId);
            console.log("  IsSharing:", simple.isSharing);
            console.log("  HasJoined:", simple.hasJoined);
            console.log("  Peer Connections:", simple.peerConnections.size);
        }
    },
    
    initTrainer: function() {
        console.log("=== Manually Initializing Trainer ===");
        if (window.simpleTrainingScreenShare) {
            window.simpleTrainingScreenShare.initializeTrainer();
        } else {
            console.log("✗ SimpleTrainingScreenShare not available");
        }
    },
    
    initTrainee: function() {
        console.log("=== Manually Initializing Trainee ===");
        if (window.simpleTrainingScreenShare) {
            window.simpleTrainingScreenShare.initializeTrainee();
        } else {
            console.log("✗ SimpleTrainingScreenShare not available");
        }
    },
    
    testSignaling: function() {
        console.log("=== Testing Signaling Server ===");
        fetch('trainingShare3/simpleTrainingSignaling.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                type: 'test-message',
                from: 'debug-console',
                to: 'test',
                timestamp: Date.now()
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log("Signaling test result:", data);
        })
        .catch(error => {
            console.error("Signaling test failed:", error);
        });
    },
    
    showTrainerScreen: function() {
        console.log("=== Manually Triggering Trainer Screen View ===");
        if (window.simpleTrainingScreenShare) {
            window.simpleTrainingScreenShare.showTrainerScreen();
        }
    },
    
    help: function() {
        console.log("=== Training Debug Helper Commands ===");
        console.log("trainingDebug.checkStatus() - Check integration status");
        console.log("trainingDebug.initTrainer() - Initialize as trainer");
        console.log("trainingDebug.initTrainee() - Initialize as trainee");
        console.log("trainingDebug.testSignaling() - Test signaling server");
        console.log("trainingDebug.showTrainerScreen() - Switch to trainer screen view");
        console.log("trainingDebug.help() - Show this help");
    }
};

// Auto-run status check
console.log("Training Debug Helper loaded. Type 'trainingDebug.help()' for commands.");
trainingDebug.checkStatus();