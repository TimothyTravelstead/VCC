# Archive - Old Training System Files

This directory contains files from the previous PHP-based signaling system that have been replaced by the new Ratchet WebSocket implementation.

## Archived Date
August 16, 2025

## Reason for Archival
These files were part of the original file-based signaling system that had reliability issues. They have been replaced by a modern Ratchet WebSocket server that provides:

- Real-time communication instead of file polling
- Better multi-participant support
- Integrated database authentication
- Standard WebRTC signaling protocols

## Archived File Categories

### Old PHP Signaling Servers
- `signalingServer.php` - Original signaling server
- `signalingServerMulti.php` - Multi-participant signaling server
- `roomManager.php` - Room management for old system
- `simpleTrainingSignaling.php` - Simple signaling implementation
- `noAuthSignalingServer.php` - Unauthenticated signaling server
- `minimal_signaling_test.php` - Minimal signaling test

### File-based Message Queue System
- `Signals/` - Directory containing participant and room JSON files
- `training_messages.json` - Old message queue file

### Test Files for Old System
- `test_signal.php` - Signal testing
- `test_eventsource.php` - EventSource testing
- `test_complete_flow.php` - Complete flow testing
- `test_auth.php` - Authentication testing
- `simple_test.php` - Simple system test
- `curl_test.sh` - HTTP endpoint testing

### Debug/Development Files
- `console_debug_helper.js` - Console debugging utilities
- `debug_init.js` - Initialization debugging
- `debug_trainee_condition.js` - Trainee condition debugging
- `trainee_debug_logger.js` - Trainee logging system
- `create_test_users.php` - Test user creation utility

### Old Client-side Files
- `noAuthScreenSharing.js` - Unauthenticated screen sharing
- `screenSharingControl.js` - Original screen sharing control
- `screenSharingControlMulti.js` - Multi-user screen sharing control
- `simpleTrainingScreenShare.js` - Simple screen sharing implementation

### HTML Test Pages
- `test.html` - Generic test page
- `simple_caller.html` - Simple caller test
- `simple_receiver.html` - Simple receiver test
- `simple_integration_test.html` - Integration test
- `integration_status_check.html` - Status checking
- `test_caller.html` - Caller testing
- `test_receiver.html` - Receiver testing
- `test_sharer.html` - Sharer testing
- `test_viewer.html` - Viewer testing
- `test_trainee.html` - Trainee testing
- `test_trainee_noauth.html` - Unauthenticated trainee testing
- `test_trainer.html` - Trainer testing
- `test_trainer_noauth.html` - Unauthenticated trainer testing

### Backup Files
- `trainingSession.js.backup` - Backup of training session file

## Replacement System

The new system consists of:

- **`websocketServer.php`** - Ratchet-based WebSocket server with database integration
- **`websocket-manager.php`** - Server management utility
- **`trainingWebSocketClient.js`** - Modern client-side WebSocket library
- **`test_websocket.html`** - Test page for new WebSocket system

## Recovery

If any of these files are needed for reference, they can be safely moved back to the main directory. However, the new Ratchet-based system should handle all previous functionality more reliably.

## Safe to Delete

After confirming the new system works correctly, this entire archive directory can be safely deleted to free up disk space.