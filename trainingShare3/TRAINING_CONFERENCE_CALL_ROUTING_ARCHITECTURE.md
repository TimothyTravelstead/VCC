# TRAINING CONFERENCE CALL ROUTING ARCHITECTURE

## ⚠️ CRITICAL UNDERSTANDING - READ THIS FIRST ⚠️

**THIS IS NOT HOW NORMAL TWILIO CALLS WORK!**

In the training system, **external calls are NOT answered using device.connect() or connection.accept()** on the client side. Instead, they are **ROUTED INTO AN EXISTING CONFERENCE** by Twilio's server-side TwiML routing.

### The Fundamental Concept

```
╔════════════════════════════════════════════════════════════════════╗
║  TRAINING CONFERENCE = NAMED CONFERENCE ON TWILIO                  ║
║  Conference Name = Trainer's Username                              ║
║  All participants join the SAME conference by name                 ║
╚════════════════════════════════════════════════════════════════════╝

BEFORE external call:
┌─────────────────────────────────────────┐
│  Twilio Conference: "TrainerUsername"   │
│                                         │
│  Participants:                          │
│  - TrainerUsername (moderator, unmuted) │
│  - TraineeUser1 (participant, unmuted)  │
│  - TraineeUser2 (participant, unmuted)  │
└─────────────────────────────────────────┘

AFTER external call arrives:
┌─────────────────────────────────────────┐
│  Twilio Conference: "TrainerUsername"   │
│                                         │
│  Participants:                          │
│  - TrainerUsername (moderator, muted)   │
│  - TraineeUser1 (participant, UNMUTED)  │ ← Has control
│  - TraineeUser2 (participant, muted)    │
│  - +15551234567 (EXTERNAL CALLER)       │ ← Added by Twilio!
└─────────────────────────────────────────┘
```

**Key Insight**: The external caller is **ADDED TO** the existing conference, not connected via a separate call!

---

## Complete Call Flow Documentation

### PHASE 1: Training Session Initialization

#### Step 1.1: Trainer Logs In

**File**: `loginverify2.php`

```php
// Trainer login (LoggedOn = 4)
$_SESSION['trainer'] = 1;
$_SESSION['trainee'] = 0;
$_SESSION['trainerID'] = $TrainerUsername; // Trainer's own username
```

**File**: `index2.php`

```php
// Read from session
$trainerID = $_SESSION['trainerID']; // "TrainerUsername"

// Output to HTML
echo "<input type='hidden' id='trainerID' value='TrainerUsername'>";
echo "<input type='hidden' id='assignedTraineeIDs' value='Trainee1,Trainee2'>";
```

#### Step 1.2: TrainingSession Initializes (Trainer)

**File**: `trainingSessionUpdated.js`

```javascript
// Constructor (line 14-36)
constructor() {
    this.volunteerID = document.getElementById("volunteerID").value; // "TrainerUsername"
    const trainerID = document.getElementById("trainerID").value;    // "TrainerUsername"
    this.conferenceID = null; // Will be set during init
}

// Initialize as trainer (line 221-258)
_initializeAsTrainer(traineeID) {
    this.role = "trainer";
    this.trainer.id = this.volunteerID; // "TrainerUsername"

    // CRITICAL: Conference ID = Trainer's username!
    this.conferenceID = this.volunteerID; // "TrainerUsername"

    this.isController = true;
    this.activeController = this.volunteerID;
    this.incomingCallsTo = this.volunteerID;
}
```

#### Step 1.3: Trainer Connects to Conference

**File**: `trainingSessionUpdated.js` (line 989-1087)

```javascript
connectConference() {
    const device = callMonitor.getDevice();

    // Conference parameters
    const params = {
        conference: this.conferenceID || this.trainer.id, // "TrainerUsername"
        conferenceRole: 'moderator',     // Trainer is moderator
        startConferenceOnEnter: true,    // Trainer creates conference
        endConferenceOnExit: true,       // Conference ends when trainer leaves
        muted: false                      // Trainer unmuted
    };

    // Connect to conference named "TrainerUsername"
    this.connection = device.connect(params);
}
```

**What Twilio Does**:
1. Receives request to connect to conference "TrainerUsername"
2. Conference doesn't exist yet → Creates new conference named "TrainerUsername"
3. Adds trainer as MODERATOR with startConferenceOnEnter=true
4. Trainer is now in conference "TrainerUsername" (the only participant)

