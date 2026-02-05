# Training Session Call Handling Fixes - January 2025

## Problem Summary

When a trainee with control received an external call, the system filled the console with 400 and 500 errors from:
- `notifyCallStart.php`
- `notifyCallEnd.php`
- `endConference.php`

Additionally, when a trainer with control accepted a call, the call would end immediately.

## Root Causes

### 1. Missing Trainer ID Lookup
**Issue**: Trainees don't always have the `trainerID` hidden field populated in `index2.php`
- The field only gets populated if the trainer has added the trainee to their `TraineeID` list
- If the relationship isn't set up, `$trainerID` is `null` (becomes empty string)
- Notification endpoints required `trainerId` but received empty strings → **400 errors**

### 2. Database Access Bug
**Issue**: Code used array syntax on object results from `dataQuery()`
```php
// WRONG:
$traineeIds = explode(',', $result[0]['TraineeID']);

// CORRECT:
$traineeIds = explode(',', $result[0]->TraineeID);
```
This caused fatal errors → **500 errors**

### 3. Missing Session Lock Release
**Issue**: Endpoints didn't call `session_write_close()` after reading session data
- Could cause session locking and blocking concurrent requests
- Not the primary cause but contributed to reliability issues

### 4. Missing Signals Directory
**Issue**: Code assumed `/Signals/` directory exists
- `file_put_contents()` would fail silently if directory doesn't exist
- No error checking on write operations

### 5. Insufficient Error Logging
**Issue**: Catch blocks didn't log full exception details
- Hard to diagnose production issues
- No stack traces or file/line information

## Fixes Applied

### notifyCallStart.php ✅
1. **Automatic trainer lookup**: If `trainerId` is empty, look it up from `callerId`
   - Query: `SELECT UserName FROM volunteers WHERE FIND_IN_SET(?, TraineeID) > 0`
   - Falls back to using `callerId` if no trainer found (trainee is actually a trainer)

2. **Fixed database access**: Changed `$result[0]['TraineeID']` to `$result[0]->TraineeID`

3. **Added session management**: `session_start()` + `session_write_close()`

4. **Directory creation**: Auto-creates `/Signals/` directory if missing

5. **Enhanced logging**:
   - Logs all incoming parameters
   - Logs participant lists before/after filtering
   - Logs each notification write operation
   - Full exception details in catch blocks

6. **Write error handling**: Checks `file_put_contents()` return value

### notifyCallEnd.php ✅
Applied all the same fixes as `notifyCallStart.php`

### endConference.php ✅
1. **Automatic conference ID lookup**: Same pattern as notification endpoints
2. **Added session management**: `session_start()` + `session_write_close()`
3. **Enhanced logging**: Logs all parameters and operations

### trainingSessionUpdated.js ✅
1. **Always send caller context**:
```javascript
const requestBody = {
    trainerId: notifyTrainerId || '', // Can be empty - server will look it up
    activeController: this.activeController,
    callerId: this.volunteerID, // Always provide
    callerRole: this.role // Always provide
};
```

2. **Better error logging**:
   - Logs request body before sending
   - Logs full error text from failed responses
   - Shows which endpoint failed

## How It Works Now

### Trainee with Control Receives Call
1. JavaScript: `notifyCallStart()` sends `{callerId: "TraineeUsername", trainerId: "", callerRole: "trainee"}`
2. PHP: Receives empty `trainerId`, triggers lookup
3. PHP: `WHERE FIND_IN_SET('TraineeUsername', TraineeID) > 0`
4. PHP: Finds trainer, uses their ID to get participant list
5. PHP: Filters out active controller (the trainee)
6. PHP: Notifies all others to mute
7. Returns success with participant list

### Trainer with Control Receives Call
1. JavaScript: `notifyCallStart()` sends `{callerId: "TrainerUsername", trainerId: "TrainerUsername", callerRole: "trainer"}`
2. PHP: Has valid `trainerId`, skips lookup
3. PHP: Gets trainee list from trainer's `TraineeID` field
4. PHP: Filters out active controller (the trainer)
5. PHP: Notifies trainees to mute
6. Returns success

## Error Prevention

### 400 Errors
- ✅ Empty `trainerId` → Auto-lookup from `callerId`
- ✅ Empty `conferenceId` → Auto-lookup from `callerId`
- ✅ Meaningful error messages if lookup fails

### 500 Errors
- ✅ Array/object access mismatch → Fixed to use object properties
- ✅ Database query failures → Wrapped in try-catch
- ✅ File write failures → Checked and logged
- ✅ Missing directories → Auto-created
- ✅ All exceptions logged with stack traces

## Remaining Issue: "Call Ends Immediately"

**Status**: Not yet diagnosed

**Symptom**: When trainer with control accepts an external call, it ends immediately

**Possible Causes**:
1. Conference connection conflict (trainer already in conference)
2. Call routing issue in Twilio
3. Muting logic error causing call drop
4. External call not being properly joined to conference

**Next Steps**:
1. Add detailed logging to `startNewCall()` and `connectConference()`
2. Check Twilio dashboard for call disconnect reasons
3. Verify conference participant states during call
4. Review `twilioRedirect.php` for training session routing

## Testing Checklist

- [ ] Trainee with control receives external call (no errors)
- [ ] Trainer with control receives external call (no errors, call stays connected)
- [ ] Notifications sent to correct participants
- [ ] Active controller excluded from mute notifications
- [ ] All participants unmuted after call ends
- [ ] Conference restarted correctly after external call
- [ ] Error logs show detailed information if failures occur

## Files Modified

```
trainingShare3/notifyCallStart.php
trainingShare3/notifyCallEnd.php
trainingShare3/endConference.php
trainingShare3/trainingSessionUpdated.js
```

## Log Monitoring

Check error logs for these patterns:

**Success**:
```
INFO: notifyCallStart called - trainerId: X, activeController: Y, callerId: Z
INFO: Participants after filtering (will be notified): A, B, C
INFO: Successfully notified 3 participants of external call start
```

**Failure**:
```
ERROR: Trainer ID required or could not be determined
CRITICAL ERROR in notifyCallStart: [exception message]
Stack trace: [full trace]
```
