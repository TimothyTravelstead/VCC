<?php

require_once('../../private_html/db_login.php');
session_start();

// Require authentication
requireAuth();

session_write_close(); // Prevent session locking for background requests

// Include database configuration

// Get and validate volunteer ID
$VolunteerID = $_REQUEST['volunteerID'] ?? $_SESSION['UserID'] ?? 'testingID';

// Get schedule modification data
$Day = $_REQUEST['day'] ?? '';
$Block = $_REQUEST['block'] ?? '';
$Type = $_REQUEST['type'] ?? '';
$StartDate = $_REQUEST['startDate'] ?? '';
$EndDate = $_REQUEST['endDate'] ?? '';
$ShiftType = $_REQUEST['shiftType'] ?? '';
$ShiftLocation = $_REQUEST['shiftLocation'] ?? '';

// Validate required fields
if (empty($Day) || empty($Block) || empty($Type) || empty($StartDate) || empty($EndDate)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing required fields',
        'status' => 'error'
    ]);
    exit;
}

$lastQuery = ''; // Store the last executed query for output

try {
    if ($Type == "Delete") {
        // Delete schedule entry
        $query = "DELETE FROM volunteerSchedule 
                 WHERE Day = ? 
                 AND Block = ? 
                 AND UserName = ? 
                 AND StartDate = ? 
                 AND EndDate = ?";
                 
        $result = dataQuery($query, [
            $Day,
            $Block,
            $VolunteerID,
            $StartDate,
            $EndDate
        ]);
        
        $lastQuery = $query;
        
    } elseif ($Type == "Add") {
        // Update any existing schedules that overlap
        $query = "UPDATE volunteerSchedule 
                 SET EndDate = ? 
                 WHERE Day = ? 
                 AND Block = ? 
                 AND UserName = ? 
                 AND EndDate > ?";
                 
        $result = dataQuery($query, [
            $StartDate,
            $Day,
            $Block,
            $VolunteerID,
            $StartDate
        ]);
        
        // Insert new schedule entry
        $query = "INSERT INTO volunteerSchedule 
                 (Day, Block, UserName, StartDate, EndDate, ShiftType, ShiftLocation) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
                 
        $result = dataQuery($query, [
            $Day,
            $Block,
            $VolunteerID,
            $StartDate,
            $EndDate,
            $ShiftType,
            $ShiftLocation
        ]);
        
        $lastQuery = $query;
    }

    // Return success response with the last executed query
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => ($Type == 'Delete' ? 'Schedule deleted' : 'Schedule added'),
        'query' => $lastQuery
    ]);
    
} catch (Exception $e) {
    // Handle any errors
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Database operation failed',
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