---

#### Step 1.4: Trainee Logs In

**File**: `loginverify2.php` (lines 299-320)

```php
case '6': // Trainee
    $_SESSION['trainee'] = 1;
    $_SESSION['trainer'] = 0;

    // CRITICAL: Look up trainer and store in session
    $trainerLookupQuery = "SELECT UserName, firstname, lastname, TraineeID
                           FROM volunteers
                           WHERE FIND_IN_SET(?, TraineeID) > 0
                           LIMIT 1";
    $trainerLookupResult = dataQuery($trainerLookupQuery, [$UserID]); // UserID = "TraineeUser1"

    if (!empty($trainerLookupResult)) {
        $_SESSION['trainerID'] = $trainerLookupResult[0]->UserName; // "TrainerUsername"
        $_SESSION['trainerName'] = $trainerLookupResult[0]->firstname . " " . $trainerLookupResult[0]->lastname;
    }
```

**File**: `index2.php`

```php
// Read from session
$trainerID = $_SESSION['trainerID']; // "TrainerUsername"

// Output to HTML
echo "<input type='hidden' id='trainerID' value='TrainerUsername'>";
echo "<input type='hidden' id='traineeID' value='TraineeUser1'>";
```

#### Step 1.5: TrainingSession Initializes (Trainee)

**File**: `trainingSessionUpdated.js`

```javascript
// Constructor (line 14-36)
constructor() {
    this.volunteerID = document.getElementById("volunteerID").value; // "TraineeUser1"
    const trainerID = document.getElementById("trainerID").value;    // "TrainerUsername"
    this.conferenceID = null; // Will be set during init
}

// Initialize as trainee (line 260-290)
_initializeAsTrainee(trainerID) {
    this.trainer.id = trainerID; // "TrainerUsername"

    // CRITICAL: Conference ID = Trainer's username!
    this.conferenceID = trainerID; // "TrainerUsername"

    this.isController = false;
    this.activeController = trainerID; // Trainer has control initially
    this.incomingCallsTo = trainerID; // External calls go to trainer
}
```

#### Step 1.6: Trainee Connects to Conference

**File**: `trainingSessionUpdated.js` (line 989-1087)

```javascript
connectConference() {
    const device = callMonitor.getDevice();

    // Conference parameters
    const params = {
        conference: this.conferenceID || this.trainer.id, // "TrainerUsername"
        conferenceRole: 'participant',   // Trainee is participant
        startConferenceOnEnter: false,   // Don't start (trainer already did)
        endConferenceOnExit: false,      // Don't end when trainee leaves
        muted: false                      // Trainee unmuted (normal training)
    };

    // Connect to conference named "TrainerUsername"
    this.connection = device.connect(params);
}
```

**What Twilio Does**:
1. Receives request to connect to conference "TrainerUsername"
2. Conference ALREADY EXISTS (trainer created it)
3. Adds trainee as PARTICIPANT to existing conference
4. Both trainer and trainee now in conference "TrainerUsername"

---

### PHASE 2: External Call Arrives

#### Step 2.1: External Caller Dials Helpline

```
External caller: +1 (555) 123-4567
Dials: Helpline number (e.g., +1-800-XXX-XXXX)
```

**What Twilio Does**:
1. Receives incoming call from +15551234567
2. Looks up call routing rules
3. Determines which volunteer should receive call
4. Creates entry in `CallRouting` table with CallSid and Volunteer name

#### Step 2.2: Twilio Webhook Called

**Twilio calls**: `https://yoursite.com/twilioRedirect.php`

**Request Parameters**:
```
CallSid: "CA1234567890abcdef1234567890abcdef"
From: "+15551234567"
To: "+18005551234"
CallStatus: "ringing"
...
```

#### Step 2.3: twilioRedirect.php Processes Call

**File**: `twilioRedirect.php` (lines 33-89)

