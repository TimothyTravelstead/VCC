<?php
/**
 * Admin Debug Logging System
 * Logs all admin session activities to track auto-logoff issues
 */

function writeAdminLog($message, $type = 'INFO') {
    $logDir = '../log';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/admin_debug_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $userID = $_SESSION['UserID'] ?? 'UNKNOWN';
    $sessionID = session_id();
    $remoteIP = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $requestURI = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
    
    $logEntry = sprintf(
        "[%s] [%s] [UserID: %s] [SessionID: %s] [IP: %s] [URI: %s] %s\n",
        $timestamp,
        $type,
        $userID,
        $sessionID,
        $remoteIP,
        $requestURI,
        $message
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function logAdminLogin() {
    writeAdminLog("ADMIN LOGIN - Session started", "LOGIN");
}

function logAdminLogout($reason = 'USER_INITIATED') {
    writeAdminLog("ADMIN LOGOUT - Reason: $reason", "LOGOUT");
}

function logAdminPageLoad($page) {
    writeAdminLog("PAGE LOAD - $page", "PAGE");
}

function logAdminAction($action, $details = '') {
    writeAdminLog("ACTION - $action - $details", "ACTION");
}

function logAdminError($error, $details = '') {
    writeAdminLog("ERROR - $error - $details", "ERROR");
}

function logEventSourceActivity($event, $data = '') {
    writeAdminLog("EVENTSOURCE - Event: $event, Data: $data", "EVENTSOURCE");
}

function logSessionCheck() {
    $auth = $_SESSION["auth"] ?? 'NOT_SET';
    $userID = $_SESSION["UserID"] ?? 'NOT_SET';
    writeAdminLog("SESSION CHECK - auth: $auth, UserID: $userID", "SESSION");
}
?>