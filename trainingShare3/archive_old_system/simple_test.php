<?php
// Simple signaling test
echo "Starting signal test...\n\n";

// Test 1: Trainer joins
echo "=== TEST 1: Trainer joins room ===\n";
$cmd = 'curl -X POST -H "Content-Type: application/json" ' .
       '-d \'{"type":"join-room"}\' ' .
       '-b "PHPSESSID=test123" ' .
       '"http://localhost:8000/trainingShare3/test_auth.php?userID=TestTrainer&trainer=1"';
echo "Authenticating trainer...\n";
exec($cmd, $output);
echo implode("\n", $output) . "\n\n";

// Now test signaling
$cmd2 = 'curl -X POST -H "Content-Type: application/json" ' .
        '-d \'{"type":"join-room"}\' ' .
        '-b "PHPSESSID=test123" ' .
        '"http://localhost:8000/trainingShare3/signalingServerMulti.php?trainingShareRoom=TestTrainer&role=trainer"';
echo "Sending join-room signal...\n";
exec($cmd2, $output2);
echo implode("\n", $output2) . "\n\n";

echo "Checking files created:\n";
exec('ls -la Signals/', $files);
echo implode("\n", $files) . "\n";
?>