# Training System Restructuring Plan

**Created:** January 29, 2026
**Status:** Implementation Complete, Testing Required

---

## Executive Summary

The training system (`trainingShare3/`) has been restructured to use **database-backed signaling only**, removing the unreliable legacy file-based signaling system. This document outlines the architecture, completed changes, and remaining work.

---

## 1. Problem Statement

### Legacy System Issues
- **File-based signaling** (`/Signals/` directory) had race conditions
- **1-second polling** was too slow for reliable WebRTC negotiation
- **Silent fallback** masked failures - users didn't know when signaling broke
- **No atomic operations** - multiple concurrent writes could corrupt state

### Goals
1. Replace file-based signaling with database-backed atomic operations
2. Reduce polling latency from 1000ms to 500ms
3. Remove silent fallbacks - fail visibly so issues can be diagnosed
4. Maintain complete isolation from normal volunteer operations

---

## 2. Architecture Overview

### Training System Isolation

The training system is **completely isolated** from normal volunteer operations:

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         NORMAL VOLUNTEER MODE                           │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────────────────────┐  │
│  │ index.js    │───▶│ vccFeed.php │───▶│ volunteers.OnCall           │  │
│  │ callMonitor │    │ vccPoll.php │    │ CallRouting table           │  │
│  └─────────────┘    └─────────────┘    └─────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
                              │
                              │ Sees OnCall status only
                              ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         TRAINING MODE (LoggedOn 4/6)                    │
