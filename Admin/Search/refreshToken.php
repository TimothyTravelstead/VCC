<?php
/**
 * CSRF Token Refresh Endpoint
 *
 * This endpoint allows long-running Resource Admin sessions to verify their
 * CSRF tokens are still valid, preventing 403 errors during extended work sessions.
 *
 * Called automatically every 50 minutes by JavaScript in index.php
 *
 * NOTE: Uses getCSRFToken() (not generateCSRFToken()) to support multiple browser tabs.
 * This returns the existing valid token rather than creating a new one, ensuring all
 * tabs share the same token and can submit forms without CSRF validation failures.
 */

// Include database connection FIRST to set session configuration before session_start()
require_once('../../../private_html/db_login.php');
require_once('../../../private_html/csrf_protection.php');

// Start the session with the correct configuration
session_start();

// Read user authentication status
$VolunteerID = $_SESSION['UserID'] ?? null;
$isAuthenticated = isset($_SESSION['auth']) && $_SESSION['auth'] === 'yes';

// Verify user is logged in
if (!$VolunteerID || !$isAuthenticated) {
    session_write_close(); // Release session lock before responding
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized: Please log in to refresh your token.'
    ]);
    exit;
}

// Get the existing CSRF token (or generate if needed)
// Using getCSRFToken() instead of generateCSRFToken() to support multiple browser tabs
// - generateCSRFToken() creates a NEW token, invalidating tokens in other tabs
// - getCSRFToken() returns the existing valid token, keeping all tabs in sync
$newToken = getCSRFToken();

// Release session lock now that we've written the new token
session_write_close();

// Return the new token to the client
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'token' => $newToken,
    'timestamp' => time()
]);
?>
