<?php

// Get and sanitize date parameters

require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$Start = $_REQUEST['Start'];
$End = $_REQUEST['End'];

// Query with status mapping and name lookup
$query = "SELECT 
    IDNum, 
    (SELECT CONCAT(Volunteers.FirstName, ' ', Volunteers.LastName) 
     FROM Volunteers 
     WHERE Volunteers.UserName = volunteerlog.UserID) as Name,
    UserID,
    EventTime,
    LoggedOnStatus,
    CASE 
        WHEN LoggedOnStatus = 1 THEN 'Volunteer'
        WHEN LoggedOnStatus = 2 THEN 'Admin User'
        WHEN LoggedOnStatus = 3 THEN 'Resource Only'
        WHEN LoggedOnStatus = 4 THEN 'Trainer'
        WHEN LoggedOnStatus = 5 THEN 'Monitor'
        WHEN LoggedOnStatus = 6 THEN 'Trainee'
        WHEN LoggedOnStatus = 7 THEN 'Admin Mini'
        WHEN LoggedOnStatus = 8 THEN 'Group Chat Monitor'
    END as Description,
    ChatOnly 
FROM VolunteerLog 
WHERE (SELECT CONCAT(Volunteers.FirstName, ' ', Volunteers.LastName) 
       FROM Volunteers 
       WHERE Volunteers.UserName = volunteerlog.UserID) <> '' 
    AND EventTime >= :start_date 
    AND EventTime <= :end_date 
ORDER BY EventTime DESC";

$params = [
    ':start_date' => $Start,
    ':end_date' => $End
];

$result = dataQuery($query, $params);

// Set headers for CSV download
header("Content-Type: text/csv;filename=Volunteer_Log.csv");
header("Content-Disposition: attachment; filename=Volunteer_Log.csv"); 

// Define CSV headers
$headers = [
    "ID Number",
    "Name",
    "UserID",
    "Event Time",
    "Logged On Status",
    "Description",
    "Chat Only"
];

// Output CSV header
echo '"' . implode('","', $headers) . '"' . "\r\n";

// Process and output results
if ($result) {
    foreach ($result as $row) {
        $values = [
            $row->IDNum,
            $row->Name,
            $row->UserID,
            $row->EventTime,
            $row->LoggedOnStatus,
            $row->Description,
            $row->ChatOnly
        ];
        
        // Output each value with proper CSV escaping
        echo '"' . implode('","', array_map(function($value) {
            return str_replace('"', '""', $value);
        }, $values)) . '"' . "\r\n";
    }
}

// No need to explicitly close connection as PDO handles this automatically
?>
