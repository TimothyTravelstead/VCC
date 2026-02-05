# GroupChat Moderator Authentication Fix

**Date**: January 2025
**Issue**: Moderators were unable to access GroupChat rooms - system immediately reset selector and cleared iframe after room selection

## Problem Summary

When Group Chat Monitors (LoggedOn = 8) selected a room from the admin interface, the following sequence occurred:

1. Room selector changed to selected room
2. Iframe loaded with GroupChat interface
3. **Selector immediately reset to "None"**
4. **Iframe cleared (blank)**
5. Moderator kicked out before being able to sign in

### Root Causes

Multiple interconnected issues caused this problem:

#### 1. Database Field Name Mismatch
**File**: `GroupChat/index.php`, `GroupChat/Admin/moderatorSignOn.php`

**Problem**: Authentication queries were using wrong field name
```php
// WRONG - querying numeric primary key
WHERE UserID = ?

// CORRECT - querying string username field
WHERE UserName = ?
```

**Impact**: Moderator authentication failed because queries couldn't find user records

#### 2. Status Being Reset to 0
**File**: `GroupChat/index.php` line 212

**Problem**: When stealth admin moderators (LoggedOn=2) loaded the page, existing caller record was updated with `status = 0`
```php
// WRONG - resets status to 0 on page load
ON DUPLICATE KEY UPDATE moderator = 2, status = 0, modified = now()

// CORRECT - preserves status from moderatorSignOn.php
ON DUPLICATE KEY UPDATE moderator = 2, modified = now()
```

**Impact**: Database trigger created "signoff" transaction when status changed from 1→0, causing JavaScript to log off the moderator

#### 3. roomClosed() Resetting Parent Selector
**File**: `GroupChat/groupChat.js` lines 998-1020

**Problem**: When `roomClosed()` was called for moderators, it reset the parent selector and cleared iframe
```javascript
// OLD CODE - kicked moderators out
if(groupChatModeratorFlag === "0" || !groupChatModeratorFlag) {
    // Show "Room Closed" message
} else {
    // User IS a moderator - but still reset selector and clear iframe!
    groupChatMonitor1RoomSelector.selectedIndex = 0;
    groupChatAdminFrame1.src = "";
}
```

**Impact**: Even when moderator was properly authenticated, calling `roomClosed()` would kick them out

#### 4. Function Namespace Collision
**File**: `GroupChat/Admin/moderatorRoomList.php`, `GroupChat/Admin/includeFormatGroupChatEmail.php`

**Problem**: Files calling `dataQuery()` instead of `groupChatDataQuery()` for GroupChat database queries

**Impact**: Fatal errors when moderators tried to exit rooms

#### 5. Moderator Counting Logic
**File**: `GroupChat/callerSignOff.php` lines 77-78, 88

**Problem**: Only counted visible moderators (moderator=1), not stealth admins (moderator=2)

**Impact**: Email transcript sent when stealth admin signed off even though visible moderators were still in room

## Files Modified

### Authentication Fixes

1. **`GroupChat/index.php`** (lines 35, 151, 212)
   - Changed `WHERE UserID = ?` → `WHERE UserName = ?`
   - Removed `status = 0` from ON DUPLICATE KEY UPDATE for moderators

2. **`GroupChat/Admin/moderatorSignOn.php`** (lines 21)
   - Changed `WHERE UserID = ?` → `WHERE UserName = ?`

3. **`GroupChat/Admin/moderatorRoomList.php`** (line 21)
   - Changed `dataQuery()` → `groupChatDataQuery()`

### JavaScript Fix

4. **`GroupChat/groupChat.js`** (lines 1008-1022)
   - Removed selector reset and iframe clearing for moderators in `roomClosed()` function
   - Moderators now stay connected even when room is administratively closed

### Email/Cleanup Fixes

5. **`GroupChat/callerSignOff.php`** (lines 78, 88)
   - Updated moderator checks to include both `moderator = 1` AND `moderator = 2`
   - Email now only sent when LAST moderator (of any type) signs off

6. **`GroupChat/Admin/includeFormatGroupChatEmail.php`** (all instances)
   - Changed `dataQuery()` → `groupChatDataQuery()`

## Database Architecture Notes

### Critical Field Naming Convention

**Volunteers Table** (VCC Database - hrbnxbfdau):
- `UserId` (INTEGER, auto-increment) - Numeric primary key
- `UserName` (VARCHAR, unique) - Login username (e.g., "Travelstead")

**All Other Tables** use `userID` (string) which maps to `UserName` in Volunteers table.

**Rule**: When querying Volunteers table with userID from sessions/other tables:
```php
// CORRECT
$query = "SELECT ... FROM Volunteers WHERE UserName = ?";

// WRONG
$query = "SELECT ... FROM Volunteers WHERE UserID = ?";
```

### Database Function Convention

**GroupChat files use TWO database connections**:

1. **`dataQuery()`** - VCC database (hrbnxbfdau)
   - Use for: Volunteers table queries (authentication, permissions)

