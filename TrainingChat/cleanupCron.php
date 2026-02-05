#!/usr/bin/env php
<?php
/**
 * Cron job script for periodic chat cleanup
 * Run this every 5-10 minutes via cron
 * 
 * Example crontab entry:
 * */5 * * * * /usr/bin/php /path/to/your/chat/cleanupCron.php
 */

// Change to script directory
chdir(dirname(__FILE__));

require_once('../../private_html/training_chat_db_login.php');
require_once('chatCleanup.php');

$logFile = 'cleanup.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    writeLog("Starting scheduled chat cleanup");
    
    $cleanupService = new ChatCleanupService(300); // 5 minute threshold
    $result = $cleanupService->performCleanup();
    
    $cleanedRooms = 0;
    foreach ($result['actions'] as $action) {
        if (is_array($action) && isset($action['action']) && $action['action'] === 'room_cleaned_successfully') {
            $cleanedRooms++;
        }
    }
    
    writeLog("Cleanup completed. Rooms cleaned: $cleanedRooms");
    
    // Optional: Send result to web interface
    if (isset($_GET['web'])) {
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    writeLog("Cleanup failed: " . $e->getMessage());
    
    if (isset($_GET['web'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>