│  ┌─────────────────────┐    ┌──────────────────┐    ┌────────────────┐  │
│  │ trainingSession     │───▶│ signalPollDB.php │───▶│ training_*     │  │
│  │ Updated.js          │    │ signalSend.php   │    │ tables         │  │
│  │                     │    │ roomJoin.php     │    │                │  │
│  │ screenSharing       │    │ setMuteState.php │    │                │  │
│  │ ControlMulti.js     │    │ bulkMute.php     │    │                │  │
│  └─────────────────────┘    └──────────────────┘    └────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
```

### Mode Detection

| LoggedOn Value | Mode | Scripts Loaded |
|----------------|------|----------------|
| 1 | Normal Volunteer | index.js, callMonitor only |
| 4 | Trainer | + trainingSessionUpdated.js, screenSharingControlMulti.js |
| 6 | Trainee | + trainingSessionUpdated.js, screenSharingControlMulti.js |

### Database Tables (New)

```sql
training_rooms           -- Active training sessions (keyed by trainer username)
training_participants    -- Who's in each room
training_signals         -- WebRTC signaling queue (replaces /Signals/ files)
training_session_state   -- State machine: INITIALIZING→CONNECTED→ON_CALL→DISCONNECTED
training_mute_state      -- Server-authoritative mute tracking
training_events_log      -- Audit trail for debugging
```

---

## 3. How Normal Volunteers See Training Activity

**Key Insight:** Normal volunteers see training activity through the **standard visibility mechanism**, not through training signaling.

### For Incoming Calls

When a call comes in and a training session answers:

1. **answerCall.php** executes:
   ```php
   // Clear ringing for ALL other volunteers
   UPDATE Volunteers SET IncomingCallSid = NULL WHERE UserName != ? AND IncomingCallSid = ?

   // Set OnCall for ALL training participants
   if ($loggedOnStatus == 6) {  // Trainee answered
       UPDATE Volunteers SET OnCall = 1 WHERE UserName = ? (trainer)
   } elseif ($loggedOnStatus == 4) {  // Trainer answered
       UPDATE Volunteers SET OnCall = 1 WHERE UserName IN (trainees)
   }
   ```

2. **vccFeed.php / vccPoll.php** broadcasts updated user list (every 2 seconds)

3. **Normal volunteer's index.js** receives update:
   - `User.updateUser()` detects `callObject` is now NULL
   - `newCall.cancelCall()` stops ringing

### For Chats

Chats are **NOT synchronized** like calls:
- Each training participant receives independent chat invites
- When one accepts, `ChatInvite` is cleared for all volunteers
- Other training participants don't automatically join

### What Normal Volunteers See vs Cannot See

| Visible to Normal Volunteers | Hidden from Normal Volunteers |
|------------------------------|-------------------------------|
| OnCall status (busy/available) | WebRTC signaling messages |
| LoggedOn status (4=trainer, 6=trainee) | Mute state changes |
| TraineeID relationships | Control transfer signals |
| Call history entries | Screen sharing streams |

---

## 4. Completed Changes

### 4.1 Removed Legacy Fallback from screenSharingControlMulti.js

**Before:**
```javascript
// Try database-backed signaling first, fall back to legacy
if (this.useDBSignaling) {
    // ... DB signaling
} else {
    // Legacy file-based signaling
    this.pollingUrl = `/trainingShare3/pollSignals.php?participantId=...`;
}
```

**After:**
```javascript
// Database-backed signaling only - NO LEGACY FALLBACK
this.pollingUrl = `/trainingShare3/signalPollDB.php?roomId=${encodeURIComponent(this.roomId)}`;
this.signalingUrl = `/trainingShare3/signalSend.php`;
this.pollingIntervalMs = 500;
console.log(`📡 Using DB-backed signaling (500ms polling) - NO LEGACY FALLBACK`);
```

### 4.2 Removed Legacy Fallback from trainingSessionUpdated.js

**Before:**
```javascript
try {
    await this.initDBSignaling();
} catch (error) {
    console.warn('DB signaling init failed, using legacy:', error);
}
```

**After:**
```javascript
// Initialize database-backed signaling (REQUIRED - no legacy fallback)
await this.initDBSignaling();
// Throws error if fails - training cannot function without signaling
```

### 4.3 Added Error Visibility

New `_showSignalingError()` method alerts users when signaling fails:
```javascript
_showSignalingError(message) {
    console.error('🚨 Signaling Error:', message);
    if (typeof alert === 'function') {
        alert(`Training Session Error: ${message}`);
    }
}
```

Error thresholds:
- **Polling errors:** Alert after 10 consecutive failures
- **Send errors:** Alert after 5 consecutive failures
- **Room join failure:** Immediate alert

### 4.4 Added TrainingSignalingClient.js to index2.php

```html
<script src="trainingShare3/lib/TrainingSignalingClient.js" type="text/javascript"></script>
<script src="trainingShare3/screenSharingControlMulti.js" type="text/javascript"></script>
<script src="trainingShare3/trainingSessionUpdated.js?v=2025012901" type="text/javascript"></script>
```

---

## 5. DB Signaling Endpoints

| Endpoint | Purpose | Polling Rate |
|----------|---------|--------------|
| `signalPollDB.php` | Poll for incoming signals | 500ms |
| `signalSend.php` | Send WebRTC signals | On-demand |
| `roomJoin.php` | Join training room | On session start |
| `roomLeave.php` | Leave training room | On session end |
| `setMuteState.php` | Set individual mute state | On-demand |
| `getMuteState.php` | Get current mute states | On-demand |
| `bulkMute.php` | Mute all non-controllers | On external call |
| `transitionState.php` | State machine transitions | On events |
| `getSessionState.php` | Get full session state | On-demand |

---

## 6. Call Flow: Training Session Answers

```
INCOMING CALL
    │
    ▼
dialHotline.php → Rings all volunteers (including training)
    │
    ▼
Training participant clicks Answer
    │
    ▼
answerCall.php
    ├── Updates CallRouting.Volunteer
    ├── Clears IncomingCallSid for OTHER volunteers
    ├── Sets OnCall=1 for ALL training participants
    └── Redirects caller to trainer's conference
    │
    ▼
twilioRedirect.php → Joins caller to conference
    │
    ▼
[Training-specific muting via trainingSessionUpdated.js]
    ├── setExternalCallActive(true)
    ├── applyMuteState() → Mutes non-controllers
    └── bulkMuteNonControllers() → Server-side mute via Twilio API
    │
    ▼
