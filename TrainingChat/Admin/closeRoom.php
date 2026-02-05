<?php

require_once('../../../private_html/training_chat_db_login.php');
session_start();
ini_set("session.gc.maxlifetime", "14400000");
session_cache_limiter('nocache');
error_reporting(E_ALL);
ini_set('display_errors', 1); // Temporarily enable error display for debugging
ini_set('log_errors', 1);
ini_set('error_log', 'php-errors.log');

// Simulate $_REQUEST for CLI usage
if (PHP_SAPI === 'cli' && isset($argv[1])) {
    parse_str($argv[1], $_REQUEST);
}

// Debug log initial values
error_log("Script started - Session auth: " . (isset($_SESSION['auth']) ? $_SESSION['auth'] : 'not set'));
error_log("ChatRoomID received: " . ($_REQUEST['chatRoomID'] ?? 'not set'));





$userID = $_SESSION["UserID"] ?? "Travelstead";
$chatRoomID = $_REQUEST['chatRoomID'] ?? null;

// Release session lock after reading session data
session_write_close();

error_log("UserID: $userID, ChatRoomID: $chatRoomID");
error_log("All POST parameters: " . print_r($_POST, true));
error_log("All GET parameters: " . print_r($_GET, true));
error_log("All REQUEST parameters: " . print_r($_REQUEST, true));

if(!$userID || !$chatRoomID) {
    http_response_code(400);
    die('Missing required parameters');
}

try {
    error_log("Attempting to include database file");
    error_log("Database file included successfully");
    
    // Delete records in specific order
    $queries = [
        "DELETE FROM callers WHERE chatRoomID = ?",
        "DELETE FROM groupChatStatus WHERE chatRoomID = ?",
        "DELETE FROM groupChat WHERE chatRoomID = ?",
        "DELETE from groupchattimeouts WHERE chatRoomID = ?",
        "DELETE from transactions WHERE chatRoomID = ?"
    ];

    foreach ($queries as $index => $query) {
        error_log("Executing query $index: $query");
        $result = dataQuery($query, [$chatRoomID]);
        if ($result === false) {
            error_log("Query $index failed");
            throw new Exception("Failed executing query: " . $query);
        }
        error_log("Query $index completed successfully");
    }

    // Update room status first
    error_log("Updating room status");
    $updateRoom = "UPDATE groupChatRooms set open = 0 WHERE id = ?";
    $result = dataQuery($updateRoom, [$chatRoomID]);
    if ($result === false) {
        error_log("Room status update failed");
        throw new Exception("Failed to update room status");
    }
    error_log("Room status updated successfully");

    // Skip inserting final transaction record - room is being closed anyway
    error_log("Skipping final transaction record insertion - room is closed");

    error_log("All operations completed successfully");
    echo "OK";

} catch (Exception $e) {
    error_log("Error in closeRoom.php: " . $e->getMessage());
    http_response_code(500);
    echo $e->getMessage(); // Display error message for debugging
    die();
}
?>