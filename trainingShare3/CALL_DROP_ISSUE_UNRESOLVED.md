# Call Drop Issue - UNRESOLVED - January 2025

## Status: ISSUE NOT FIXED ❌

**Problem Persists**: When a trainer clicks "Accept" on an incoming external call, the call still hangs up immediately.

---

## Executive Summary

### The Core Issue
Training mode uses an **EXISTING** Twilio conference (created when trainer/trainees connect). When an external call arrives and the trainer clicks "Accept", there's a race condition between:
1. `answerCall.php` calling `$call->update()` to redirect to `twilioRedirect.php`
2. The original `<Dial action='/unAnsweredCall.php'>` from `dialAll.php` completing/failing

The `<Dial>` action callback to `unAnsweredCall.php` appears to be triggering BEFORE or DURING the redirect, causing the call to drop.

### Key Discoveries
1. ✅ Training conference EXISTS before external call (created by `device.connect()`)
2. ✅ Conference is named after trainer's volunteerID
3. ✅ Trainer is moderator (startConferenceOnEnter=true, endConferenceOnExit=true)
4. ✅ `dialAll.php` sets `<Dial action='/unAnsweredCall.php'>` for all calls
5. ✅ `unAnsweredCall.php` is being triggered (user observation)
6. ❌ The `$call->update()` redirect may not be cancelling the original `<Dial>` in time

### What Still Needs Investigation
1. Check Twilio logs to see exact sequence of webhook calls
2. Add logging to see if `answerCall.php` completes successfully
3. Verify timing: does `unAnsweredCall.php` run BEFORE or AFTER `twilioRedirect.php`?
4. Check if `$call->update()` throws any errors for training mode
5. Determine if Twilio has issues adding participants to existing conferences via update

---

## ⚡ CRITICAL FINDING (Latest)

**User Discovery**: The system is somehow triggering `unansweredCall.php` BEFORE the redirect happens.

This means:
1. Trainer clicks "Accept"
2. `answerCall.php` starts executing
3. **Something triggers `unansweredCall.php`** ← THIS IS THE PROBLEM
4. Call gets ended/rejected as "unanswered"
5. The redirect to `twilioRedirect.php` never completes or is ineffective

**Key Questions Now**:
- What triggers `unansweredCall.php`?
- Is it a Twilio webhook callback?
- Is it JavaScript client-side code?
- Is there a timeout that fires?
- Why does it think the call is "unanswered" when Accept was clicked?

### Analysis of unAnsweredCall.php Trigger

**Found in `dialAll.php` (lines 178-179)**:
```php
$dialPath = $WebAddress . "/unAnsweredCall.php";
echo "    <Dial action='" . htmlspecialchars($dialPath) . "' method='POST'>\n";
```

**The Flow**:
1. External call comes in → `dialAll.php` generates TwiML
2. TwiML contains: `<Dial action='/unAnsweredCall.php'>`
3. Inside the `<Dial>` are multiple `<Client>` tags (all volunteers including trainer)
4. Trainer clicks Accept → `answerCall.php` runs:
   - Sets `OnCall = 1` for trainer
   - Calls `$call->update()` to redirect to `twilioRedirect.php`
5. **RACE CONDITION**: The original `<Dial>` from dialAll.php is still active
6. When the `<Dial>` completes/fails, Twilio calls the action URL: `unAnsweredCall.php`
7. `unAnsweredCall.php` runs and may interfere with the redirect

**What unAnsweredCall.php Does**:
```php
// Line 50-54: Updates call category
UPDATE CallerHistory SET Category = ?, Length = sec_to_time(?) WHERE CallSid = ?

// Line 57-66: Clears volunteer status
UPDATE Volunteers SET Ringing = NULL, HotlineName = NULL, ...
WHERE IncomingCallSid = ? AND oncall = 0
```

**The Race**:
- `answerCall.php` calls `$call->update()` to redirect the call
- The original `<Dial>` from dialAll.php might complete at the same time
- When `<Dial>` completes, Twilio calls its action: `unAnsweredCall.php`
- This might happen BEFORE the redirect takes effect
- Or the redirect might conflict with the `<Dial>` action callback

