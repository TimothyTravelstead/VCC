// Debug script to check why trainee condition fails
// Paste this into browser console on trainee page

function debugTraineeCondition() {
    console.log("=== Trainee Condition Debug ===");
    
    // Check the exact values and types
    const userLoggedOn = document.getElementById("userLoggedOn") ? parseInt(document.getElementById("userLoggedOn").value) : 0;
    const isTrainer = userLoggedOn === 4;
    const isTrainee = userLoggedOn === 6;
    const isTrainingUser = isTrainer || isTrainee;
    
    console.log("User Detection:");
    console.log("  userLoggedOn (from element):", document.getElementById("userLoggedOn")?.value, "(type:", typeof document.getElementById("userLoggedOn")?.value, ")");
    console.log("  userLoggedOn (parsed):", userLoggedOn, "(type:", typeof userLoggedOn, ")");
    console.log("  isTrainer (===4):", isTrainer);
    console.log("  isTrainee (===6):", isTrainee);
    console.log("  isTrainingUser:", isTrainingUser);
    
    // Check the trainer/trainee flags
    const trainerElement = document.getElementById("trainer");
    const traineeElement = document.getElementById("assignedTraineeIDs");
    
    const trainer = trainerElement ? trainerElement.value : 0;
    const trainee = traineeElement ? traineeElement.value : 0;
    
    console.log("\nTraining Flags:");
    console.log("  trainer element value:", trainerElement?.value, "(type:", typeof trainerElement?.value, ")");
    console.log("  trainer (parsed):", trainer, "(type:", typeof trainer, ")");
    console.log("  trainee element value:", traineeElement?.value, "(type:", typeof traineeElement?.value, ")");
    console.log("  trainee (parsed):", trainee, "(type:", typeof trainee, ")");
    
    // Check the conditions
    console.log("\nCondition Checks:");
    console.log("  isTrainingUser && trainer == 1:", isTrainingUser && trainer == 1);
    console.log("  isTrainingUser && trainee == 1:", isTrainingUser && trainee == 1);
    console.log("  trainer == 1:", trainer == 1);
    console.log("  trainer === '1':", trainer === '1');
    console.log("  trainee == 1:", trainee == 1); 
    console.log("  trainee === '1':", trainee === '1');
    
    // Check additional elements that might be relevant
    console.log("\nAdditional Elements:");
    console.log("  trainerID:", document.getElementById("trainerID")?.value);
    console.log("  traineeID:", document.getElementById("traineeID")?.value);
    console.log("  volunteerID:", document.getElementById("volunteerID")?.value);
    
    // Simulate the exact logic from index.js
    console.log("\n=== Simulating index.js Logic ===");
    if (isTrainingUser && trainer == 1) {
        console.log("✅ Would initialize as TRAINER");
    } else if (isTrainingUser && trainee == 1) {
        console.log("✅ Would initialize as TRAINEE");
    } else {
        console.log("❌ Would NOT initialize training session");
        console.log("   isTrainingUser:", isTrainingUser);
        console.log("   trainer == 1:", trainer == 1);
        console.log("   trainee == 1:", trainee == 1);
    }
}

debugTraineeCondition();