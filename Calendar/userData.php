<?php

require_once('../../private_html/db_login.php');
session_start();

// Require authentication
requireAuth();

session_write_close(); // Prevent session locking for background requests

// Include database configuration

// Query to fetch user information
$query = "SELECT 
    UserName,
    CONCAT(FirstName, ' ', LastName) as FullName,
    Type,
    CONCAT(LEFT(FirstName, 1), LEFT(LastName, 1)) as Initials,
    LastName,
    FirstName,
    Office as Location,
    Trainer,
    ResourceOnly,
    Volunteer,
    LoggedOn
FROM Volunteers 
ORDER BY LastName, FirstName";

$result = dataQuery($query);
$Users = [];

if ($result) {
    foreach ($result as $row) {
        $Users[$row->UserName] = [
            'UserName' => $row->UserName,
            'FullName' => $row->FullName,
            'Type' => $row->Type,
            'Initials' => $row->Initials,
            'LastName' => $row->LastName,
            'FirstName' => $row->FirstName,
            'Location' => $row->Location,
            'Trainer' => $row->Trainer,
            'ResourceOnly' => $row->ResourceOnly,
            'Volunteer' => $row->Volunteer,
            'LoggedOn' => $row->LoggedOn,
            'ListName' => $row->LastName . ", " . $row->FirstName
        ];
    }
}

// Set JSON header and output response
header('Content-Type: application/json');
echo json_encode($Users);
