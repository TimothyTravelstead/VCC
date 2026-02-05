# Training System Testing Plan: Calls and Chats

**Date Created:** 2026-01-29
**Purpose:** Comprehensive testing of training mode call and chat functionality

---

## Prerequisites

- [ ] Trainer logged in (LoggedOn = 4)
- [ ] Trainee logged in (LoggedOn = 6)
- [ ] Screen sharing working between trainer and trainee
- [ ] Both can hear each other in the Twilio conference
- [ ] Browser console open on both machines with "Preserve Log" enabled

---

## Part 1: Basic Call Handling

### Test 1.1: Trainer Accepts Incoming Call
**Setup:** Trainer has control, no active external call

1. [ ] Call the hotline from an external phone
2. [ ] Verify call appears in trainer's interface
3. [ ] Trainer clicks to accept the call
4. [ ] **Expected Results:**
   - [ ] Call connects successfully
   - [ ] Trainer can hear caller
   - [ ] Caller can hear trainer
   - [ ] Trainee is MUTED (cannot hear caller, caller cannot hear trainee)
   - [ ] Console shows: `📞 External call STARTED`
   - [ ] Console shows mute state changes

### Test 1.2: Trainer Ends Call
**Setup:** Continue from Test 1.1

1. [ ] Trainer ends the call
2. [ ] **Expected Results:**
   - [ ] Call disconnects cleanly
   - [ ] Trainee is UNMUTED
   - [ ] Console shows: `📞 External call ENDED`
   - [ ] Training conference audio restored (trainer/trainee can talk)

### Test 1.3: Caller Hangs Up
**Setup:** Start a new call, trainer accepts

1. [ ] Have caller hang up the phone
2. [ ] **Expected Results:**
   - [ ] System detects call ended
   - [ ] Trainee is UNMUTED
   - [ ] No orphaned call state

---

## Part 2: Control Transfer with Active Call

### Test 2.1: Transfer Control to Trainee (No Active Call)
**Setup:** Trainer has control, no external call

1. [ ] Trainer transfers control to trainee via control panel
2. [ ] **Expected Results:**
   - [ ] Control state updates in both consoles
   - [ ] Trainee's screen is now shared to trainer
   - [ ] Both remain unmuted (no external call)

### Test 2.2: Trainee Accepts Call (When Trainee Has Control)
**Setup:** Trainee has control

1. [ ] Call the hotline
2. [ ] Trainee accepts the call
3. [ ] **Expected Results:**
   - [ ] Call connects to trainee
   - [ ] Trainee can hear caller
   - [ ] Trainer is MUTED
   - [ ] Console shows appropriate mute state

### Test 2.3: Transfer Control During Active Call
**Setup:** Trainee has control and is on a call

1. [ ] Transfer control back to trainer while call is active
2. [ ] **Expected Results:**
   - [ ] Control transfers successfully
   - [ ] Trainer becomes unmuted (now controller)
   - [ ] Trainee becomes muted (no longer controller)
   - [ ] Call remains connected
   - [ ] Caller experiences no interruption

---

## Part 3: Call Drop Prevention

### Test 3.1: Trainer Grabs Call (Call Drop Fix)
**Setup:** Trainer has control, call comes in

1. [ ] Call the hotline
2. [ ] Let it ring to both trainer and trainee
3. [ ] Trainer accepts the call
4. [ ] **Expected Results:**
   - [ ] Call connects to trainer's conference
   - [ ] Call is NOT marked as unanswered
   - [ ] No duplicate call handling
   - [ ] Check server logs: should see training mode detection in unAnsweredCall.php

### Test 3.2: Another Volunteer Grabs Call First
**Setup:** Have another (non-training) volunteer logged in

1. [ ] Call the hotline
2. [ ] Have the other volunteer accept first
3. [ ] **Expected Results:**
   - [ ] Call goes to other volunteer
   - [ ] Training session is unaffected
   - [ ] No incorrect muting in training session

---

## Part 4: Chat Functionality

### Test 4.1: Training Chat Window
**Setup:** Both trainer and trainee logged in

1. [ ] Verify training chat window opened automatically
2. [ ] Trainer sends a message
3. [ ] Trainee sends a message
4. [ ] **Expected Results:**
   - [ ] Messages appear in both windows
   - [ ] Timestamps are correct
   - [ ] No duplicate messages

