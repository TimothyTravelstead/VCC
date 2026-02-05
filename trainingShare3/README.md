# Multi-Trainee Screen Sharing System

This refactored PHP-based signaling system supports 1 trainer + multiple trainees for screen sharing.

## 🏗️ **Database Architecture - IMPORTANT**

**CRITICAL:** The system uses different database fields for different purposes:

### **Permission Fields (Admin-Set, Persistent)**
- **`trainer`** - Admin-set permission: Can user login as trainer? (1=yes, NULL=no)
- **`trainee`** - Admin-set permission: Can user login as trainee? (1=yes, NULL=no)
- **These NEVER change during login/logout**

### **Session Status Fields (Login/Logout Managed)**
- **`LoggedOn`** - Current status: 4=trainer, 6=trainee, 1=volunteer, 0=logged out
- **`TraineeID`** - Active trainer's trainees (comma-separated list, set when trainer logs in)
  - Format: `"trainee1,trainee2,trainee3"` supports multiple trainees per trainer
  - Query: Use `FIND_IN_SET(trainee_id, TraineeID)` to find trainer for specific trainee
- **These change based on current session state**

### **Role Detection**
Use `LoggedOn` values to determine current roles, NOT permission fields.

### **Multi-Trainee Support**
- One trainer can manage multiple trainees simultaneously
- TraineeID field stores comma-separated list: `"TimTesting,JohnDoe,JaneSmith"`
- Screen sharing broadcasts from trainer to all assigned trainees
- Each trainee connects individually but receives same trainer stream

## Key Changes from Original System

### Architecture Changes
1. **Room-based signaling**: Instead of 1:1 communication, uses room concept
2. **Multi-participant support**: One trainer can share screen with multiple trainees
3. **Enhanced message routing**: Messages can be targeted or broadcast
4. **Better state management**: Tracks all participants in a room

### File Structure
- `signalingServerMulti.php` - Enhanced signaling server supporting multiple participants
- `screenSharingControlMulti.js` - JavaScript client for multi-trainee support
- `roomManager.php` - REST API for room management
- `README.md` - This documentation

## How to Integrate

### 1. Update index2.php
Replace the existing trainingShare script with the new multi-trainee version:

```html
<!-- Replace this line: -->
<script src="trainingShare/screenSharingControl.js"></script>

<!-- With this: -->
<script src="trainingShare3/screenSharingControlMulti.js"></script>
```

### 2. Initialize Screen Sharing
```javascript
// For trainers
const screenSharing = new MultiTraineeScreenSharing({
    role: 'trainer',
    trainerId: trainerVolunteerID,
    participantId: volunteerID,
    roomId: trainerVolunteerID // Use trainer ID as room identifier
});

// For trainees  
const screenSharing = new MultiTraineeScreenSharing({
    role: 'trainee',
    trainerId: trainerVolunteerID,
    participantId: volunteerID,
    roomId: trainerVolunteerID
});
```

### 3. Control Methods
```javascript
// Trainer controls
screenSharing.startScreenSharing(); // Start sharing screen
screenSharing.stopScreenSharing();  // Stop sharing screen

// Get participant info
const participants = screenSharing.getParticipants();
const status = screenSharing.getConnectionStatus(participantId);

// Cleanup
screenSharing.closeConnection();
```

## API Endpoints

### Room Management API (`roomManager.php`)

#### Get Room Status
```
GET /trainingShare3/roomManager.php?action=room-status&roomId=TRAINER_ID
```

#### Get Participant List
```
GET /trainingShare3/roomManager.php?action=participant-list&roomId=TRAINER_ID
```

#### Join Room
```
POST /trainingShare3/roomManager.php?action=join-room&roomId=TRAINER_ID
Content-Type: application/json

{
    "participantId": "USER123",
    "role": "trainee"
}
```

#### Leave Room
```
DELETE /trainingShare3/roomManager.php?action=leave-room&roomId=TRAINER_ID
```

## Signal Flow

### Connection Establishment
1. **Trainer starts**: Creates room, begins screen capture
2. **Trainees join**: Connect to room, establish WebRTC connections
3. **Screen sharing**: Trainer's screen is broadcast to all trainees
4. **Real-time sync**: All participants receive screen sharing events