**Timing Issue**:
```
Time 0ms:   Trainer clicks Accept
Time 10ms:  answerCall.php starts
Time 20ms:  answerCall.php sets OnCall = 1
Time 30ms:  answerCall.php calls $call->update(url: twilioRedirect.php)
Time 40ms:  Twilio receives update request
Time 50ms:  Original <Dial> from dialAll.php completes/fails
Time 50ms:  Twilio calls <Dial action='/unAnsweredCall.php'>
Time 60ms:  unAnsweredCall.php executes
Time 70ms:  Redirect to twilioRedirect.php tries to execute
            ↑ But call might already be ended by unAnsweredCall.php flow
```

**Hypothesis**: The `<Dial>` action callback to `unAnsweredCall.php` is interfering with or racing against the `$call->update()` redirect to `twilioRedirect.php`.

### Understanding Normal (Non-Training) Flow

**For comparison, when a NORMAL volunteer accepts a call**:

1. External call → dialAll.php generates:
   ```xml
   <Dial action='/unAnsweredCall.php'>
     <Client>Volunteer1</Client>
     <Client>Volunteer2</Client>
     <Client>TrainerUser</Client>  <!-- If trainer is logged in normally -->
   </Dial>
   ```

2. Volunteer clicks Accept → answerCall.php:
   - Sets OnCall = 1
   - Calls `$call->update(url: twilioRedirect.php)`

3. twilioRedirect.php returns:
   ```xml
   <Dial action='/answeredCallEnd.php'>
     <Conference startConferenceOnEnter='true' endConferenceOnExit='true'>VolunteerUsername</Conference>
   </Dial>
   ```

4. The `$call->update()` **replaces** the original `<Dial>` TwiML with the new conference TwiML
5. The original `<Dial action='/unAnsweredCall.php'>` is **discarded/cancelled**
6. Call connects to conference successfully
7. When call ends, Twilio calls `answeredCallEnd.php` (the action from the NEW TwiML)

**Key Question for Training Mode**:
- Why does the update work for normal volunteers but not for trainers in training mode?
- Is there something about training mode that prevents the `<Dial>` from being cancelled?
- Does the presence of an **existing** conference (training conference) cause Twilio to reject the update?

### Critical Difference: Training Conference Already Exists

**Normal Volunteer Flow**:
- Volunteer connects to Twilio when call arrives
- Conference is **created** when first participant joins (startConferenceOnEnter='true')
- External caller joins the NEW conference

**Training Mode Flow**:
- Trainer and trainees are **already connected** to a Twilio conference (named after trainer)
- That conference **already exists** before external call arrives
- External caller needs to join the **existing** conference

**Potential Issue**:
When `answerCall.php` calls `$call->update()`, it might be trying to:
1. Cancel the original `<Dial>` (which is ringing all volunteers)
2. Create a NEW `<Dial>` with the training conference

But if the training conference already has active participants, Twilio might:
- Reject the update as invalid
- Complete the original `<Dial>` instead (triggering unAnsweredCall.php)
- See the update as a conflict with the existing conference state

**Answer Found - Training Conference Creation**:

YES, the training conference DOES exist before external calls arrive.

**How It's Created** (trainingSessionUpdated.js lines 989-1044):
```javascript
connectConference() {
    const device = callMonitor.getDevice();
    const params = {
        conference: this.conferenceID || this.trainer.id,  // Conference named after trainer
        conferenceRole: isConferenceModerator ? 'moderator' : 'participant',
        startConferenceOnEnter: isConferenceModerator,    // TRUE for trainer
        endConferenceOnExit: isConferenceModerator,        // TRUE for trainer
        muted: shouldBeMuted
    };
    this.connection = device.connect(params);  // Creates Twilio conference
}
```

**Conference Details**:
- **Name**: Trainer's volunteerID (e.g., "TrainerUsername")
- **Created by**: Trainer calling `device.connect({conference: "TrainerUsername"})`
- **Moderator**: Trainer (startConferenceOnEnter=true, endConferenceOnExit=true)
- **Participants**: Trainees join same conference (startConferenceOnEnter=false, endConferenceOnExit=false)
- **State**: Conference is ACTIVE and IN-PROGRESS before external call arrives

