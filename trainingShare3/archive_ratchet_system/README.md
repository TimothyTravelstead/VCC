# Archive - Ratchet WebSocket System

This directory contains the Ratchet-based WebSocket implementation that was attempted but had SSL/firewall issues on Cloudways.

## Archived Date
August 16, 2025

## Reason for Archival
While technically superior, this WebSocket implementation had irresolvable issues on managed hosting:
- Custom ports blocked by Cloudways firewall
- SSL certificate complications for non-standard ports
- Required server-level configuration changes not available to users

## Archived Files

### WebSocket Servers
- `websocketServer.php` - Production Ratchet WebSocket server
- `websocketServerDev.php` - Development server with test user support

### Client Libraries
- `trainingWebSocketClient.js` - WebSocket client wrapper
- `webrtcScreenSharingClient.js` - WebRTC + WebSocket integration

### Test Pages
- `test_webrtc_dev.html` - WebRTC testing with WebSocket backend
- `test_websocket_http.html` - HTTP-only WebSocket testing

## Technical Notes
The Ratchet system provided:
- Real-time bidirectional communication
- Modern WebSocket protocol compliance
- Excellent performance and scalability

However, it required:
- External port access (8080, 8083, 8443)
- Custom SSL certificate configuration
- Server-level Nginx proxy configuration

## Replacement
Returned to PHP EventSource + POST polling system which:
- Works reliably on any hosting platform
- Uses standard HTTPS port 443
- Requires no custom server configuration
- Proven track record of reliability