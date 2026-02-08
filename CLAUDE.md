# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a web-based volunteer crisis support system for LGBTQ+ helplines. The platform handles phone calls, chat support, volunteer management, and training through multiple integrated services.

**TEST ENVIRONMENT**: This is the test system at `/home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html/`
- Production is at `/home/1203785.cloudwaysapps.com/hrbnxbfdau/public_html/`
- GroupChat database is shared between test and production (intentional)

## Production Commands

### PHP Dependencies
```bash
composer install
```

### Node.js Dependencies
```bash
npm install
npm run build # Build for production
```

### Running Tests
```bash
npm test                    # Run all JavaScript tests (Jest)
npm run test:coverage       # Run with coverage report
npm run test:php            # Run PHP tests (PHPUnit)
npm run test:all            # Run all tests (JS + PHP)
```

### WebSocket Server
```bash
# WebSocket server managed by process manager in production
# Check status: pm2 list
# Restart if needed: pm2 restart websocket-server
```

## Version Control

Git repository initialized December 2025.

### What's Excluded (.gitignore)
- `vendor/` and `node_modules/` - Dependencies (install via composer/npm)
- `*.sql` and `database_backups*/` - Database files
- `*.mp4`, `*.mp3`, `*.wav`, `*.m4a` - Large media files
- `*.log` and `debug_*.txt` - Log files
- `CalendarNotes*` - Sensitive calendar notes
- `*.p8` - Apple MapKit credentials
- Legacy directories (`GroupChat-OLD/`, `trainingShare/`)

## Architecture