### Test 4.2: Chat with External Caller
**Setup:** Trainer has control, accepts a chat (if available)

1. [ ] Accept an incoming chat request
2. [ ] Send messages to caller
3. [ ] **Expected Results:**
   - [ ] Chat connects properly
   - [ ] Trainee can see the chat (view trainer's screen)
   - [ ] Only trainer (controller) can respond

### Test 4.3: Transfer Control During Chat
**Setup:** Active chat with trainer having control

1. [ ] Transfer control to trainee
2. [ ] **Expected Results:**
   - [ ] Chat remains connected
   - [ ] Trainee can now respond to chat
   - [ ] Trainer can observe via screen share

---

## Part 5: Edge Cases and Error Handling

### Test 5.1: Trainee Refreshes Browser During Call
**Setup:** Active call with trainer as controller

1. [ ] Trainee refreshes their browser
2. [ ] **Expected Results:**
   - [ ] Call remains connected for trainer
   - [ ] Trainee rejoins training session
   - [ ] Screen sharing re-establishes
   - [ ] Mute states are correct after rejoin

### Test 5.2: Trainer Refreshes Browser During Call
**Setup:** Active call with trainer as controller

1. [ ] Trainer refreshes their browser
2. [ ] **Expected Results:**
   - [ ] Document what happens (may need improvement)
   - [ ] Note any call drops or issues

### Test 5.3: Network Interruption
**Setup:** Active training session

1. [ ] Briefly disconnect network on trainee's machine
2. [ ] Reconnect after 5 seconds
3. [ ] **Expected Results:**
   - [ ] WebRTC reconnection should occur
   - [ ] Screen sharing should recover
   - [ ] Check console for reconnection logs

### Test 5.4: Multiple Rapid Control Transfers
**Setup:** No active call

1. [ ] Rapidly transfer control back and forth 5 times
2. [ ] **Expected Results:**
   - [ ] System handles rapid changes
   - [ ] Final state is correct
   - [ ] No stuck states

---

## Part 6: Mute State Verification

### Test 6.1: Verify Server-Side Mute
**Setup:** Active call with trainee muted

1. [ ] Check Twilio console to verify participant is actually muted
2. [ ] **Expected Results:**
   - [ ] Twilio shows trainee as muted
   - [ ] Client state matches server state

### Test 6.2: Client-Side Mute Indicator
**Setup:** Various mute states

1. [ ] Verify UI shows correct mute status
2. [ ] **Expected Results:**
   - [ ] Visual indicator matches actual mute state

---

## Console Log Patterns to Watch For

### Good Signs:
- `📞 External call STARTED` / `ENDED`
- `✅ WebRTC connected`
- `🎬 Remote video started playing successfully`
- `📋 [CALLSID] Accept handler` with valid CallSid

### Warning Signs:
- `🚨 [CRITICAL]` - Any critical errors
- `InvalidStateError` - WebRTC state issues
- Repeated reconnection attempts
- Mute operations with null CallSid (after connection established)

---

## Test Results Log

| Test | Date | Result | Notes |
|------|------|--------|-------|
| 1.1  |      |        |       |
| 1.2  |      |        |       |
| 1.3  |      |        |       |
| 2.1  |      |        |       |
| 2.2  |      |        |       |
| 2.3  |      |        |       |
| 3.1  |      |        |       |
| 3.2  |      |        |       |
| 4.1  |      |        |       |
| 4.2  |      |        |       |
| 4.3  |      |        |       |
| 5.1  |      |        |       |
| 5.2  |      |        |       |
| 5.3  |      |        |       |
| 5.4  |      |        |       |
| 6.1  |      |        |       |
| 6.2  |      |        |       |

---

## Issues Found

*(Document any issues discovered during testing)*

1.
2.
3.

---

## Files Modified This Session (2026-01-29)

1. **screenSharingControlMulti.js**
   - ICE candidate queuing (fixes timing errors)
   - Fixed poster.png path
   - Hidden local video preview windows
   - Added `applyPendingIceCandidates()` method

2. **trainingSessionUpdated.js**
   - Fixed CallSid NULL check (distinguishes init vs bug)

3. **index2.php**
   - Updated cache buster versions