### Message Types
- `join-room` - Participant joins training room
- `leave-room` - Participant leaves training room
- `offer` - WebRTC offer for connection establishment
- `answer` - WebRTC answer response
- `ice-candidate` - ICE candidate for NAT traversal
- `screen-share-start` - Trainer starts screen sharing
- `screen-share-stop` - Trainer stops screen sharing

## File-based Signaling Details

### Room Files
- `Signals/room_TRAINER_ID.json` - Room state and participant list
- `Signals/participant_USER_ID.txt` - Individual participant message queue

### Message Delivery
1. **Broadcast**: Messages sent to all participants in room
2. **Targeted**: Messages sent to specific participant
3. **Event Source**: Long-polling for real-time message delivery
4. **File cleanup**: Automatic cleanup of read messages and inactive participants

## Advantages over Socket.IO Approach

1. **Server compatibility**: Works on any PHP hosting (no Node.js required)
2. **Firewall friendly**: Uses standard HTTP/HTTPS ports
3. **Simple deployment**: No additional services to manage
4. **Stateless**: No persistent connections to manage on server
5. **Debugging**: Easy to inspect signal files for troubleshooting

## Legacy Compatibility

The `SignalingServer()` function wrapper provides compatibility with existing code that expects the old interface:

```javascript
// Legacy usage still works
const signaling = new SignalingServer('Trainer');
signaling.shareMyScreen();
signaling.getSharedScreen();
```

## Performance Considerations

1. **Polling frequency**: EventSource polls every 0.5 seconds
2. **File cleanup**: Automatic cleanup of old signal files
3. **Participant timeout**: Inactive participants removed after 5 minutes
4. **Message batching**: Multiple events can be sent in single response

## ✅ **SYSTEM STATUS: FULLY FUNCTIONAL** (January 28, 2025)

### **Screen Sharing Issue Resolved**

**Problem**: Trainees could see poster image but not trainer's actual shared screen

**Root Causes Fixed**:
1. **Poster path mismatch**: `index2.php` used `trainingShare/poster.png` instead of `trainingShare3/poster.png`
2. **Missing video attributes**: Added `playsinline` for mobile compatibility  
3. **Incorrect UI hiding**: Fixed element selectors to target actual DOM elements
4. **Video display issues**: Enhanced styling and automatic play handling

**Files Modified**:
- `../index2.php:454` - Fixed poster path, added `playsinline` attribute
- `simpleTrainingScreenShare.js` - Enhanced video handling, UI hiding, and debugging

**Key Technical Fixes**:
```javascript
// Enhanced video element setup
remoteVideo.onloadedmetadata = () => {
    remoteVideo.play().then(() => {
        this.log('Video playing successfully');
    }).catch(e => {
        this.log(`Video play failed: ${e.message}`);
    });
};

// Better video styling for full-screen display
remoteVideo.style.objectFit = 'contain';
remoteVideo.style.visibility = 'visible';
remoteVideo.removeAttribute('hidden');
```

### **Current Working Flow**:
1. **Trainer logs in** → Auto-starts screen sharing (`startScreenShare()`)
2. **Trainee logs in** → Polls for trainer every 2 seconds (`checkForTrainer()`)
3. **Connection established** → WebRTC stream flows from trainer to trainee
4. **UI switches** → Trainee interface hidden, full-screen trainer view shown
5. **Screen displays** → Trainer's screen appears in trainee's browser

### **Debugging Features Added**:
- **Emoji indicators**: 🎯 🔗 ✅ ❌ ⏳ for easy console log identification
- **Connection monitoring**: ICE and connection state tracking
- **Stream logging**: Track information for video/audio tracks
- **Enhanced error reporting**: Detailed failure messages

### **Integration Points**:
- **TrainingSession class**: `trainingSessionUpdated.js` manages overall session
- **Video elements**: `#remoteVideo` and `#localVideo` in `index2.php`
- **UI coordination**: Seamless switching between normal and training views
- **Session management**: PHP session integration for user identification

## Testing

1. **Single trainer, multiple trainees**: Primary use case ✅ WORKING
2. **Connection recovery**: Handles network interruptions
3. **Browser compatibility**: Works with modern browsers supporting WebRTC
4. **Mobile support**: `playsinline` attribute added for mobile compatibility ✅ FIXED
5. **Full-screen display**: Immersive training experience ✅ WORKING

### **Test with**: `/trainingShare3/test.html` for isolated debugging