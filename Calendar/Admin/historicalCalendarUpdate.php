<?php

// Include database configuration
require_once('../../../private_html/db_login.php');

// Get session and request variables
$volunteerID = $_SESSION['UserID'] ?? null;
$fullName = $_SESSION['FullName'] ?? null;
$type = $_SESSION['userType'] ?? null;
$Date = $_REQUEST['date'] ?? null;

// Initialize arrays
$Schedule = [];

// Get the last history date
$query = "SELECT MAX(date) as last_date FROM volunteerScheduleHistory";
$result = dataQuery($query);
$lastHistoryDate = $result && count($result) > 0 ? $result[0]->last_date : "2015-07-01";

// Calculate date range for processing
$phpdate = date_create($lastHistoryDate);
date_modify($phpdate, "+1 days");
$now = date_create();
date_modify($now, "-1 days");

// Process each day's schedule
while ($now > $phpdate) {
    $Date = date_format($phpdate, "Y-m-d");
    createScheduleHistory($Date);
    date_modify($phpdate, "+1 days");
}

function createScheduleHistory($Date) {
    $Schedule = [];

    // Delete existing history for the date
    $query = "DELETE FROM volunteerScheduleHistory WHERE Date = ?";
    dataQuery($query, [$Date]);

    // Get regular schedule
    $query = "SELECT 
        ID, 
        Day, 
        Block, 
        volunteerSchedule.UserName, 
        CONCAT(Volunteers.FirstName, ' ', Volunteers.LastName) as FullName,
        ShiftLocation,
        ShiftType,
        (SELECT COUNT(*) 
         FROM Volunteerlog 
         WHERE volunteerlog.UserID = volunteerSchedule.UserName 
         AND DATE(EventTime) = ? 
         AND LoggedOnStatus > 0 
         AND LoggedOnStatus != 3 
         AND LoggedOnStatus != 2 
         ORDER BY EventTime DESC) as LoggedOn
    FROM volunteerSchedule 
    JOIN volunteers ON (volunteerSchedule.UserName = Volunteers.UserName)
    WHERE Day = DATE_FORMAT(?, '%w') 
    AND StartDate <= ? 
    AND EndDate >= ?
    ORDER BY Day, Block, Volunteers.LastName, Volunteers.FirstName, volunteerSchedule.UserName";

    $result = dataQuery($query, [$Date, $Date, $Date, $Date]);

    if ($result) {
        foreach ($result as $row) {
            $scheduleRecord = [
                "ID" => $row->ID,
                "Date" => $Date,
                "Block" => $row->Block,
                "UserName" => $row->UserName,
                "FullName" => $row->FullName,
                "Location" => $row->ShiftLocation,
                "ShiftType" => $row->ShiftType,
                "LoggedOn" => $row->LoggedOn
            ];

            $block = $scheduleRecord["Block"];
            $userName = $scheduleRecord["UserName"];

            if (!isset($Schedule[$block])) {
                $Schedule[$block] = [];
            }
            if (!isset($Schedule[$block][$userName])) {
                $Schedule[$block][$userName] = [];
            }
            
            $Schedule[$block][$userName][] = $scheduleRecord;
        }
    }

    // Get schedule changes
    $query = "SELECT 
        ID, 
        Date, 
        Block, 
        volunteerScheduleChanges.Type,
        volunteerScheduleChanges.UserName,
        CONCAT(Volunteers.FirstName, ' ', Volunteers.LastName) as FullName,
        ShiftLocation,
        ShiftType,
        (SELECT COUNT(*) 
         FROM Volunteerlog 
         WHERE volunteerlog.UserID = volunteerScheduleChanges.UserName 
         AND DATE(EventTime) = ? 
         AND LoggedOnStatus > 0 
         AND LoggedOnStatus != 3 
         AND LoggedOnStatus != 2 
         ORDER BY EventTime DESC) as LoggedOn
    FROM volunteerScheduleChanges 
    JOIN volunteers ON (volunteerScheduleChanges.UserName = Volunteers.UserName)
    WHERE Date = ?
    ORDER BY transactionTime, Date, Block, Volunteers.LastName, Volunteers.FirstName, Volunteers.UserName, volunteerScheduleChanges.Type";

    $result = dataQuery($query, [$Date, $Date]);

    if ($result) {
        foreach ($result as $row) {
            $scheduleChange = [
                "ID" => $row->ID,
                "Date" => $row->Date,
                "Block" => $row->Block,
                "UserName" => $row->UserName,
                "FullName" => $row->FullName,
                "Location" => $row->ShiftLocation,
                "ShiftType" => $row->ShiftType,
                "LoggedOn" => $row->LoggedOn
            ];

            $block = $scheduleChange["Block"];
            $userName = $scheduleChange["UserName"];
            $type = $row->Type;

            if ($type == "Add") {
                if (!isset($Schedule[$block][$userName])) {
                    $Schedule[$block][$userName] = [];
                    $Schedule[$block][$userName][] = $scheduleChange;
                }
            } elseif ($type == 'Delete') {
                if (isset($Schedule[$block][$userName])) {
                    unset($Schedule[$block][$userName]);
                }
            }
        }
    }

    // Process and insert final schedule
    if (!empty($Schedule)) {
        ksort($Schedule);

        foreach ($Schedule as $BlockNumber => $Block) {
            foreach ($Block as $User) {
                foreach ($User as $Value) {
                    // Extract values
                    $historyDate = $Value['Date'] ?? '';
                    $blockNumber = $Value['Block'] ?? 0;
                    $userName = $Value['UserName'] ?? '';
                    $shiftType = $Value['ShiftType'] ?? '';
                    $shiftLocation = $Value['Location'] ?? '';
                    $loggedOnToBlock = $Value['LoggedOn'] ?? '0';

                    // Insert history record
                    $query = "INSERT INTO volunteerScheduleHistory 
                             (Date, Block, UserName, ShiftType, ShiftLocation, LoggedOnForBlock) 
                             VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $result = dataQuery($query, [
                        $historyDate,
                        $blockNumber,
                        $userName,
                        $shiftType,
                        $shiftLocation,
                        $loggedOnToBlock
                    ]);

                    if ($result === false) {
                        die("Error inserting schedule history record");
                    }
                }
            }
        }
    }
}

