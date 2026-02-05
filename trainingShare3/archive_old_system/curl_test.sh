#!/bin/bash

echo "=== Testing Complete Signal Flow via HTTP ==="
echo

# Step 1: Clear old files
rm -f Signals/participant_*.txt
echo "1. Cleared old signal files"

# Step 2: Set up authentication for TestTrainer
echo "2. Setting up TestTrainer authentication..."
TRAINER_SESSION=$(curl -s -c cookies_trainer.txt \
  "http://localhost:8000/trainingShare3/test_auth.php?userID=TestTrainer&trainer=1")
echo "   Auth response: $TRAINER_SESSION"

# Step 3: Set up authentication for TestTrainee  
echo "3. Setting up TestTrainee authentication..."
TRAINEE_SESSION=$(curl -s -c cookies_trainee.txt \
  "http://localhost:8000/trainingShare3/test_auth.php?userID=TestTrainee&trainee=1")
echo "   Auth response: $TRAINEE_SESSION"

# Step 4: TestTrainer joins room
echo "4. TestTrainer joining room..."
TRAINER_JOIN=$(curl -s -b cookies_trainer.txt -X POST \
  -d '{"type":"join-room"}' \
  "http://localhost:8000/trainingShare3/signalingServerMulti.php?trainingShareRoom=TestTrainer")
echo "   Join response: $TRAINER_JOIN"

# Step 5: TestTrainee joins room
echo "5. TestTrainee joining room..."
TRAINEE_JOIN=$(curl -s -b cookies_trainee.txt -X POST \
  -d '{"type":"join-room"}' \
  "http://localhost:8000/trainingShare3/signalingServerMulti.php?trainingShareRoom=TestTrainer")
echo "   Join response: $TRAINEE_JOIN"

# Step 6: TestTrainer starts screen sharing
echo "6. TestTrainer starting screen share..."
SCREEN_SHARE=$(curl -s -b cookies_trainer.txt -X POST \
  -d '{"type":"screen-share-start"}' \
  "http://localhost:8000/trainingShare3/signalingServerMulti.php?trainingShareRoom=TestTrainer")
echo "   Screen share response: $SCREEN_SHARE"

# Step 7: Check signal files
echo "7. Checking signal files created..."
for file in Signals/participant_*.txt; do
  if [ -f "$file" ]; then
    echo "   Found: $file"
    echo "   Content: $(cat "$file")"
    echo
  fi
done

# Step 8: Check room status
echo "8. Checking room status..."
if [ -f "Signals/room_TestTrainer.json" ]; then
  echo "   Room file content:"
  cat "Signals/room_TestTrainer.json" | python3 -m json.tool 2>/dev/null || cat "Signals/room_TestTrainer.json"
else
  echo "   No room file found"
fi

# Cleanup
rm -f cookies_*.txt

echo
echo "=== Test Complete ==="