```php
// Get the volunteer who should answer this call
$query = "SELECT Volunteer FROM CallRouting WHERE CallSid = ?";
$result = dataQuery($query, [$CallSid]);

$Volunteer = null;

if ($result && count($result) > 0) {
    $Volunteer = $result[0]->Volunteer; // e.g., "TraineeUser1"

    // Check if volunteer is in training session
    $trainingQuery = "SELECT LoggedOn FROM volunteers WHERE UserName = ?";
    $trainingResult = dataQuery($trainingQuery, [$Volunteer]);

    if ($trainingResult && count($trainingResult) > 0) {
        $loggedOnStatus = $trainingResult[0]->LoggedOn;

        // CRITICAL: If volunteer is a trainee (LoggedOn = 6)
        if ($loggedOnStatus == 6) {
            // Find the trainer for this trainee
            $findTrainerQuery = "SELECT UserName FROM volunteers
                               WHERE FIND_IN_SET(?, TraineeID) > 0
                               AND LoggedOn = 4";
            $trainerResult = dataQuery($findTrainerQuery, [$Volunteer]);

            if ($trainerResult && count($trainerResult) > 0) {
                $trainerId = $trainerResult[0]->UserName; // "TrainerUsername"

                // CRITICAL: Replace volunteer with trainer!
                // External call routes to TRAINER'S conference
                $Volunteer = $trainerId; // Now "TrainerUsername"
            }
        }
        // If volunteer is trainer (LoggedOn = 4), $Volunteer stays as trainer
    }
}
```

