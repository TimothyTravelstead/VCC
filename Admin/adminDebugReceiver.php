<?php
require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately
session_write_close();

include('adminDebugLog.php');

// Only process if this is a debug request
if ($_POST['postType'] === 'adminDebug') {
    $action = $_POST['action'] ?? 'UNKNOWN';
    $data = $_POST['data'] ?? 'NO_DATA';
    $timestamp = $_POST['timestamp'] ?? 'NO_TIMESTAMP';
    
    switch($action) {
        case 'logoff_received':
            writeAdminLog("JAVASCRIPT LOGOFF EVENT RECEIVED - Data: $data, JS Timestamp: $timestamp", "JS_LOGOFF");
            break;
            
        case 'auto_logoff_triggered':
            writeAdminLog("CRITICAL: AUTO-LOGOFF TRIGGERED - Data: $data, JS Timestamp: $timestamp", "CRITICAL");
            break;
            
        case 'eventsource_error':
            writeAdminLog("EVENTSOURCE ERROR - Data: $data, JS Timestamp: $timestamp", "ERROR");
            break;
            
        case 'eventsource_open':
            writeAdminLog("EVENTSOURCE OPENED - Data: $data, JS Timestamp: $timestamp", "EVENTSOURCE");
            break;
            
        default:
            writeAdminLog("UNKNOWN DEBUG ACTION - Action: $action, Data: $data, JS Timestamp: $timestamp", "DEBUG");
            break;
    }
    
    echo "OK";
} else {
    writeAdminLog("INVALID DEBUG REQUEST - PostType: " . ($_POST['postType'] ?? 'NOT_SET'), "ERROR");
    echo "ERROR";
}
?>