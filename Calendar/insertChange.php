<?php

require_once('../../private_html/db_login.php');
session_start();

// Require authentication
requireAuth();

session_write_close(); // Prevent session locking for background requests

// Include database configuration

// Get and validate volunteer ID
$VolunteerID = $_REQUEST['volunteerID'] ?? $_SESSION['UserID'] ?? 'testingID';

// Get schedule change data
$Date = $_REQUEST['date'] ?? '';
$Block = $_REQUEST['block'] ?? '';
$Type = $_REQUEST['type'] ?? '';
$ShiftType = $_REQUEST['shiftType'] ?? '';
$ShiftLocation = $_REQUEST['shiftLocation'] ?? '';

// Validate required fields
if (empty($Date) || empty($Block) || empty($Type)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Insert schedule change
$query = "INSERT INTO volunteerScheduleChanges 
          (Date, Block, Type, UserName, ShiftType, ShiftLocation) 
          VALUES (?, ?, ?, ?, ?, ?)";

$result = dataQuery($query, [
    $Date,
    $Block,
    $Type,
    $VolunteerID,
    $ShiftType,
    $ShiftLocation
]);

if ($result === false) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to insert schedule change']);
    exit;
}

// Return success response
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Schedule change inserted successfully']);
