<?php

require_once('../../private_html/db_login.php');
session_start();

// Require authentication
requireAuth();

session_write_close(); // Prevent session locking for background requests

// Include database configuration

// Get Hotline Open Hours
$query = "SELECT 
    dayofweek, 
    TIME_TO_SEC(Start)/60/60*2 as Start, 
    TIME_TO_SEC(End)/60/60*2-1 as end 
FROM Hours 
ORDER BY dayofweek, start, end";

$result = dataQuery($query);

// Initialize array for open hours
$OpenHours = [];

if ($result) {
    foreach ($result as $row) {
        // Adjust day of week to be zero-based
        $dayIndex = $row->dayofweek - 1;
        
        $OpenHours[$dayIndex] = [
            'DayOfWeek' => $dayIndex,
            'StartTime' => $row->Start,
            'EndTime' => $row->end
        ];
    }
}

// Set JSON header and output response
header('Content-Type: application/json');
echo json_encode($OpenHours);



