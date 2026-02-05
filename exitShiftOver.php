<?php

// Include database connection and functions


require_once('../private_html/db_login.php');
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

function CheckVolunteer($Volunteer) {
    // Get current time blocks
    $Month = date("m");                
    $Year = date("Y");
    $Day = date("d");
    $Date = "$Year-$Month-$Day";
    $Date2 = time();
    $DayOfWeek = date("w", $Date2);
    $Date3 = strtotime('midnight');
    
    // Calculate time blocks
    $currentBlock = floor((($Date2 - $Date3) / 3600) * 2);
    $blockToCheck = $currentBlock;                        
    $lastBlockToCheck = $blockToCheck + 5;
    
    // Initialize schedule array
    $Schedule = array_fill($blockToCheck, $lastBlockToCheck - $blockToCheck + 1, []);

    try {
        // Get permanent schedule
        $query = "SELECT block, volunteerSchedule.UserName 
                  FROM volunteerSchedule 
                  JOIN volunteers ON (volunteerSchedule.UserName = Volunteers.UserName) 
                  WHERE StartDate <= ? 
                  AND EndDate >= ? 
                  AND Day = ? 
                  AND shiftType != 'ResourceOnly' 
                  AND Block BETWEEN ? AND ? 
                  ORDER BY Day, Block, Volunteers.LastName, 
                           Volunteers.FirstName, volunteerSchedule.UserName";
        
        $result = dataQuery($query, [$Date, $Date, $DayOfWeek, $blockToCheck, $lastBlockToCheck]);

        foreach ($result as $row) {
            $block = $row->block;
            $userName = $row->UserName;
            $Schedule[$block][$userName] = (array)$row;
        }

        // Check for schedule changes
        $query = "SELECT Block, Type as ChangeType, UserName 
                  FROM volunteerScheduleChanges 
                  JOIN volunteers ON (volunteerScheduleChanges.UserName = Volunteers.UserName) 
                  WHERE Date = ? 
                  AND Block BETWEEN ? AND ? 
                  ORDER BY transactionTime, Date, Block, Volunteers.LastName, 
                           Volunteers.FirstName, Volunteers.UserName, 
                           volunteerScheduleChanges.Type";
        
        $changes = dataQuery($query, [$Date, $blockToCheck, $lastBlockToCheck]);

        foreach ($changes as $change) {
            $block = $change->Block;
            $userName = $change->UserName;
            $type = $change->ChangeType;

            if (strcasecmp($Volunteer, $userName) === 0) {
                $Volunteer = $userName;
            }

            if ($type === "Delete") {
                unset($Schedule[$block][$userName]);
            } elseif ($type === "Add") {
                $Schedule[$block][$userName] = (array)$change;
            }
        }

        return isset($Schedule[$currentBlock][$Volunteer]) ? json_encode("Yes") : null;

    } catch (Exception $e) {
        error_log("Check volunteer error: " . $e->getMessage());
        return null;
    }
}

