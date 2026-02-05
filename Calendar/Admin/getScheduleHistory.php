<?php

require_once('../../../private_html/db_login.php');
session_start();
session_write_close(); // Prevent session locking for background requests

// Include the database configuration

// Format the month number with leading zero if needed
$Month = str_pad($_REQUEST['month'] + 1, 2, '0', STR_PAD_LEFT);
$Year = $_REQUEST['year'];
$Date = "$Year-$Month-01";

// Calculate date range
$StartDate = date_create($Date);
date_modify($StartDate, "-1 months");
$EndDate = date_create($Date);
date_modify($EndDate, "+2 Month");
date_modify($EndDate, "-1 Days");

$Start = date_format($StartDate, 'Y-m-d');
$End = date_format($EndDate, 'Y-m-d');

// Initialize schedule array
$Schedule = [];

// Get historical schedule data from Database using prepared statement
$query = "SELECT 
    Date, 
    Block, 
    Volunteers.UserName, 
    ShiftType, 
    ShiftLocation, 
    LoggedOnForBlock, 
    CONCAT(Volunteers.FirstName, ' ', Volunteers.LastName) as FullName 
FROM volunteerScheduleHistory 
JOIN volunteers ON (volunteerScheduleHistory.UserName = Volunteers.UserName) 
WHERE Date <= ? AND Date >= ? 
ORDER BY Date, Block, Volunteers.LastName, Volunteers.FirstName, volunteerScheduleHistory.UserName";

$result = dataQuery($query, [$End, $Start]);

// Process results if query successful
if ($result) {
    foreach ($result as $row) {
        $scheduleRecord = [
            "Date" => $row->Date,
            "Block" => $row->Block,
            "UserName" => $row->UserName,
            "ShiftType" => $row->ShiftType,
            "ShiftLocation" => $row->ShiftLocation,
            "LoggedOnForBlock" => $row->LoggedOnForBlock,
            "FullName" => $row->FullName
        ];

        $date = $scheduleRecord["Date"];
        $block = $scheduleRecord["Block"];

        // Initialize nested arrays if they don't exist
        if (!isset($Schedule[$date])) {
            $Schedule[$date] = [];
        }

        if (!isset($Schedule[$date][$block])) {
            $Schedule[$date][$block] = [];
        }

        // Add record to schedule
        $Schedule[$date][$block][] = $scheduleRecord;
    }
}

// Output JSON response
header('Content-Type: application/json');
echo json_encode($Schedule);
