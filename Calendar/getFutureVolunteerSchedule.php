<?php
// Include database configuration FIRST (sets session configuration)
require_once('../../private_html/db_login.php');

// NOTE: This file is called during login flow (before user is authenticated)
// to check if volunteer is on the calendar. Do NOT add requireAuth() here.

// Get request parameters and initialize variables
$Volunteer = $_REQUEST['UserID'] ?? null;
$requestType = $_REQUEST['type'] ?? null;

// Set up date variables
$Date = date('Y-m-d');
$currentTimestamp = time();
$DayOfWeek = date('w', $currentTimestamp);
$midnightTimestamp = strtotime('midnight');

// Calculate current block (half hour blocks)
$currentBlock = floor(((time() - $midnightTimestamp) / 3600) * 2);

/**
 * Check if a volunteer is scheduled for specific blocks
 * 
 * @param string|null $Volunteer The volunteer's username
 * @param int $currentBlock The current time block
 * @param string $Date The date to check
 * @return string JSON encoded schedule information or "Yes" if volunteer is scheduled
 */
function CheckVolunteer($Volunteer, $currentBlock, $Date) 
{
    // Calculate blocks to check
    $blockToCheck = $currentBlock;
    $lastBlockToCheck = $blockToCheck + 5;
    $DayOfWeek = date('w', time());
    
    // Initialize schedule array with empty blocks
    $Schedule = array_fill($blockToCheck, ($lastBlockToCheck - $blockToCheck + 1), []);
    
    // Get permanent volunteer schedule
    $query = "SELECT
        block,
        volunteerSchedule.UserName
    FROM volunteerSchedule
    JOIN volunteers ON (volunteerSchedule.UserName = Volunteers.UserName)
    WHERE StartDate <= ?
    AND EndDate >= ?
    AND Day = ?
    AND shiftType <> 'ResourceOnly'
    AND Block >= ?
    AND Block <= ?
    ORDER BY Day, Block, Volunteers.LastName, Volunteers.FirstName, volunteerSchedule.UserName";

    $result = dataQuery($query, [
        $Date,
        $Date,
        $DayOfWeek,
        $blockToCheck,
        $lastBlockToCheck
    ]);

    // Process regular schedule
    if ($result) {
        foreach ($result as $row) {
            $block = $row->block;
            $userName = $row->UserName;
            
            if (!isset($Schedule[$block])) {
                $Schedule[$block] = [];
            }
            
            $Schedule[$block][$userName] = [
                'Block' => $block,
                'UserName' => $userName
            ];
        }
    }

    // Get schedule changes
    $query = "SELECT
        Block,
        volunteerScheduleChanges.Type,
        volunteerScheduleChanges.UserName
    FROM volunteerScheduleChanges
    JOIN volunteers ON (volunteerScheduleChanges.UserName = Volunteers.UserName)
    WHERE Date = ?
    AND Block >= ?
    AND Block <= ?
    ORDER BY transactionTime, Date, Block, Volunteers.LastName, Volunteers.FirstName,
            Volunteers.UserName, volunteerScheduleChanges.Type";

    $result = dataQuery($query, [
        $Date,
        $blockToCheck,
        $lastBlockToCheck
    ]);

    // Process schedule changes
    if ($result) {
        foreach ($result as $row) {
            $block = $row->Block;
            $userName = $row->UserName;
            $type = $row->Type;

            // Case-insensitive volunteer name matching
            if (strtolower($Volunteer) === strtolower($userName)) {
                $Volunteer = $userName; // Use correct case from database
            }

            // Handle schedule changes
            if ($type === "Delete") {
                if (isset($Schedule[$block][$userName])) {
                    unset($Schedule[$block][$userName]);
                }
            } elseif ($type === "Add") {
                if (!isset($Schedule[$block])) {
                    $Schedule[$block] = [];
                }
                if (!isset($Schedule[$block][$userName])) {
                    $Schedule[$block][$userName] = [
                        'Block' => $block,
                        'UserName' => $userName,
                        'Type' => $type
                    ];
                }
            }
        }
    }

    // Prepare results
    $results = [
        'Schedule' => $Schedule,
        'CurrentBlock' => $blockToCheck,
        'LastBlock' => $lastBlockToCheck
    ];

    // Check if volunteer is scheduled for current block
    return !isset($Schedule[$currentBlock][$Volunteer]) 
        ? json_encode($results) 
        : json_encode("Yes");
}

// Execute check and output results
echo CheckVolunteer($Volunteer, $currentBlock, $Date);

