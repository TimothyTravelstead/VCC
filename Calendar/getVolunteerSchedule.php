<?php

require_once('../../private_html/db_login.php');
session_start();

// Require authentication
requireAuth();

session_write_close(); // Prevent session locking for background requests

// Include database configuration

// Initialize variables
$Date = date('Y-m-d');
$Schedule = [];

if (!isset($_REQUEST["changes"])) {
    // Fetch regular schedule
    $query = "SELECT 
        ID,
        Day,
        Block,
        volunteerSchedule.UserName,
        StartDate,
        EndDate,
        CONCAT(Volunteers.FirstName, ' ', Volunteers.LastName) as FullName,
        ShiftLocation,
        ShiftType
    FROM volunteerSchedule 
    JOIN volunteers ON (volunteerSchedule.UserName = Volunteers.UserName)
    WHERE EndDate >= ?
    ORDER BY Day, Block, Volunteers.LastName, Volunteers.FirstName, volunteerSchedule.UserName";

    $result = dataQuery($query, [$Date]);

    if ($result) {
        foreach ($result as $row) {
            $scheduleRecord = [
                "ID" => $row->ID,
                "Day" => $row->Day,
                "Block" => $row->Block,
                "UserName" => $row->UserName,
                "StartDate" => $row->StartDate,
                "EndDate" => $row->EndDate,
                "FullName" => $row->FullName,
                "Location" => $row->ShiftLocation,
                "ShiftType" => $row->ShiftType
            ];

            $day = $scheduleRecord["Day"];
            $block = $scheduleRecord["Block"];

            // Initialize nested array if not exists
            if (!isset($Schedule[$day][$block])) {
                $Schedule[$day][$block] = [];
            }

            $Schedule[$day][$block][] = $scheduleRecord;
        }
    }
} else {
    // Fetch schedule changes
    $query = "SELECT 
        ID,
        Date,
        Block,
        volunteerScheduleChanges.Type,
        volunteerScheduleChanges.UserName,
        CONCAT(Volunteers.FirstName, ' ', Volunteers.LastName) as FullName,
        ShiftLocation,
        ShiftType
    FROM volunteerScheduleChanges 
    JOIN volunteers ON (volunteerScheduleChanges.UserName = Volunteers.UserName)
    WHERE Date >= ?
    ORDER BY transactionTime, Date, Block, Volunteers.LastName, 
            Volunteers.FirstName, Volunteers.UserName, volunteerScheduleChanges.Type";

    $result = dataQuery($query, [$Date]);

    if ($result) {
        foreach ($result as $row) {
            $scheduleRecord = [
                "ID" => $row->ID,
                "Date" => $row->Date,
                "Block" => $row->Block,
                "Type" => $row->Type,
                "UserName" => $row->UserName,
                "FullName" => $row->FullName,
                "Location" => $row->ShiftLocation,
                "ShiftType" => $row->ShiftType
            ];

            $date = $scheduleRecord["Date"];
            $block = $scheduleRecord["Block"];

            // Initialize nested array if not exists
            if (!isset($Schedule[$date][$block])) {
                $Schedule[$date][$block] = [];
            }

            $Schedule[$date][$block][] = $scheduleRecord;
        }
    }
}

// Set JSON header and output
header('Content-Type: application/json');
echo json_encode($Schedule);