**This Confirms the Hypothesis**:
When answerCall.php tries to add an external caller to this EXISTING conference via `$call->update()`, there may be a conflict because:
1. The conference already has active participants (trainer + trainees)
2. The original `<Dial>` from dialAll.php is still active and ringing
3. Twilio might reject the update or complete the `<Dial>` action callback first

**Need to Investigate**:

2. **What does Twilio do when you try to add a participant to an existing conference via call update?**
   - Is this supported?
   - Are there timing issues?

3. **Are there Twilio API errors in the logs when answerCall.php runs?**
   - Does `$call->update()` throw an exception?
   - Does it return a success but Twilio rejects it internally?

### Recommended Logging to Add

**In answerCall.php** (around line 130):
```php
error_log("answerCall.php: About to update call $CallSid for volunteer $VolunteerID");

// Check if volunteer is in training mode
$trainingCheck = "SELECT LoggedOn FROM Volunteers WHERE UserName = ?";
$trainingResult = dataQuery($trainingCheck, [$VolunteerID]);
$loggedOnStatus = $trainingResult[0]->LoggedOn ?? null;
error_log("answerCall.php: Volunteer LoggedOn status: $loggedOnStatus");

try {
    $call = $client->calls($CallSid)->fetch();
    error_log("answerCall.php: Current call status: " . $call->status);

    $call->update([
        "Url" => $WebAddress . "/twilioRedirect.php",
        "Method" => "POST"
    ]);

    error_log("answerCall.php: Call update completed successfully");
} catch (Exception $e) {
    error_log("answerCall.php: Call update FAILED: " . $e->getMessage());
    error_log("answerCall.php: Exception code: " . $e->getCode());
    throw $e; // Re-throw so we can see it failed
}
```

**In unAnsweredCall.php** (at the start):
```php
error_log("unAnsweredCall.php: Called for CallSid: $CallSid, CallStatus: $CallStatus");
error_log("unAnsweredCall.php: Category determined: $Category");
error_log("unAnsweredCall.php: Call duration: $Length seconds");
```

**In twilioRedirect.php** (at the start):
```php
error_log("twilioRedirect.php: Called for CallSid: $CallSid");
error_log("twilioRedirect.php: Volunteer: $Volunteer, Training mode: " . ($isTrainingMode ? 'YES' : 'NO'));

// Check if conference exists
try {
    $conferences = $client->conferences->read(["friendlyName" => $Volunteer, "status" => "in-progress"]);
    if (!empty($conferences)) {
        error_log("twilioRedirect.php: Conference '$Volunteer' EXISTS with " . count($conferences[0]->participants) . " participants");
    } else {
        error_log("twilioRedirect.php: Conference '$Volunteer' does NOT exist yet");
    }
} catch (Exception $e) {
    error_log("twilioRedirect.php: Could not check conference status: " . $e->getMessage());
}
```

This logging will show:
1. Whether answerCall.php completes successfully or throws an error
2. Whether unAnsweredCall.php is called BEFORE or AFTER twilioRedirect.php
3. Whether the training conference exists when twilioRedirect.php is called
4. The exact sequence of events and timing

## What Was Attempted

### Attempted Fix #1: answerCall.php (REVERTED)
**Theory**: Training mode volunteers shouldn't have their calls updated via Twilio API

**Implementation**: Added check to skip `$call->update()` for training mode users

**Result**: ❌ INCORRECT - This prevented the call from being routed at all. The `$call->update()` is necessary to trigger twilioRedirect.php.

**Action Taken**: Reverted changes to answerCall.php

---

### Attempted Fix #2: twilioRedirect.php (FAILED)
**Theory**: External callers joining training conference with `startConferenceOnEnter='true'` and `endConferenceOnExit='true'` creates moderator conflict

**Implementation**:
```php
// Lines 41, 87-89, 107-120
$isTrainingMode = false;
if ($loggedOnStatus == 4 || $loggedOnStatus == 6) {
    $isTrainingMode = true;
}

// Different TwiML for training mode
if ($isTrainingMode) {
    echo "<Conference startConferenceOnEnter='false' endConferenceOnExit='false'>$Volunteer</Conference>";
} else {
    echo "<Conference startConferenceOnEnter='true' endConferenceOnExit='true'>$Volunteer</Conference>";
}
```

