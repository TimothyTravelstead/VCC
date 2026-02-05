// Debug helper to check training initialization
// Paste this into browser console on trainee page

function debugTrainingInit() {
    console.log("=== Training Initialization Debug ===");
    
    // Check if elements exist
    const elements = {
        'volunteerID': document.getElementById('volunteerID'),
        'traineeID': document.getElementById('traineeID'), 
        'trainerID': document.getElementById('trainerID'),
        'assignedTraineeIDs': document.getElementById('assignedTraineeIDs'),
        'trainer': document.getElementById('trainer'),
        'trainee': document.getElementById('trainee')
    };
    
    console.log("HTML Elements:");
    Object.keys(elements).forEach(key => {
        const elem = elements[key];
        if (elem) {
            console.log(`  ${key}: value="${elem.value}"`);
        } else {
            console.log(`  ${key}: ❌ NOT FOUND`);
        }
    });
    
    // Check global variables  
    console.log("\nGlobal Variables:");
    console.log("  trainer:", window.trainer);
    console.log("  trainee:", window.trainee);
    console.log("  currentUser:", window.currentUser);
    console.log("  isTrainingUser:", window.isTrainingUser);
    
    // Check TrainingSession
    if (window.trainingSession) {
        console.log("\nTrainingSession State:");
        console.log("  Initialized:", window.trainingSession.initialized);
        console.log("  Initializing:", window.trainingSession.initializing);
        console.log("  Role:", window.trainingSession.role);
        console.log("  VolunteerID:", window.trainingSession.volunteerID);
        console.log("  InitPromise:", window.trainingSession.initPromise);
        console.log("  InitError:", window.trainingSession.initError);
    } else {
        console.log("\n❌ No trainingSession found");
    }
    
    // Try manual initialization
    console.log("\n=== Attempting Manual Init ===");
    if (window.Training && elements.trainerID && elements.trainerID.value) {
        console.log("Attempting trainee initialization...");
        try {
            const testSession = new Training();
            console.log("TrainingSession created, calling init...");
            testSession.init(elements.trainerID.value, elements.volunteerID?.value || 'unknown', "trainee")
                .then(() => {
                    console.log("✅ Manual init succeeded");
                    console.log("Role:", testSession.role);
                    console.log("Simple screen share state:", {
                        role: window.simpleTrainingScreenShare?.role,
                        myId: window.simpleTrainingScreenShare?.myId
                    });
                })
                .catch(error => {
                    console.log("❌ Manual init failed:", error);
                });
        } catch (error) {
            console.log("❌ Error creating TrainingSession:", error);
        }
    }
}

debugTrainingInit();