<?php

// Include database connection and functions

// Get and sanitize input parameters

require_once('../private_html/db_login.php');
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$CallerID = $_REQUEST["PhoneNumber"] ?? null;
$VolunteerID = $_REQUEST["VolunteerID"] ?? null;
$Message = $_REQUEST["Message"] ?? " ";  // Default to space if not provided
$Type = $_REQUEST["Type"] ?? null;
$InternetNumber = $_REQUEST["InternetNumber"] ?? 0;

// Format the block number
if (strlen($CallerID) > 10) {
    $BlockNumber = "+1" . substr($CallerID, 1, 3) . substr($CallerID, 6, 3) . substr($CallerID, 10, 4);
} else {
    $BlockNumber = "+1" . $CallerID;
}

try {
    // Check if number is already blocked
    $query = "SELECT PhoneNumber 
              FROM BlockList 
              WHERE PhoneNumber = ?";
    $result = dataQuery($query, [$BlockNumber]);

    if (empty($result)) {
        // Insert new blocked number
        $query = "INSERT INTO BlockList 
                  (Date, PhoneNumber, Type, Message, UserName, InternetNumber) 
                  VALUES (NOW(), ?, ?, ?, ?, ?)";
        dataQuery($query, [
            $BlockNumber,
            $Type,
            $Message,
            $VolunteerID,
            $InternetNumber
        ]);
    } else {
        // Update existing block
        $query = "UPDATE BlockList 
                  SET Date = NOW(),
                      Message = ?,
                      Type = ?,
                      UserName = ?,
                      InternetNumber = ? 
                  WHERE PhoneNumber = ?";
        dataQuery($query, [
            $Message,
            $Type,
            $VolunteerID,
            $InternetNumber,
            $BlockNumber
        ]);
    }

    // Return appropriate response based on block type
    if ($Type == "User") {
        // Return close window script for user-initiated blocks
        ?>
        <html>
            <head>
                <script>
                    window.close();
                </script>
            </head>
            <body>
            </body>
        </html>
        <?php
    } else {
        echo "OK";
    }

} catch (Exception $e) {
    // Log error and return friendly message
    error_log("Block caller error: " . $e->getMessage());
    die("Could not process blocking request. Please try again.");
}
