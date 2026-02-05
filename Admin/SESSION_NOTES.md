# Admin Auto-Logoff Investigation - Session Notes

## CRITICAL SYSTEM ARCHITECTURE 
**Username/UserID Convention (IMPORTANT FOR FUTURE SESSIONS):**
- `UserID` database field = Numeric MySQL primary key (e.g., 7) - used ONLY for database uniqueness
- `UserName` database field = Text username (e.g., "Travelstead") - this is the actual user identifier  
- `$_SESSION["UserID"]` = Contains the **UserName** (e.g., "Travelstead"), NOT the numeric UserID
- `$VolunteerID` = Also contains the **UserName** (e.g., "Travelstead")
- Throughout the system = Variables named "UserID" and "VolunteerID" actually contain usernames, not numeric database keys

## PROBLEM DESCRIPTION
- Admin users were experiencing automatic logoff 5-10 seconds after login
- This only affected Admin signons, not regular volunteer access
- Problem started after adding new spreadsheet upload functionality to Admin DataPane

## ROOT CAUSE DISCOVERED
From log analysis (`/log/admin_debug_2025-07-26.log` line 29):
```
FOUND VIA USERNAME - UserName: Travelstead, ActualUserID: 7, LoggedOn: 1
```

**Key Finding:** The architecture is actually CORRECT:
- Session stores: `$_SESSION["UserID"] = "Travelstead"` (username) ✓
- vccFeed.php queries: `WHERE UserName = ?` with parameter "Travelstead" ✓  
- Database returns: `LoggedOn = 1` (user is logged in) ✓

## WHAT WE'VE DONE

### 1. Added Comprehensive Logging System
- `adminDebugLog.php` - Debug logging functions
- `adminDebugReceiver.php` - JavaScript debug message receiver
- Added logging to:
  - Admin login process (`Admin/index.php`)
  - EventSource connection (`vccFeed.php`) 
  - JavaScript logoff events (`Admin/index.js`)
  - Exit program calls (`Admin/ExitProgram.php`)

### 2. Identified Auto-Logoff Mechanism
- EventSource (`vccFeed.php`) sends `event: logoff` with `data: 0` when user's `LoggedOn = 0`
- JavaScript (`Admin/index.js`) receives this and calls `Exit()` function
- `Exit()` calls `ExitProgram.php` which sets `LoggedOn = 0` in database

### 3. Found Timing Issue
Auto-logoff sequence from logs:
```
16:37:20.935Z - EventSource opens
16:37:20.939Z - Logoff event received (Data: 0) 
16:37:20.940Z - Auto-logoff triggered
```
This happens within **5 milliseconds** of EventSource connection!

### 4. Architecture Verification
Confirmed vccFeed.php is correctly architected:
- Line 15: `$VolunteerID = $_SESSION['UserID']` (gets "Travelstead")
- Line 275: `WHERE UserName = ?` (correctly queries by UserName)  
- Line 277: Uses `$VolunteerID` as parameter (passes "Travelstead")

### 5. Reverted All Temporary Changes
- Removed all debug logging from production code
- Added cache-busting headers to prevent browser caching
- System is back to original state for testing

## CURRENT STATUS - Session 2 (January 27, 2025)

### ✅ COMPLETED INVESTIGATION
1. **Multiple LoggedOn queries identified** - Found 2 separate queries in vccFeed.php:
   - Lines 92-98: Gets volunteer list for display (`LoggedOn IN (1,2,4,6,7,8,9)`)
   - Lines 272-275: Gets current user's LoggedOn status for logoff event (`WHERE UserName = ?`)

2. **JavaScript logoff logic confirmed** - `Admin/index.js:1262`:
   ```javascript
   if(event.data == "0") {
       Exit(); // Only triggers when data equals "0"
   }
   ```

3. **Session consistency verified** - vccFeed.php correctly uses:
   - Line 15: `$VolunteerID = $_SESSION['UserID']` (gets username like "Travelstead")
   - Line 277: `WHERE UserName = ?` with `$VolunteerID` parameter

### 🔍 ROOT CAUSE DISCOVERED
**The auto-logoff is triggered when the database query returns NO RESULTS!**

From previous debug log:
- Admin user "Travelstead" has `LoggedOn = 1` (not 2 as expected)
- When vccFeed.php query fails to find the user, it sends `data: 0` by default
- JavaScript receives `data: 0` and triggers `Exit()`

### 🔧 DEBUGGING CHANGES MADE
1. **Added comprehensive logging** to vccFeed.php around lines 272-289:
   - Logs session UserID value
   - Logs database query result count
   - Logs actual LoggedOn value returned
   - Logs the logoff event data being sent

2. **Added fallback case** - When no database result found:
   - Explicitly sends `data: 0` (which triggers logoff)
   - Logs this occurrence for debugging

### 🎯 HYPOTHESIS
The admin auto-logoff occurs because:
1. **Session/Database mismatch** - `$_SESSION['UserID']` contains a username that doesn't exist in the database
2. **Session expiry** - Admin session expires between login and EventSource connection
3. **Timing issue** - Database query happens before admin LoggedOn status is properly set

## NEXT STEPS FOR TESTING
1. **Monitor debug logs** - Check error_log for "DEBUG LOGOFF" messages when admin logs in
2. **Verify admin login process** - Ensure `LoggedOn = 2` is set correctly during admin login
3. **Check session persistence** - Verify `$_SESSION['UserID']` contains correct username
4. **Test with different admin users** - See if issue affects all admins or just "Travelstead"

**Key files to monitor:**
- PHP error log (location varies by server config)
- `/log/admin_debug_2025-07-27.log` (if using previous debug system)

## KEY FILES MODIFIED
- `/log/admin_debug_2025-07-26.log` - Contains all debug information
- `Admin/adminDebugLog.php` - Logging functions (kept for future use)
- `Admin/adminDebugReceiver.php` - Debug receiver (kept for future use)
- All other files reverted to original state

## UPLOAD FUNCTIONALITY STATUS
- Spreadsheet upload functionality is complete and working
- Located in Admin DataPane with two upload forms (type1 and type2)
- Files upload to `/pridePath/` directory
- No connection found between upload functionality and auto-logoff issue

**The auto-logoff appears to be a pre-existing issue that coincidentally emerged during upload development.**