function getVolunteerData($VolunteerID) {
    try {
        $query = "SELECT 
                    UserID, oncall, Active1, Active2, UserName, ringing, 
                    LoggedOn, TraineeID,
                    (SELECT callStatus 
                     FROM CallRouting 
                     WHERE CallSid = Volunteers.IncomingCallSid 
                     ORDER BY ID DESC 
                     LIMIT 1) as callStatus,
                    (SELECT UserName 
                     FROM Volunteers V3 
                     WHERE V3.LoggedOn = 4 
                     AND V3.TraineeID = Volunteers.UserName) as TrainerID,
                    chatInvite 
                  FROM Volunteers 
                  WHERE UserName = ?";
        
        $result = dataQuery($query, [$VolunteerID]);
        
        if (!empty($result)) {
            $data = (array)$result[0];
            $data['scheduled'] = CheckVolunteer($VolunteerID);
            return $data;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Get volunteer data error: " . $e->getMessage());
        return null;
    }
}

function logoffUser($VolunteerID) {
    try {
        // Delete training session signals
        $filename = dirname(__FILE__) . '/trainingShare/Signals/' . $VolunteerID . ".txt";
        if (file_exists($filename)) {
            $file = fopen($filename, 'c+b');
            flock($file, LOCK_SH);
            fclose($file);
            unlink($filename);
        }

        // Log the logoff
        $query = "INSERT INTO Volunteerlog (UserName, timestamp, status) 
                  VALUES (?, NOW(), 0)";
        dataQuery($query, [$VolunteerID]);

        // Reset volunteer status
        $query = "UPDATE Volunteers
                  SET LoggedOn = 0,
                      Active1 = NULL,
                      Active2 = NULL,
                      OnCall = 0,
                      ChatInvite = NULL,
                      Ringing = NULL,
                      TraineeID = NULL,
                      Muted = 0,
                      IncomingCallSid = NULL
                  WHERE UserName = ?";
        dataQuery($query, [$VolunteerID]);

        // Remove from CallControl table
        $deleteControl = "DELETE FROM CallControl WHERE user_id = ?";
        dataQuery($deleteControl, [$VolunteerID]);

        // Delete volunteer IMs
        $query = "DELETE FROM VolunteerIM 
                  WHERE imTo = ? 
                  OR imFrom = ?";
        dataQuery($query, [$VolunteerID, $VolunteerID]);

    } catch (Exception $e) {
        error_log("Logoff user error: " . $e->getMessage());
    }
}

try {
    // Get active volunteers
    $query = "SELECT 
                UserID, oncall, Active1, Active2, UserName, 
                ringing, LoggedOn, TraineeID,
                (SELECT callStatus 
                 FROM CallRouting 
                 WHERE CallSid = Volunteers.IncomingCallSid 
                 ORDER BY ID DESC 
                 LIMIT 1) as callStatus,
                (SELECT UserName 
                 FROM Volunteers V3 
                 WHERE V3.LoggedOn = 4 
                 AND V3.TraineeID = Volunteers.UserName) as TrainerID,
                chatInvite 
              FROM Volunteers 
              WHERE LoggedOn IN (1, 4, 6, 8) 
              ORDER BY Shift, lastname";
    
    $result = dataQuery($query);

    foreach ($result as $r) {
        $SingleUser = (array)$r;
        $SingleUser['scheduled'] = CheckVolunteer($SingleUser['UserName']);

        if ($SingleUser['TrainerID']) {
            $SingleUser['trainerData'] = getVolunteerData($SingleUser['TrainerID']);
        }

        // Check if user should be logged off
        if (($SingleUser['LoggedOn'] === '1' || 
             $SingleUser['LoggedOn'] === '4' || 
             $SingleUser['LoggedOn'] === '6') &&
            !$SingleUser['oncall'] &&
            !$SingleUser['Active1'] && 
            !$SingleUser['Active2'] && 
            !$SingleUser['chatInvite'] && 
            !$SingleUser['ringing'] && 
            !$SingleUser['scheduled']) {

            if (!$SingleUser['TrainerID'] && $SingleUser['LoggedOn'] != '6') {
                logoffUser($SingleUser['UserName']);
            } elseif ($SingleUser['LoggedOn'] === '6' &&
                      isset($SingleUser['trainerData']) &&
                      !$SingleUser['trainerData']['oncall'] &&
                      !$SingleUser['trainerData']['Active1'] && 
                      !$SingleUser['trainerData']['Active2'] && 
                      !$SingleUser['trainerData']['chatInvite'] && 
                      !$SingleUser['trainerData']['ringing'] && 
                      !$SingleUser['trainerData']['TrainerID'] && 
                      !$SingleUser['trainerData']['scheduled']) {
                logoffUser($SingleUser['UserName']);
            }
        }
    }

} catch (Exception $e) {
    error_log("Volunteer management error: " . $e->getMessage());
}