Normal volunteers see:
    ├── Call stops ringing (IncomingCallSid = NULL)
    └── Training users show as "on call" (OnCall = 1)
```

---

## 7. Files Modified

| File | Changes |
|------|---------|
| `trainingShare3/screenSharingControlMulti.js` | Removed legacy fallback, added error handling |
| `trainingShare3/trainingSessionUpdated.js` | Made DB signaling mandatory, removed silent fallbacks |
| `index2.php` | Added TrainingSignalingClient.js script |

---

## 8. Files That Can Be Removed (After Testing)

Once the new system is verified working, these legacy files can be deleted:

| File | Purpose | Safe to Remove |
|------|---------|----------------|
| `trainingShare3/signalingServerMulti.php` | Legacy file-based signaling POST | Yes |
| `trainingShare3/pollSignals.php` | Legacy file-based signaling poll | Yes |
| `trainingShare3/Signals/` directory | File-based message queue | Yes |
| `trainingShare3/trainingFeed.php` | Old EventSource feed | Verify unused first |
| `trainingShare3/trainingFeed_production.php` | Old EventSource feed | Verify unused first |

---

## 9. Testing Checklist

### Phase 1: Basic Connectivity
- [ ] Trainer login establishes DB signaling connection
- [ ] Trainee login establishes DB signaling connection
- [ ] `signalPollDB.php` returns signals within 500ms
- [ ] Console shows "DB-backed signaling (500ms polling) - NO LEGACY FALLBACK"

### Phase 2: Screen Sharing
- [ ] Trainer can share screen to trainees
- [ ] Trainees receive screen share automatically
- [ ] Multiple trainees receive same screen share
- [ ] Screen sharing reconnects after brief network interruption

### Phase 3: Call Handling
- [ ] External call rings on training console
- [ ] Training participant can answer call
- [ ] Other volunteers stop ringing when training answers
- [ ] Non-controller participants get muted on call start
- [ ] All participants unmuted when call ends
- [ ] Normal volunteer console shows training users as "on call"

### Phase 4: Control Transfer
- [ ] Trainer can transfer control to trainee
- [ ] Trainee with control can take calls
- [ ] Control transfer updates CallControl table
- [ ] Mute state adjusts correctly after control transfer

### Phase 5: Error Handling
- [ ] Signaling failure shows alert to user (not silent)
- [ ] Session continues to attempt reconnection
- [ ] Error count resets after successful operation

---

## 10. Rollback Plan

If critical issues are found:

1. **Revert index2.php** to remove TrainingSignalingClient.js
2. **Revert trainingSessionUpdated.js** to restore legacy fallback
3. **Revert screenSharingControlMulti.js** to restore legacy fallback

The legacy files (`pollSignals.php`, `signalingServerMulti.php`, `/Signals/`) have NOT been deleted, so fallback is still possible.

---

## 11. Success Criteria

The restructuring is complete when:

1. **All Phase 1-5 tests pass** without using legacy signaling
2. **No "fallback" messages** appear in console logs
3. **Error alerts** appear when signaling genuinely fails (not silent)
4. **Normal volunteer operations** are completely unaffected
5. **2-week production period** with no training-specific issues

After success criteria are met, legacy files can be removed and this plan archived.

---

## Appendix: Key Code Locations

### Training Mode Detection
- `index.js:isTrainingMode` - Client-side mode check
- `loginverify2.php` - Sets LoggedOn to 4 (trainer) or 6 (trainee)

### Call Answer Synchronization
- `answerCall.php:103-153` - Clears ringing, syncs OnCall for training

### User List Visibility
- `vccFeed.php:179-302` - Includes OnCall, TrainerID, traineeOnCall
- `vccPoll.php` - Redis-cached version of same query

### Mute Logic
- `trainingSessionUpdated.js:applyMuteState()` - Client decision
- `muteConferenceParticipants.php` - Server-side Twilio API calls
- `trainingShare3/setMuteState.php` - DB-backed mute endpoint