**Result**: ❌ FAILED - Call still drops when trainer clicks Accept

**Conclusion**: The theory about conference moderator conflict was incorrect. The issue is elsewhere in the flow.

---

## Current State

### What We Know Works
1. ✅ External call rings to all volunteers (dialhotline.php/dialall.php)
2. ✅ Trainer sees incoming call UI
3. ✅ Trainer clicks "Accept" button
4. ✅ answerCall.php executes successfully:
   - Updates CallRouting table
   - Sets OnCall = 1
   - Calls `$call->update()` to redirect to twilioRedirect.php
5. ✅ twilioRedirect.php executes and returns TwiML
6. ✅ Training conference already exists (trainer + trainees connected)

### What Breaks
❌ **Call drops immediately after trainer clicks Accept**

### What We DON'T Know
- **When** exactly does the call drop?
  - During answerCall.php execution?
  - After twilioRedirect.php returns TwiML?
  - When Twilio attempts to add caller to conference?
  - After caller joins conference?

- **What** causes the drop?
  - Twilio API error?
  - Conference state issue?
  - TwiML parsing error?
  - JavaScript client-side issue?
  - Network/WebRTC issue?

- **Which** call drops?
  - External caller's call?
  - Trainer's conference connection?
  - Both?

## Files Modified (Current State)

```
twilioRedirect.php (lines 41, 87-89, 107-120)
  - Added training mode detection
  - Using different conference parameters for training mode
  - STATUS: Modified but didn't fix the issue

answerCall.php
  - STATUS: Reverted to original state (no modifications)

screenSharingControlMulti.js
  - STATUS: Modified for screen sharing fixes (unrelated to call drop)
```

## What Needs Investigation

### Server-Side
1. **answerCall.php logging**
   - Does it complete successfully?
   - Does `$call->update()` throw any errors?
   - Check Twilio API response

2. **twilioRedirect.php logging**
   - Is it being called?
   - What TwiML is being returned?
   - Log the conference name and parameters

3. **Twilio webhook logs**
   - Check Twilio console for webhook errors
   - Look for TwiML parsing errors
   - Check call status changes

4. **Conference state**
   - Is training conference actually running when external call arrives?
   - How was training conference created?
   - What are the trainer's conference parameters?

### Client-Side
1. **JavaScript call handling**
   - What happens in `trainingSession.startNewCall()`?
   - Does it call any Twilio methods that might disconnect?
   - Check for `device.disconnectAll()` or `connection.disconnect()` calls

2. **Twilio Voice SDK**
   - Are there event listeners that might trigger on call state change?
   - Check for `device.on('disconnect')` handlers
   - Look for automatic cleanup code

3. **Conference connection**
   - How did trainer connect to conference originally?
   - What parameters were used?
   - Is trainer's connection stable before external call arrives?

## Recommended Debug Steps

### Step 1: Add Comprehensive Logging

**answerCall.php** (add before and after `$call->update()`):
```php
error_log("answerCall.php: About to update call $CallSid to redirect to twilioRedirect.php");
error_log("answerCall.php: Volunteer: $VolunteerID, CallSid: $CallSid");

try {
    $call->update([...]);
    error_log("answerCall.php: Call update successful");
} catch (Exception $e) {
    error_log("answerCall.php: Call update FAILED: " . $e->getMessage());
    error_log("answerCall.php: Call update FAILED - Code: " . $e->getCode());
}
```

**twilioRedirect.php** (add at start and end):
```php
error_log("twilioRedirect.php: Called for CallSid: $CallSid, Volunteer: $Volunteer");
error_log("twilioRedirect.php: Training mode: " . ($isTrainingMode ? 'YES' : 'NO'));
error_log("twilioRedirect.php: Conference parameters - start: " . ($isTrainingMode ? 'false' : 'true') . ", end: " . ($isTrainingMode ? 'false' : 'true'));

// At the end, after generating TwiML
error_log("twilioRedirect.php: Returned TwiML for conference: $Volunteer");
```