### Core Components
- **Admin/** - Administrative interface and volunteer management
- **chat/** - One-on-one crisis chat support system
- **GroupChat/** - Multi-user volunteer coordination chat
- **TrainingChat/** - Training environment with screen sharing
- **Calendar/** - Volunteer shift scheduling and management
- **Stats/** - Call and chat analytics dashboard
- **Audio/** - Voice prompts for phone system routing

### Technology Stack
- **Backend**: PHP with MySQL database
- **Real-time**: Ratchet WebSockets for chat (production managed), Twilio Voice SDK v2.x for telephony
- **Frontend**: Vanilla JavaScript, HTML/CSS
- **Key Dependencies**:
  - @twilio/voice-sdk v2.15.0+ (phone integration, migrated from legacy v1.15)
  - cboden/ratchet (WebSocket server)
  - phpmailer/phpmailer (email notifications)

### Database Architecture - Training System

**CRITICAL:** The training system uses distinct field types in the `volunteers` table:

#### **Permission Fields (Admin-Set, Persistent)**
- **`trainer`** - Permission flag: Can this user login as trainer? (1=yes, NULL=no)
- **`trainee`** - Permission flag: Can this user login as trainee? (1=yes, NULL=no)
- **Set by administrators, NEVER modified by login/logout processes**

#### **Session Status Fields (Login/Logout Managed)**
- **`LoggedOn`** - Current session status:
  - `0` = Logged out
  - `1` = Regular volunteer logged in
  - `2` = Full Admin logged in (stealth mode for GroupChat)
  - `4` = Trainer logged in
  - `5` = Resource Only volunteer (accesses resources, no calls/chats)
  - `6` = Trainee logged in
  - `7` = Admin Mini logged in (limited admin UI, no Blocked/GroupChat/Widget tabs)
  - `8` = Group Chat Monitor logged in (visible mode)
  - `9` = Resource Admin logged in (Resource Mini - updates resource database only)
  - `10` = Calendar Only (viewing/editing calendar, not taking calls/chats)
- **`TraineeID`** - Active trainer's assigned trainees (comma-separated list)
  - Example: `"TimTesting,JohnDoe,JaneSmith"` for trainer with 3 trainees
  - Cleared on logout, populated during trainer login based on selected trainees

#### **Role Detection Logic**
- **Login eligibility**: Check `trainer`/`trainee` permission fields
- **Current role**: Use `LoggedOn` status values (4=trainer, 6=trainee)
- **Trainer-trainee relationships**:
  - Use `TraineeID` field with `FIND_IN_SET(trainee_id, TraineeID)` queries
  - Supports multiple trainees per trainer via comma-separated values

### Database Configuration
Database connection settings are in `../private_html/db_login.php` (outside web root for security).

**CRITICAL - Volunteers Table Field Naming**:
- **`UserId`** (numeric, auto-increment) - Primary key, INTEGER field
- **`UserName`** (string, unique) - The volunteer's login username, VARCHAR field
- **IMPORTANT**: All other tables/systems use `userID` (string) which maps to `UserName` in Volunteers table, NOT `UserId`
- **Rule**: When querying Volunteers table with a userID from sessions/other tables, ALWAYS use `WHERE UserName = ?`, NEVER `WHERE UserID = ?`

**CRITICAL - GroupChat Moderator Authentication**:
- **Permission Field**: `groupChatMonitor` in Volunteers table (1=has permission, NULL/0=no permission)
- **Authentication Requirements**: User must have BOTH:
  1. `groupChatMonitor = 1` (permission to moderate)
  2. `LoggedOn = 2` (Admin - stealth mode) OR `LoggedOn = 8` (Group Chat Monitor - visible mode)
- **Moderator Types**:
  - `LoggedOn = 2` → `$_SESSION['Moderator'] = 2` (stealth admin, invisible to chatters)
  - `LoggedOn = 8` → `$_SESSION['Moderator'] = 1` (visible monitor, shown as "Moderator-Name")
- **Rule**: Users with `groupChatMonitor = 1` but other LoggedOn values (1, 4, 6, etc.) should NOT be granted moderator access
- **Database Functions**: GroupChat files use TWO database connections:
  - `dataQuery()` - Queries VCC database (Volunteers table for authentication)
  - `groupChatDataQuery()` - Queries GroupChat database (callers, transactions, groupChatRooms tables)

**CRITICAL SESSION CONFIGURATION**: The `db_login.php` file contains global session configuration that MUST be included BEFORE any `session_start()` calls:
```php
ini_set('session.gc_maxlifetime', '28800');  // 8 hours
ini_set('session.cookie_lifetime', '0');      // Browser session
```

### External Services
- **Twilio**: Production phone call routing and SMS (live account)
- **Apple MapKit**: Production location services for resource mapping
- **YouTube**: Training video integration

### Twilio Webhook Files

**CRITICAL**: These files are called directly by Twilio's servers (server-to-server requests). They do NOT have user sessions and must NEVER use `requireAuth()`, `session_start()`, or any session-based authentication.

| File | Purpose |
|------|---------|
| `dialHotline.php` | Initial incoming call handler - plays greeting, rings volunteers |
| `dialAll.php` | Secondary ring handler - rings all available volunteers |
| `unAnsweredCall.php` | Handles unanswered/abandoned calls |
| `twilioRedirect.php` | Routes answered calls to volunteer's conference |
| `answeredCallEnd.php` | Handles call completion/hangup |
| `trainingRouting.php` | Routes calls for training sessions |

**Pattern for Twilio webhooks:**
```php
<?php
require_once('../private_html/db_login.php');
// NO session_start() - Twilio has no user session
// NO requireAuth() - would always fail
// Process $_REQUEST data from Twilio directly
```

### Login Flow Calendar Check

**CRITICAL**: The login process includes a calendar check that happens BEFORE the user is fully authenticated. This is by design.

**Login Flow:**
1. User enters credentials on `login.php`
2. `logon.js` calls `loginverify2.php` with `Calendar=check` to verify password only (returns "OK" or "FAIL")
3. If password correct, `logon.js` calls `Calendar/getFutureVolunteerSchedule.php` to check if user is on calendar
4. Based on calendar status, user either logs in directly or selects end-of-shift time
5. Final login completes via `loginverify2.php`

**Files involved (DO NOT add requireAuth()):**

| File | Purpose |
|------|---------|
| `Calendar/getFutureVolunteerSchedule.php` | Returns schedule data during login flow - called BEFORE user is authenticated |

**Why no authentication on getFutureVolunteerSchedule.php:**
- Called during login BEFORE session is established
- Only returns non-sensitive schedule data (which volunteers are on calendar)
- Adding `requireAuth()` breaks the entire login process
- The password has already been verified in step 2 before this is called

## Key Files

### Critical Configuration
- `../private_html/db_login.php` - Database credentials AND session configuration
- `../private_html/.env` - Production environment variables
- `Twilio-Start-MySQL.txt` - Call routing initialization SQL

### Security Features
- Session-based volunteer authentication
- Caller blocking/moderation systems
- Browser detection for compatibility
- Separate public/private directory structure

## Production Notes

- WebSocket server runs as a managed service in production
- Twilio webhooks use HTTPS production URLs for phone integration
- MapKit uses production authentication tokens in `.p8` files
- HTTPS is required for WebRTC features (getUserMedia/getDisplayMedia)
- Multiple duplicate files exist - check timestamps when editing

## Staging Auto-Deploy

Staging (`vcctest.org`) auto-deploys from GitHub on push to `main`. Uses a two-part system because `exec()` is blocked in PHP-FPM by Imunify360.

### Architecture

| Component | File | Runs As | Purpose |
|-----------|------|---------|---------|
| Webhook | `api/github-webhook.php` | `dgqtkqjasj` (www-data) | Validates GitHub HMAC signature, writes `.deploy_trigger` |
| Cron job | `api/deploy-cron.sh` | `timtravelstead` (crontab) | Every minute: checks trigger, runs `git fetch` + `git reset --hard origin/main` + `git clean -fd` |

### Key Details

- **Deploy strategy**: `git reset --hard` (not `git pull`) so local changes never block deploys
- **Webhook secret**: Configured in GitHub repo Settings > Webhooks
- **Logs**: Webhook writes to `deploy.log`, cron writes to `deploy-cron.log` (different OS users can't share log files)
- **Concurrency**: `.deploy_lock` file prevents overlapping deploys (5-minute stale check)
- **Gitignored**: `.deploy_trigger`, `.deploy_lock`, `deploy.log`, `deploy-cron.log`
- **SSH access**: `ssh timtravelstead@vcctest.org`
- **Repo path**: `/home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html`
- **Known limitation**: Some locked files can't be cleaned by `git clean` (`archive_old_system/Signals/`, `testShare/vendor/`) — owned by `dgqtkqjasj` with sticky bit, harmless

### Manual Deploy (if webhook/cron fails)

```bash
ssh timtravelstead@vcctest.org "cd /home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html && git fetch origin main && git reset --hard origin/main"
```

## Real-Time Update System

### Current Mode: Legacy SSE (vccFeed.php)

The system supports two modes for real-time updates. Currently running in **legacy SSE mode** while Redis polling is being tested.

### Legacy SSE Mode (Active)
- **Endpoint**: `vccFeed.php` - Server-Sent Events stream
- **Connection**: Long-lived HTTP connection (~30 seconds per cycle)
- **Updates**: User list, IMs, chat invites, room status pushed as events

### Redis Polling Mode (Testing) 🔄

**Status**: Implementation complete, undergoing testing. Toggle with `togglePolling(true/false)` in browser console.

**Architecture**:
- **General polling**: `vccPoll.php` every 2.5 seconds
- **Fast ringing check**: `vccRinging.php` every 500ms
- **Cache layer**: Redis stores user list, reduces database queries
- **Fallback**: Automatic database fallback if Redis unavailable

**Key Files**:

| File | Purpose |
|------|---------|
| `vccPoll.php` | Main polling endpoint - returns user list, events, IMs, chat updates |
| `vccRinging.php` | Fast polling for incoming call detection |
| `lib/VCCFeedPublisher.php` | Redis cache manager with `refreshUserListCache()` |
| `initializeRedisCache.php` | CLI tool to initialize/refresh Redis cache |
| `index.js:4216-4530` | `vccPolling` object - client-side polling logic |

**Redis Keys**:
- `vcc:userlist` - Cached user list with timestamp
- `vcc:user:{userID}:events` - Per-user event queue
- `vcc:chat:{callerID}:messages` - Chat room messages
- `vcc:chat:{callerID}:typing` - Typing indicators

**To Enable for Testing**:
```javascript
// In browser console
togglePolling(true)   // Enable Redis polling
togglePolling(false)  // Return to legacy SSE
```

**To Initialize Redis Cache**:
```bash
php initializeRedisCache.php
```

### Database Cleanup (December 2025)

**Hotlines Table JOIN Removal**: Removed unused `JOIN Hotlines` from 6 files. The Hotlines table is a lookup table but no columns were being selected from it, causing unnecessary row multiplication when duplicate entries existed.

**Files cleaned**:
- `vccFeed.php`
- `vccPoll.php`
- `lib/VCCFeedPublisher.php`
- `index2.php`
- `Admin/index.php`
- `Admin/WelcomeList.php`

## Training System Architecture

### Directory Structure
- `trainingShare3/` - **CURRENT** Multi-trainee training system (PHP-based)
  - `screenSharingControlMulti.js` - Multi-peer WebRTC client
  - `trainingSessionUpdated.js` - Training session management
  - `signalSend.php` / `signalPollDB.php` - DB-backed signaling (send/poll)
  - `lib/TrainingDB.php` - Database abstraction layer
  - `lib/SignalQueue.php` - Atomic signal send/receive
- `trainingShare/` - Legacy 1:1 training system (preserved for reference)

### Key Architectural Principles
- **Pure PHP/JavaScript** - No Socket.IO dependency
- **Unified EventSource** - Screen sharing uses main `vccFeed.php` stream
- **Session locking prevention** - `session_write_close()` in all endpoints
- **Multi-trainee support** - 1 trainer with multiple trainees simultaneously
- **DB-backed signaling** - Atomic signal delivery via `training_signals` table, 500ms polling

### 🚨 CRITICAL: Training Call Separation

**MANDATORY READING**: Production file `/trainingShare3/CRITICAL_TRAINING_CALL_SEPARATION.md`

**Key Architectural Rule:**
- **Training conferences**: Use `this.connection.mute()` (TrainingSession class)
- **Regular calls**: Use `callMonitor.getActiveCall().mute()` (global system)
- **External calls**: Added to existing training conference (NOT new calls)

**⚠️ NEVER use `callMonitor.getActiveCall()` or `device.mute()` in training sessions!**

### Training Mute State Management

**Muting Rule**: `shouldBeMuted = currentlyOnCall && !isController`
- Normal training (no external call): Everyone UNMUTED
- External call active: Only controller UNMUTED, others MUTED

**Key Methods in `trainingSessionUpdated.js`:**
- `setExternalCallActive(isActive, source)` - Single entry point for call state changes
- `applyMuteState(reason)` - Central decision: mute if `currentlyOnCall && !isController`

**Race Condition Protection**: Both `startNewCall()` and `handleExternalCallStart()` verify active conference connection before muting, preventing incorrect muting when another volunteer grabs a call first.

### Twilio Conference API - FriendlyName vs SID

**CRITICAL**: Two identifiers that are NOT interchangeable:

| Identifier | Format | Used By |
|------------|--------|---------|
| **FriendlyName** | Any string (e.g., `"BradBecker99"`) | TwiML `<Conference>` noun |
| **Conference SID** | `CF` + 32 hex chars | REST API endpoints |

**The Rule:**
- **TwiML** (creating/joining): Use **FriendlyName**
- **REST API** (muting, listing participants): Use **Conference SID**

**Pattern:** If you have a FriendlyName, look up the SID first via `$client->conferences->read(['friendlyName' => $name, 'status' => 'in-progress'])`.

| Context | FriendlyName | Created By |
|---------|--------------|------------|
| Training sessions | Trainer's username | `trainingRouting.php` |
| Regular calls | Volunteer's username | `twilioRedirect.php` |

**Key Files:** `muteConferenceParticipants.php` (server mute), `trainingSessionUpdated.js` (client mute)

## Training System Test Suite

112 automated tests for `trainingShare3/` module: 88 Jest (JS) + 24 PHPUnit (PHP).

| Layer | Tests | Coverage |
|-------|-------|----------|
| JS Unit | 54 | Mute logic, control transfer, state transitions, WebRTC |
| JS Integration | 34 | Session lifecycle, control flows, database sync |
| PHP Unit | 24 | API endpoints, validation |

**Test Commands:**
```bash
npm test              # All JS tests
npm run test:php      # PHP tests
npm run test:all      # All tests
npm run test:coverage # Coverage report
```

**Key Test Files:** `tests/unit/js/TrainingSession.*.test.js` (mute, control, state)

## Session Management Architecture

### Critical Implementation Details

All PHP files in the system follow this pattern:

```php
<?php
// 1. Include db_login.php FIRST (sets session configuration)
require_once('../../../private_html/db_login.php');

// 2. Start session (inherits 8-hour timeout from db_login.php)
session_start();

// 3. Read any needed session data
$userId = $_SESSION['UserID'] ?? null;

// 4. Release session lock IMMEDIATELY
session_write_close();

// 5. Continue with database operations, output generation, etc.
```

### Why This Matters

**Session Locking**: PHP locks session files during `session_start()` until script ends OR `session_write_close()` is called. Without `session_write_close()`, concurrent requests from the same user BLOCK each other.

**Session Timeout**: The `db_login.php` file sets `session.gc_maxlifetime = 28800` (8 hours). This MUST be set BEFORE `session_start()` to take effect.

### Files Requiring This Pattern

All entry points and Ajax endpoints, including but not limited to:
- `vccFeed.php` - EventSource stream
- `index2.php` - Main volunteer interface
- `Admin/Search/*.php` - All resource admin endpoints
- `Calendar/*.php` - All calendar endpoints
- `chat/*.php` - All chat endpoints

## Coding Standards and Best Practices

### Database Connection Includes

**CRITICAL**: ALWAYS use `require_once()` when including `db_login.php`, NEVER use `include()` or `include_once()`.

**Why**: Using `include()` can cause `db_login.php` to be loaded multiple times when files are included by other files, resulting in fatal "Cannot redeclare function" errors. The `require_once()` function ensures the file is only loaded once per request, regardless of how many times it's referenced.

**Correct Usage**:
```php
// CORRECT - Use require_once for db_login.php
require_once('../private_html/db_login.php');
require_once('../../private_html/db_login.php');
require_once('../../../private_html/db_login.php');
```

**Incorrect Usage**:
```php
// WRONG - Will cause function redeclaration errors
include('../private_html/db_login.php');
include_once('../private_html/db_login.php');
```

## Browser Exit Handling

### Exit Methods

| Location | Exit Button | Browser Close/Refresh |
|----------|-------------|----------------------|
| Main Console (`index.js`) | `exitProgram(intentional=true)` | Heartbeat stops → auto-logout in 2 min |
| Calendar (`calendar.js`) | XHR `exitCalendar` | `sendBeacon` with `exitCalendar` |
| Admin Calendar (`adminCalendar.js`) | XHR `exitCalendar` | `sendBeacon` with `exitCalendar` |

### Heartbeat System

The main console uses a heartbeat system to detect inactive users:

- **Client**: Sends `heartbeat` POST to `volunteerPosts.php` every 30 seconds
- **Server**: Updates `LastHeartbeat` timestamp in volunteers table
- **Cleanup**: Each heartbeat also cleans up stale users (no heartbeat in 2+ minutes)
- **Database**: `volunteers.LastHeartbeat` column (DATETIME)

**Behavior:**
- Page refresh: User stays logged in (heartbeat continues after reload)
- Exit button: Immediate logout (`intentional=true` triggers full cleanup)
- Browser close: User auto-logged out after 2 minutes (no more heartbeats)
- Network disconnect: Same as browser close

### Key Implementation Details

- **Main Console**: `sendBeacon` on `beforeunload` is kept for logging but does NOT change LoggedOn
- **Calendar Pages**: Still use `sendBeacon` with `exitCalendar` for immediate logout
- **Exit Buttons**: Disable `onbeforeunload` handler and stop heartbeat before redirect
- **`exitCalendar` endpoint**: Only resets `LoggedOn` from 10 to 0 (Calendar Only users)

### Why Heartbeat Instead of sendBeacon?

`sendBeacon` fires on ALL page unloads including refresh, making it impossible to distinguish between:
- User refreshing the page (should stay logged in)
- User closing the browser (should log out)

The heartbeat system solves this by only logging out users who stop sending heartbeats.

## Troubleshooting

### Session-Related Issues

**Problem**: "Unable to connect to server" errors with multiple tabs open
- **Solution**: Verify all PHP files call `session_write_close()` after reading session data
- **Check**: Look for scripts that read session but don't release the lock

**Problem**: "Unauthorized access" errors after 20-30 minutes
- **Solution**: Verify `db_login.php` is included BEFORE `session_start()` in all entry points
- **Check**: Confirm `session.gc_maxlifetime` is set to 28800 (8 hours)

### Training System Issues

**Problem**: Search/Ajax requests don't work in trainer mode
- **Solution**: Ensure `session_write_close()` is called in all long-running PHP scripts
- **Check**: Look for `while(true)` loops without session closure

**Problem**: Screen sharing doesn't start automatically for trainers
- **Solution**: Ensure HTTPS is properly configured, check browser console for errors
- **Debug**: Look for "Auto-starting screen share" in console logs

**Problem**: Trainee incorrectly muted when no external call active
- **Solution**: Check `currentlyOnCall` state and `isController` values in console
- **Debug**: Look for `📞 [NOTIFICATION]` and `🔊 [MUTE_DECISION]` console logs

### General Production Issues

**Problem**: Real-time features not working
- **Solution**: Check `vccFeed.php` accessibility, session authentication, and SSL configuration
- **Debug**: Monitor browser Network tab for EventSource status

**Problem**: Database connection errors
- **Solution**: Check database server status and network connectivity
- **Check**: Verify production credentials in `../private_html/db_login.php`

**Problem**: PHP-FPM process exhaustion (AJAX timeouts system-wide)
- **Solution**: Check for orphaned EventSource processes with `ps aux | grep php`
- **Backup**: Cron script `/private_html/cleanup_stale_php.sh` runs every 5 minutes

## Production Debugging

- Always create custom exclusive debug logs for production troubleshooting
- Monitor system resources with `pm2 monit` and server logs
- Check SSL certificate validity for WebRTC and EventSource connections
- Verify database connection pools and limits in production environment
- Use production-safe logging that doesn't expose sensitive information

## Additional Database Tables

### ResourceYouthBlock
Flags resources that should not be shown to youth callers:
```sql
CREATE TABLE ResourceYouthBlock (
    IDNUM INT PRIMARY KEY,
    YouthBlock TINYINT(1) DEFAULT 0
);
```

### CallerHistory Index
Performance index for call history queries:
```sql
CREATE INDEX idx_date_time ON CallerHistory(Date DESC, Time DESC);
```

## Current Work

*This section tracks work-in-progress. For detailed session logs, see `SESSION_LOG.md`.*

### Active Tasks
- **Redis Polling Mode Testing**: User list display working. Need to test call/chat state updates.
- **Screen Reader Accessibility**: Phases 1-5 complete, Phases 6-8 remaining. See `SCREEN_READER_ACCESSIBILITY_PLAN.md`.

### Recently Completed
- `479f403` - Remove legacy file-based signaling; fix exit flow gaps (see details below)
- `c9eaae7` - Sync exit and heartbeat fixes from production
- `301a650` - Add CallSid flow logging (📋 [CALLSID] prefix for filtering)
- `63d0cf1` - Fix CallSid capture gap (check call.sid first in Twilio SDK v2)
- `9817687` - Remove fallbacks, add critical error logging (no silent failures)
- `70cb87f` - Fix excessive muting (state tracking, removed bulk ops)
- `c34949c` - Training System Test Suite (112 tests)

## Training System Redesign (January-February 2025)

Fundamental redesign of `trainingShare3` module to fix stability issues. Completed in two phases: DB signaling implementation (January) and legacy removal + exit flow fixes (February).

### Problems Fixed
| Problem | Solution |
|---------|----------|
| File-based signaling race conditions | Database-backed atomic signaling |
| 1-second polling latency | 500ms polling for WebRTC signals |
| Call drop on trainer Accept | Training detection in `unAnsweredCall.php` |
| 3-tier muting inconsistencies | Server-authoritative mute state |
| 30+ state variables | Formal 5-state machine |
| Inconsistent exit flows | All 3 paths (exit button, admin, heartbeat) fully synchronized |
| Orphaned trainees on trainer browser close | Heartbeat cleanup now cascade-logouts trainees |
| Training table accumulation | `cleanupSession()`/`removeParticipant()` in all exit paths |

### Database Tables

```sql
-- Run migration: php trainingShare3/migrations/run_migration.php
training_rooms           -- Active training sessions
training_participants    -- Who's in each room
training_signals         -- WebRTC signaling
training_session_state   -- State machine (INITIALIZING→CONNECTED→ON_CALL→RECONNECTING→DISCONNECTED)
training_session_control -- Who currently has control (trainer_id, active_controller, controller_role)
training_mute_state      -- Server-authoritative mute tracking
training_events_log      -- Debugging/audit trail
```

### PHP Endpoints

| Endpoint | Purpose |
|----------|---------|
| `signalSend.php` | Send signals to DB (WebRTC, control-change, conference-restart, etc.) |
| `signalPollDB.php` | Poll for signals (500ms) |
| `roomJoin.php` | Join training room |
| `roomLeave.php` | Leave training room |
| `setTrainingControl.php` | Transfer control (updates training_session_control + CallControl) |
| `getTrainingControl.php` | Get current control state |
| `setMuteState.php` | Set mute (DB + Twilio API) |
| `getMuteState.php` | Get mute states |
| `bulkMute.php` | Mute all non-controllers |
| `muteConferenceParticipants.php` | Server-side Twilio REST API muting |

### Library Files

| File | Purpose |
|------|---------|
| `trainingShare3/lib/TrainingDB.php` | Database abstraction (rooms, participants, state, cleanup) |
| `trainingShare3/lib/SignalQueue.php` | Atomic signal send/receive/broadcast |

**CRITICAL**: `SignalQueue.php` depends on `TrainingDB.php` (calls `TrainingDB::getSessionVersion()`) but does NOT require it. Callers must `require_once` both files.

### Call Drop Fix

**Critical fix in `unAnsweredCall.php`**: When trainer/trainee answers a call, `answerCall.php` redirects it via Twilio API. However, Twilio's `<Dial>` action still fires `unAnsweredCall.php` as a callback. The fix detects training mode (LoggedOn 4 or 6) and returns empty `<Response>` instead of marking the call as unanswered.

### Signaling System

DB-backed signaling is the sole signaling system. Legacy file-based signaling was fully removed in February 2025.

**Signal types handled in `signalSend.php`:**
- `offer`, `answer`, `ice-candidate` — WebRTC negotiation (direct to recipient)
- `control-change` — Broadcast via `SignalQueue::broadcastControlChange()` (sends `newController` field)
- `conference-restart` — Broadcast via `broadcastToRoom()` with full data passthrough (`activeController`, `newConferenceId`)
- `control-request` — Direct message from trainee to trainer (uses default case)
- `screen-share-start`, `screen-share-stop`, `call-start`, `call-end` — Broadcast events
- `leave-room` — Removes participant, broadcasts departure

**Exit signals** (`trainer-exited`, `trainee-exited`) are sent from PHP exit paths via `SignalQueue::sendToParticipant()`, NOT through `signalSend.php`.

### Training Exit Flow Architecture

All three exit paths are fully synchronized:

| Path | Trigger | Files |
|------|---------|-------|
| Exit button | User clicks exit | `volunteerPosts.php` case `exitProgram` |
| Admin force-exit | Admin logs out user | `Admin/ExitProgram.php` |
| Browser close | Heartbeat stops for 2+ min | `volunteerPosts.php` case `heartbeat` |

**Trainer exit** (all 3 paths):
1. Delete `training_session_control` record
2. Cascade-logout each trainee: volunteerlog, `LoggedOn=0`, CallControl delete, IM delete
3. Send `trainer-exited` DB signal to each trainee
4. Publish Redis logout events for all trainees
5. `TrainingDB::cleanupSession()` — closes room, deletes participants, clears state/mute

**Trainee exit** (all 3 paths):
1. Check if trainee had control → transfer back to trainer + re-add trainer to CallControl
2. Send `trainee-exited` DB signal with `controlReturnedToTrainer` flag
3. `TrainingDB::removeParticipant()` — removes trainee from training_participants

**Browser-side handlers** (`trainingSessionUpdated.js`):
- `handleTrainerExited()` — shows alert, redirects to `/login.php` after 3 seconds
- `handleTraineeExited()` — removes from list, updates control state if `controlReturnedToTrainer`, shows alert
- `trainerSignedOff()` — fallback safety net (detects trainer offline via user list, exits after 10s delay)