2. **`groupChatDataQuery()`** - GroupChat database (vnupbhcntm)
   - Use for: callers, transactions, groupChatRooms, groupChat tables

### LoggedOn Values

- `0` = Logged out
- `1` = Regular volunteer
- `2` = Admin (stealth mode for GroupChat moderators)
- `4` = Trainer
- `6` = Trainee
- `8` = Group Chat Monitor (visible mode)

### Moderator Types

**Volunteers Table**:
- `groupChatMonitor = 1` - Permission to moderate GroupChat

**Callers Table** (moderator field):
- `moderator = 1` - Visible moderator (LoggedOn=8, shows as "Moderator-Name")
- `moderator = 2` - Stealth admin (LoggedOn=2, invisible to chatters)

**Authentication Requirements**: User must have BOTH:
1. `groupChatMonitor = 1` (permission)
2. `LoggedOn = 2` (Admin) OR `LoggedOn = 8` (Group Chat Monitor)

## Testing Procedure

### Test Case 1: Group Chat Monitor Login (LoggedOn = 8)

1. Log in to VCC as Group Chat Monitor (LoggedOn=8)
2. Navigate to GroupChat Admin interface
3. Select a chat room from dropdown
4. **Expected**: Room interface loads, moderator can sign in
5. **Previous Bug**: Selector reset, iframe cleared immediately

### Test Case 2: Stealth Admin Login (LoggedOn = 2)

1. Log in to VCC as Regular Admin (LoggedOn=2)
2. Navigate to GroupChat Admin interface
3. Select a chat room from dropdown
4. **Expected**: Room interface loads in stealth mode (no sign-in form)
5. **Previous Bug**: Status set to 0, logged off immediately

### Test Case 3: Email Transcript

1. Have 2 moderators in a room (any combination of LoggedOn=2 or 8)
2. First moderator signs off
3. **Expected**: No email sent, room stays open
4. Second moderator signs off
5. **Expected**: Email sent, room closed, all data cleaned up

### Test Case 4: Moderator Exit

1. Sign in as moderator
2. Sign off using Exit button
3. **Expected**: No fatal errors, clean exit
4. **Previous Bug**: Fatal error about `dataQuery()` undefined function

## Debug Code (TO BE REMOVED)

The following debug console logging was added during troubleshooting and should be removed once confirmed working:

**`GroupChat/groupChat.js`**:
- Lines 1408-1429: `countLoggedOnUsers()` debug output
- Lines 1502-1507: `User.updateUser()` debug output
- Lines 927-936: `confirmedCloseCheck()` debug output
- Lines 944-947: `roomClosedCheck()` debug output
- Lines 955-1025: `roomClosed()` debug output and stack traces

**`GroupChat/Admin/groupChatAdmin.js`**:
- Lines 117-140: `moderatorSignOn()` debug output

**To remove debug code**: Search for `console.log` and `console.trace` in these files and remove debug blocks.

## Related Documentation

- `/CLAUDE.md` - Updated with GroupChat moderator authentication rules (lines 88-99)
- `/SESSION_FIXES_2025_01_08.md` - Related session management fixes
- `/TRAINING_INACTIVITY_TIMEOUT_FIX.md` - Similar timeout/authentication issues in training system

## Prevention

To prevent similar issues in future development:

1. **Always check field names** when querying Volunteers table - use `UserName` not `UserID`
2. **Use correct database function** - `dataQuery()` for VCC, `groupChatDataQuery()` for GroupChat
3. **Test both moderator types** - LoggedOn=2 (stealth) and LoggedOn=8 (visible)
4. **Check ON DUPLICATE KEY UPDATE** - ensure it doesn't inadvertently reset critical fields like `status`
5. **Count all moderator types** - use `(moderator = 1 OR moderator = 2)` not just `moderator = 1`

## Change Summary

| File | Lines | Change Type | Description |
|------|-------|-------------|-------------|
| `GroupChat/index.php` | 35, 151 | Bug Fix | Changed UserID → UserName in WHERE clauses |
| `GroupChat/index.php` | 212 | Bug Fix | Removed `status = 0` from UPDATE to prevent auto-signoff |
| `GroupChat/Admin/moderatorSignOn.php` | 21 | Bug Fix | Changed UserID → UserName in WHERE clause |
| `GroupChat/Admin/moderatorRoomList.php` | 21 | Bug Fix | Changed dataQuery → groupChatDataQuery |
| `GroupChat/groupChat.js` | 1008-1022 | Bug Fix | Removed selector reset for moderators in roomClosed() |
| `GroupChat/callerSignOff.php` | 78, 88 | Enhancement | Include moderator=2 in moderator checks |
| `GroupChat/Admin/includeFormatGroupChatEmail.php` | All | Bug Fix | Changed dataQuery → groupChatDataQuery |
| `CLAUDE.md` | 88-99 | Documentation | Added GroupChat authentication rules |

## Resolution Status

✅ **RESOLVED** - Moderators can now successfully access GroupChat rooms without being immediately kicked out.

All test cases pass as of January 2025.