**index.js** (in `callObject.answerCall` or wherever Accept is handled):
```javascript
console.log("Accept clicked for call:", this.callSid);
console.log("Training session active:", !!trainingSession);
console.log("Volunteer ID:", volunteerID);
console.log("Current device state:", callMonitor.getDevice().state);
```

### Step 2: Check Twilio Console

1. Go to Twilio Console → Phone Numbers → Active Calls
2. Find the call that dropped
3. Check call logs for:
   - Status changes (ringing → in-progress → completed/failed)
   - Webhook requests and responses
   - TwiML executed
   - Error messages

### Step 3: Monitor Network Traffic

1. Open browser DevTools → Network tab
2. Filter for XHR/Fetch requests
3. Watch for:
   - answerCall.php request/response
   - Any subsequent API calls
   - WebSocket/EventSource messages

### Step 4: Check Conference State

Add logging to see if conference exists and its state:
```php
// In twilioRedirect.php, before generating TwiML
$conferences = $client->conferences->read(["friendlyName" => $Volunteer]);
if (!empty($conferences)) {
    error_log("twilioRedirect.php: Conference '$Volunteer' exists, status: " . $conferences[0]->status);
    error_log("twilioRedirect.php: Conference participants: " . $conferences[0]->participantsCount);
} else {
    error_log("twilioRedirect.php: Conference '$Volunteer' does NOT exist yet!");
}
```

## Questions to Answer

1. **Does the external caller hear anything before disconnect?**
   - Ringing stops?
   - Brief moment of silence?
   - Conference music/hold music?
   - Error message?

2. **Does the trainer stay in the conference?**
   - Or does trainer also get disconnected?
   - Do trainees stay connected?

3. **What does the trainer see/hear?**
   - Call UI changes?
   - Error messages?
   - Audio continues or cuts out?

4. **What's the call duration?**
   - Does Twilio show 0 seconds?
   - A few seconds?
   - This tells us when the drop happens

5. **Is the training conference a Twilio conference?**
   - Or is it a WebRTC peer-to-peer connection?
   - How are trainer and trainees currently connected for voice?
   - This is CRITICAL to understand!

## Hypothesis to Test

### Hypothesis 1: Trainer Not Actually in Twilio Conference
**Theory**: The "training conference" might be WebRTC-based for screen sharing, but voice is handled differently. When we try to add external caller to a conference that doesn't exist, it fails.

**Test**: Check how trainer connects for voice calls
- Is it `device.connect({conference: "TrainerUsername"})`?
- Or is voice handled through WebRTC peer connections?
- Are trainer and trainees in a Twilio conference or WebRTC mesh?

### Hypothesis 2: Conference Exists But With Wrong Parameters
**Theory**: Training conference was created with incompatible parameters that prevent new participants from joining.

**Test**: Log the conference state and parameters when it's created
- What parameters does trainer use when starting training session?
- Are those compatible with adding external callers?

### Hypothesis 3: Client-Side Code Disconnects After Accept
**Theory**: JavaScript code is triggering a disconnect after the Accept button is clicked.

**Test**: Trace JavaScript execution after Accept is clicked
- Does `trainingSession.startNewCall()` call any disconnect methods?
- Are there event listeners that trigger on call state change?

### Hypothesis 4: Muting Logic Causes Disconnect
**Theory**: The code that tries to mute participants might be calling wrong methods that disconnect instead.

**Test**: Check muting implementation
- Does it use `connection.mute()` or something else?
- Could muting logic accidentally disconnect calls?

## Next Steps

1. **DO NOT make any more code changes** until flow is fully understood
2. **Add comprehensive logging** to trace exact execution flow
3. **Monitor Twilio console** during test to see call lifecycle
4. **Manually test** and collect all log output
5. **Document exact sequence** of what happens from Accept click to disconnect
6. **Identify exact point** where call drops
7. **Only THEN** design fix based on actual root cause

## Status Summary

- ❌ Issue NOT resolved
- ❌ Root cause NOT identified
- ❌ Flow NOT fully understood
- ✅ Two incorrect theories ruled out:
  1. Skipping call update (prevented routing)
  2. Conference moderator conflict (didn't fix issue)

**Requires manual investigation with comprehensive logging before attempting further fixes.**
