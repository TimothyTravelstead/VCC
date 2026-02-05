<?php


require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

try {
    // Clean and format the phone number
    $CallerID = $_REQUEST["PhoneNumber"];
    $BlockNumber = "+1" . preg_replace("/[^0-9]/", "", $CallerID);
    
    // Delete from BlockList with parameter binding
    $query = "DELETE FROM `BlockList` WHERE PhoneNumber = :phoneNumber";
    $result = dataQuery($query, ['phoneNumber' => $BlockNumber]);
    
    if ($result !== false) {
        echo "OK";
    } else {
        http_response_code(500);
        echo "Error removing block";
    }
} catch (Exception $e) {
    error_log("Error unblocking number: " . $e->getMessage());
    http_response_code(500);
    echo "Error processing request";
}
?>