**Critical Logic**:
- If answering volunteer is a **trainee** → Find their trainer → Route to **trainer's conference**
- If answering volunteer is a **trainer** → Route to **their own conference**
- Result: `$Volunteer` now contains the **conference name** (trainer's username)

#### Step 2.4: TwiML Response Generated

**File**: `twilioRedirect.php` (lines 96-102)

```php
// Generate TwiML response
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<Response>";
echo "  <Dial action='".$WebAddress."/answeredCallEnd.php' method='POST'>";
echo "    <Conference beep='onExit'
                     startConferenceOnEnter='true'
                     endConferenceOnExit='true'
                     waitUrl='".$WebAddress."/Audio/waitMusic.php'>"
         .$Volunteer.  // ← "TrainerUsername"
         "</Conference>";
echo "  </Dial>";
echo "</Response>";
```

**TwiML Sent to Twilio**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<Response>
  <Dial action="https://yoursite.com/answeredCallEnd.php" method="POST">
    <Conference beep="onExit"
                startConferenceOnEnter="true"
                endConferenceOnExit="true"
                waitUrl="https://yoursite.com/Audio/waitMusic.php">
      TrainerUsername
    </Conference>
  </Dial>
</Response>
```

**What This Tells Twilio**:
- Connect this external caller to conference named "**TrainerUsername**"
- If conference doesn't exist, create it (startConferenceOnEnter='true')
- End conference when this participant leaves (endConferenceOnExit='true')
- Play wait music while connecting

#### Step 2.5: Twilio Adds External Caller to Conference

**What Twilio Does**:
1. Receives TwiML response with conference name "TrainerUsername"
2. Looks up conference named "TrainerUsername" → **Conference already exists!**
3. **Adds external caller (+15551234567) to existing conference**
4. External caller can now hear trainer + all trainees in conference
5. Trainer + trainees can hear external caller

**Current Conference State**:
```
Conference: "TrainerUsername"
Participants:
  - TrainerUsername (moderator, connected via device.connect())
  - TraineeUser1 (participant, connected via device.connect())
  - TraineeUser2 (participant, connected via device.connect())
  - +15551234567 (participant, connected via TwiML routing) ← NEW!
```

---

### PHASE 3: Call Control and Muting

#### Step 3.1: Determining Who Has Control

**File**: `training_session_control` table (database)

```sql
CREATE TABLE training_session_control (
    trainer_id VARCHAR(255) PRIMARY KEY,
    active_controller VARCHAR(255),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

Example data:
┌──────────────────┬────────────────────┬─────────────────────┐
│ trainer_id       │ active_controller  │ last_updated        │
├──────────────────┼────────────────────┼─────────────────────┤
│ TrainerUsername  │ TraineeUser1       │ 2025-01-13 10:30:15 │
└──────────────────┴────────────────────┴─────────────────────┘
```

**Meaning**: In the training session led by "TrainerUsername", "TraineeUser1" currently has control

#### Step 3.2: Client-Side Call State

**File**: `trainingSessionUpdated.js`

**For TrainerUsername**:
```javascript
this.role = "trainer"
this.volunteerID = "TrainerUsername"
this.isController = false                // Lost control to trainee
this.activeController = "TraineeUser1"   // Trainee has control
this.incomingCallsTo = "TraineeUser1"    // External calls for trainee
this.currentlyOnCall = false             // Not the one taking call
```

**For TraineeUser1** (has control):
```javascript
this.role = "trainee"
this.volunteerID = "TraineeUser1"
this.isController = true                 // I have control!
this.activeController = "TraineeUser1"   // I am active controller
this.incomingCallsTo = "TraineeUser1"    // External calls for me
this.currentlyOnCall = false             // Will become true when call arrives
```

**For TraineeUser2** (no control):
```javascript
this.role = "trainee"
this.volunteerID = "TraineeUser2"
this.isController = false                // No control
this.activeController = "TraineeUser1"   // TraineeUser1 has control
this.incomingCallsTo = "TraineeUser1"    // External calls for TraineeUser1
this.currentlyOnCall = false             // Not my call
```

#### Step 3.3: External Call Notification Flow

**When external caller is added to conference**, the system needs to mute non-controllers.

**Client-Side Detection** (NOT via Twilio event):

The training system does NOT rely on Twilio events to detect external callers joining. Instead:

**File**: `index.js` (call monitoring system)

```javascript
// When CallRouting detects a new call assignment
// The volunteer who was assigned the call triggers:
if (trainingSession) {
    trainingSession.startNewCall();
} else {
    // Normal call handling for non-training volunteers
    device.connect(parameters);
}
```

**File**: `trainingSessionUpdated.js` (line 935-962)

```javascript
async startNewCall() {
    // Check if I'm the one receiving the external call
    if (this.volunteerID !== this.incomingCallsTo) {
        // I'm NOT the controller - MUTE MYSELF
        console.log(`${this.role}: External call directed to ${this.incomingCallsTo}, I need to MUTE myself`);
        this.currentlyOnCall = true;
        this.muteConferenceCall();

        // Enforce muting with retries
        setTimeout(() => this.muteMe(), 100);
        setTimeout(() => this.muteMe(), 500);
        setTimeout(() => this.muteMe(), 1000);
        return;
    }

    // I AM the controller - stay unmuted, notify others
    this.currentlyOnCall = true;
    console.log(`Active controller is receiving call: Staying unmuted, notifying others to mute`);
    await this.notifyCallStart();
}
```

**Notification System**:

**File**: `notifyCallStart.php`

Called by the active controller to tell other participants to mute:

```php
// Get trainer and all trainees in this training session
$trainerId = $input['trainerId'];
$activeController = $input['activeController'];

// Get all participants
$participants = [$trainerId];
$traineeQuery = "SELECT TraineeID FROM volunteers WHERE UserName = ?";
$result = dataQuery($traineeQuery, [$trainerId]);
if ($result) {
    $traineeIds = explode(',', $result[0]->TraineeID);
    foreach ($traineeIds as $traineeId) {
        $participants[] = trim($traineeId);
    }
}

// Remove active controller from list (they stay unmuted)
$participants = array_filter($participants, fn($id) => $id !== $activeController);

// Write mute notification to each participant's signal file
foreach ($participants as $participantId) {
    $notification = [
        'type' => 'external-call-start',
        'activeController' => $activeController,
        'trainerId' => $trainerId,
        'timestamp' => microtime(true)
    ];

    $signalFile = __DIR__ . '/Signals/participant_' . $participantId . '.txt';
    file_put_contents($signalFile, json_encode($notification) . "_MULTIPLEVENTS_", FILE_APPEND);
}
```

**Signal Polling** (line 238-266 in screenSharingControlMulti.js):

Each participant polls for signals every 1 second:

```javascript
async pollForSignals() {
    const response = await fetch(
        `/trainingShare3/pollSignals.php?participantId=${this.participantId}&role=${this.role}`
    );
    const data = await response.json();

    if (data.messages && data.messages.length > 0) {
        data.messages.forEach(message => {
            this.handleSignalMessage(message);
        });
    }
}
```

**Message Handling**:

```javascript
// screenSharingControlMulti.js (line 760-766)
handleExternalCallStart(message) {
    // Forward to TrainingSession
    if (window.trainingSession) {
        window.trainingSession.handleExternalCallStart(message);
    }
}
```

**TrainingSession Receives Notification**:

```javascript
// trainingSessionUpdated.js
handleExternalCallStart(message) {
    // Someone else has external call - I need to mute
    if (this.volunteerID !== message.activeController) {
        this.currentlyOnCall = true;
        this.muteConferenceCall();
    }
}
```

---

### PHASE 4: Muting Mechanics

#### Step 4.1: Conference Connection Muting

**File**: `trainingSessionUpdated.js` (line 1097-1143)

```javascript
muteMe() {
    // In training sessions, mute the CONFERENCE CONNECTION
    if (this.connection && typeof this.connection.mute === 'function') {
        try {
            this.connection.mute(true); // ← Mutes THIS participant in conference
            console.log("Training conference connection muted");
        } catch (error) {
            console.error("Error muting training conference:", error);
            this._fallbackDeviceMute(true);
        }
    }
}
```

**Critical Understanding**:
- `this.connection` = Conference connection created by `device.connect({conference: "TrainerUsername"})`
- `this.connection.mute(true)` = Mutes THIS participant in the conference
- Does NOT disconnect from conference
- Other participants can still hear each other and the external caller
- This participant cannot be heard by anyone

#### Step 4.2: Who Gets Muted

**Scenario**: TraineeUser1 has control, external call arrives

**TrainerUsername**:
```javascript
this.volunteerID = "TrainerUsername"
this.incomingCallsTo = "TraineeUser1"
this.volunteerID !== this.incomingCallsTo → TRUE → MUTE
```
Result: **Trainer is MUTED**

**TraineeUser1** (has control):
```javascript
this.volunteerID = "TraineeUser1"
this.incomingCallsTo = "TraineeUser1"
this.volunteerID !== this.incomingCallsTo → FALSE → STAY UNMUTED
```
Result: **TraineeUser1 stays UNMUTED** (can talk to external caller)

**TraineeUser2**:
```javascript
this.volunteerID = "TraineeUser2"
this.incomingCallsTo = "TraineeUser1"
this.volunteerID !== this.incomingCallsTo → TRUE → MUTE
```
Result: **TraineeUser2 is MUTED**

#### Step 4.3: Conference Audio Routing

**Inside Twilio Conference "TrainerUsername"**:

```
┌─────────────────────────────────────────────────────┐
│  Conference: "TrainerUsername"                      │
│                                                     │
│  TrainerUsername (MUTED)                            │
│    ├─ CAN HEAR: TraineeUser1, External Caller      │
│    └─ CANNOT BE HEARD: Nobody hears trainer        │
│                                                     │
│  TraineeUser1 (UNMUTED - has control)               │
│    ├─ CAN HEAR: Trainer, TraineeUser2, Ext Caller  │
│    └─ CAN BE HEARD: Everyone hears trainee          │
│                                                     │
│  TraineeUser2 (MUTED)                               │
│    ├─ CAN HEAR: TraineeUser1, External Caller      │
│    └─ CANNOT BE HEARD: Nobody hears them           │
│                                                     │
│  +15551234567 (External Caller - UNMUTED)           │
│    ├─ CAN HEAR: Only TraineeUser1                  │
│    └─ CAN BE HEARD: Everyone hears caller          │
└─────────────────────────────────────────────────────┘
```

**Result**: External caller and TraineeUser1 can have a conversation while trainer and other trainees listen silently.

---

## Why There's NO Client-Side Call Acceptance

### The Key Misunderstanding

**WRONG Mental Model** (Normal Twilio Calls):
```
1. External caller dials
2. Twilio sends 'incoming' event to device
3. Client JavaScript: connection.accept() or device.connect()
4. Direct 1-to-1 call established
```

**CORRECT Mental Model** (Training Conference):
```
1. Conference "TrainerUsername" already exists (trainer + trainees in it)
2. External caller dials
3. Twilio webhook generates TwiML: <Conference>TrainerUsername</Conference>
4. Twilio adds external caller to EXISTING conference
5. No client-side action needed - caller is already connected!
```

### Why startNewCall() Doesn't "Accept" the Call

**File**: `trainingSessionUpdated.js` (line 935)

```javascript
async startNewCall() {
    // This does NOT accept incoming call!
    // The call is ALREADY CONNECTED via server-side TwiML routing!

    // This method only:
    // 1. Updates client-side state (currentlyOnCall = true)
    // 2. Mutes non-controllers
    // 3. Sends notifications to other participants

    if (this.volunteerID !== this.incomingCallsTo) {
        this.muteConferenceCall(); // Mute myself
    } else {
        await this.notifyCallStart(); // Tell others to mute
    }
}
```

**The external caller is ALREADY in the conference** by the time `startNewCall()` is called!

---

## Conference Lifecycle Management

### Conference Creation

**Who Creates**: First participant with `startConferenceOnEnter: true`

**Typically**: The trainer (moderator role)

```javascript
// Trainer connecting (line 1030)
const params = {
    conference: "TrainerUsername",
    conferenceRole: 'moderator',
    startConferenceOnEnter: true,  // ← Trainer creates conference
    endConferenceOnExit: true,     // ← Conference ends when trainer leaves
    muted: false
};
device.connect(params);
```

**Twilio Action**:
- Conference "TrainerUsername" doesn't exist → Create it
- Add trainer as first participant
- Conference now active and waiting for more participants

### Adding Participants

**Trainees join** (line 1030):
```javascript
const params = {
    conference: "TrainerUsername",  // ← Same conference name!
    conferenceRole: 'participant',
    startConferenceOnEnter: false,  // ← Don't create (already exists)
    endConferenceOnExit: false,     // ← Don't end when I leave
    muted: false
};
device.connect(params);
```

**External callers routed** (twilioRedirect.php):
```xml
<Conference startConferenceOnEnter="true"
            endConferenceOnExit="true">
    TrainerUsername
</Conference>
```

**All connect to SAME conference by name**: "TrainerUsername"

### Conference Persistence

**The conference persists as long as**:
- At least one participant is connected
- No participant with `endConferenceOnExit: true` has left

**If trainer (moderator) disconnects**:
- `endConferenceOnExit: true` → Conference ENDS
- All participants are disconnected
- Trainees must reconnect when trainer rejoins

### Conference Restart After External Call

**File**: `trainingSessionUpdated.js` (line 1270)

```javascript
async restartConferenceAfterCall() {
    // Disconnect current conference
    if (this.connection) {
        this.connection.disconnect();
    }

    // Wait for cleanup
    await new Promise(resolve => setTimeout(resolve, 2000));

    // Try to end conference on Twilio side
    await fetch('/trainingShare3/endConference.php', {
        method: 'POST',
        body: JSON.stringify({ conferenceId: this.conferenceID })
    });

    // Wait, then reconnect
    await new Promise(resolve => setTimeout(resolve, 1000));

    // Reconnect to NEW conference with SAME name
    this.conferenceID = this.trainer.id; // Still "TrainerUsername"!
    this.connectConference();

    // Notify others to reconnect
    await this.notifyOthersToReconnect();
}
```

**Why Restart?**:
- External caller needs to be removed from conference
- Cannot selectively kick participants from client side
- Solution: End conference, start new one with same name
- All training participants reconnect
- External caller is gone (their call ended)

**Conference Name Stays Same**:
- Old conference: "TrainerUsername" (had external caller)
- New conference: "TrainerUsername" (no external caller)
- Twilio treats these as different conferences (different SIDs)
- But same name allows twilioRedirect.php to continue routing correctly

---

## Database Schema Critical to Call Routing

### volunteers Table

```sql
CREATE TABLE volunteers (
    UserName VARCHAR(255) PRIMARY KEY,
    FirstName VARCHAR(255),
    LastName VARCHAR(255),
    LoggedOn INT,  -- 1=normal volunteer, 4=trainer, 6=trainee
    TraineeID TEXT, -- Comma-separated list of trainee usernames (for trainers)
    ...
);

Example:
┌─────────────────┬───────────┬──────────┬──────────────────────────┐
│ UserName        │ LoggedOn  │ TraineeID                         │
├─────────────────┼───────────┼──────────────────────────────────┤
│ TrainerUsername │ 4         │ TraineeUser1,TraineeUser2        │
│ TraineeUser1    │ 6         │ NULL                             │
│ TraineeUser2    │ 6         │ NULL                             │
└─────────────────┴───────────┴──────────────────────────────────┘
```

**Critical Fields**:
- `LoggedOn = 4`: Trainer in training mode
- `LoggedOn = 6`: Trainee in training mode
- `TraineeID`: List of trainees assigned to this trainer

**Used By twilioRedirect.php**:
```php
// If volunteer is trainee (LoggedOn = 6)
if ($loggedOnStatus == 6) {
    // Find trainer using FIND_IN_SET
    $query = "SELECT UserName FROM volunteers
              WHERE FIND_IN_SET(?, TraineeID) > 0
              AND LoggedOn = 4";
    // Returns: "TrainerUsername"
    // Route call to trainer's conference!
}
```

### training_session_control Table

```sql
CREATE TABLE training_session_control (
    trainer_id VARCHAR(255) PRIMARY KEY,
    active_controller VARCHAR(255),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

Example:
┌─────────────────┬───────────────────┬──────────────────────┐
│ trainer_id      │ active_controller │ last_updated         │
├─────────────────┼───────────────────┼──────────────────────┤
│ TrainerUsername │ TraineeUser1      │ 2025-01-13 10:30:00  │
└─────────────────┴───────────────────┴──────────────────────┘
```

**Purpose**: Track who has control in each training session

**Updated By**: `setTrainingControl.php` when control is transferred

**Read By**: Client-side JavaScript to determine `this.incomingCallsTo`

### CallRouting Table

```sql
CREATE TABLE CallRouting (
    CallSid VARCHAR(255) PRIMARY KEY,
    Volunteer VARCHAR(255),
    ...
);

Example:
┌────────────────────────────────────┬──────────────┐
│ CallSid                            │ Volunteer    │
├────────────────────────────────────┼──────────────┤
│ CA1234567890abcdef1234567890abcdef │ TraineeUser1 │
└────────────────────────────────────┴──────────────┘
```

**Purpose**: Maps Twilio CallSid to which volunteer should answer

**Created By**: Call routing system (before twilioRedirect.php is called)

**Read By**: twilioRedirect.php to determine initial volunteer assignment

---

## Complete Call Flow Diagram

```
┌────────────────────────────────────────────────────────────────────┐
│ EXTERNAL CALLER DIALS HELPLINE                                     │
│ +1 (555) 123-4567 → +1-800-XXX-XXXX                                │
└────────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌────────────────────────────────────────────────────────────────────┐
│ TWILIO RECEIVES CALL                                               │
│ - Creates CallSid: CA123...                                        │
│ - Routes to volunteer: TraineeUser1                                │
│ - Inserts: CallRouting(CallSid='CA123...', Volunteer='TraineeUser1')│
└────────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌────────────────────────────────────────────────────────────────────┐
│ TWILIO WEBHOOK: twilioRedirect.php                                │
│                                                                    │
│ 1. Query: SELECT Volunteer FROM CallRouting WHERE CallSid=?       │
│    Result: "TraineeUser1"                                         │
│                                                                    │
│ 2. Query: SELECT LoggedOn FROM volunteers WHERE UserName=?        │
│    Result: LoggedOn = 6 (trainee!)                                │
│                                                                    │
│ 3. Query: SELECT UserName FROM volunteers                         │
│           WHERE FIND_IN_SET('TraineeUser1', TraineeID) > 0        │
│           AND LoggedOn = 4                                        │
│    Result: "TrainerUsername"                                      │
│                                                                    │
│ 4. Override: $Volunteer = "TrainerUsername"                       │
│                                                                    │
│ 5. Return TwiML:                                                   │
│    <Conference>TrainerUsername</Conference>                        │
└────────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌────────────────────────────────────────────────────────────────────┐
│ TWILIO PROCESSES TwiML                                             │
│                                                                    │
│ 1. Parse conference name: "TrainerUsername"                        │
│ 2. Lookup conference "TrainerUsername" → EXISTS!                   │
│ 3. Add external caller +15551234567 to conference                  │
│ 4. External caller now in conference with:                         │
│    - TrainerUsername                                               │
│    - TraineeUser1                                                  │
│    - TraineeUser2                                                  │
└────────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌────────────────────────────────────────────────────────────────────┐
│ CLIENT-SIDE NOTIFICATION                                           │
│                                                                    │
│ TraineeUser1 (has control):                                        │
│   trainingSession.startNewCall()                                   │
│   ├─ Sets: this.currentlyOnCall = true                             │
│   ├─ Stays: UNMUTED                                                │
│   └─ Calls: notifyCallStart()                                      │
│       └─ Sends notifications to trainer and other trainees         │
│                                                                    │
│ TrainerUsername (no control):                                      │
│   Receives: 'external-call-start' signal                           │
│   trainingSession.handleExternalCallStart()                        │
│   ├─ Sets: this.currentlyOnCall = true                             │
│   └─ Calls: this.muteConferenceCall()                              │
│       └─ this.connection.mute(true) → MUTED                        │
│                                                                    │
│ TraineeUser2 (no control):                                         │
│   Receives: 'external-call-start' signal                           │
│   trainingSession.handleExternalCallStart()                        │
│   ├─ Sets: this.currentlyOnCall = true                             │
│   └─ Calls: this.muteConferenceCall()                              │
│       └─ this.connection.mute(true) → MUTED                        │
└────────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌────────────────────────────────────────────────────────────────────┐
│ ACTIVE CONFERENCE STATE                                            │
│                                                                    │
│ Conference: "TrainerUsername"                                      │
│                                                                    │
│ TrainerUsername:     CONNECTED, MUTED                              │
│ TraineeUser1:        CONNECTED, UNMUTED ← Can talk to caller      │
│ TraineeUser2:        CONNECTED, MUTED                              │
│ +15551234567:        CONNECTED, UNMUTED ← External caller          │
│                                                                    │
│ Audio Routing:                                                     │
│ - External caller hears: TraineeUser1                              │
│ - TraineeUser1 hears: External caller                              │
│ - Trainer/TraineeUser2 hear: Both but cannot speak                │
└────────────────────────────────────────────────────────────────────┘
```

---

## Why the Original Analysis Was Wrong

### Incorrect Assumption #1: Client-Side Call Acceptance Needed

**Wrong**:
> The `startNewCall()` method should call `device.connect()` or `connection.accept()` to answer the external call.

**Reality**:
- External call is ALREADY ANSWERED by Twilio server-side
- twilioRedirect.php returns TwiML that routes caller into conference
- By the time `startNewCall()` is called, external caller is already in conference
- `startNewCall()` only manages muting/unmuting of existing conference participants

### Incorrect Assumption #2: Separate Call Connection

**Wrong**:
> External calls are separate from the training conference and need to be connected.

**Reality**:
- External calls are ADDED TO the existing training conference
- No separate call object or connection
- The conference connection (`this.connection`) includes everyone:
  - Trainer (via device.connect({conference: "TrainerUsername"}))
  - Trainees (via device.connect({conference: "TrainerUsername"}))
  - External caller (via TwiML routing to conference "TrainerUsername")

### Incorrect Assumption #3: Conference Restart Creates New Conference ID

**Wrong**:
> Conference restart should use a new conference ID to avoid conflicts.

**Reality**:
- Conference name MUST stay the same: "TrainerUsername"
- twilioRedirect.php always routes to conference named after the trainer
- If conference name changed, new external calls couldn't find it
- Twilio differentiates conferences by internal SID, not name
- Same name = allows consistent routing, different SID = separate conference instance

---

## Key Takeaways - NEVER FORGET

1. **Training conference name = Trainer's username**
   - Always "TrainerUsername", never a random ID
   - Set in `_initializeAsTrainer()` and `_initializeAsTrainee()`
   - Critical for twilioRedirect.php routing

2. **External calls route TO conference, not THROUGH client**
   - No client-side acceptance needed
   - TwiML routing adds caller to existing conference
   - `startNewCall()` only manages muting

3. **Conference is SHARED by all participants**
   - Trainer + trainees connect via device.connect({conference: "TrainerUsername"})
   - External callers connect via TwiML <Conference>TrainerUsername</Conference>
   - Everyone in same Twilio conference room

4. **Muting is PARTICIPANT-LEVEL, not call-level**
   - `this.connection.mute(true)` mutes THIS participant
   - Doesn't disconnect anyone
   - Other participants stay connected and can hear others

5. **Control determines who stays unmuted**
   - `this.incomingCallsTo` = who receives external calls
   - If `this.volunteerID === this.incomingCallsTo` → Stay unmuted
   - Otherwise → Mute yourself

6. **Database drives routing logic**
   - `LoggedOn = 6` → Trainee → Look up trainer
   - `FIND_IN_SET(trainee, TraineeID)` → Finds trainer
   - Route to trainer's conference always

This architecture ensures:
- ✅ External callers always reach the right conference
- ✅ Control can transfer between trainer and trainees
- ✅ All participants hear the call (training purpose)
- ✅ Only controller can speak to caller
- ✅ No client-side call acceptance complexity
