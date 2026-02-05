<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Authentication check
if ($_SESSION['auth'] != 'yes') {
    http_response_code(401);
    die("Unauthorized");
}

// Release session lock immediately after reading session data
session_write_close();

// Get the user ID from the request
$UserID = $_REQUEST["UserID"];

// Create the query with a parameter placeholder
$query = "SELECT 
    UserID,
    FirstName,
    LastName,
    skypeID,
    UserName,
    Volunteer,
    ResourceOnly,
    AdminUser,
    Trainer,
    Monitor,
    Trainee,
    Office,
    PreferredHotline,
    Desk,
    AdminResources,
    groupChatMonitor,
    resourceAdmin 
FROM Volunteers 
WHERE UserID = ?";

// Execute the query with the parameter
$result = dataQuery($query, [$UserID]);

if ($result === false) {
    // Handle database error
    http_response_code(500);
    die(json_encode(['error' => 'Database error occurred']));
}

if (empty($result)) {
    // Handle case where no user was found
    http_response_code(404);
    die(json_encode(['error' => 'User not found']));
}

// Map the results to the expected array structure
// We'll use the first (and should be only) result
$user = $result[0];
$User = [
    'IdNum' => $user->UserID,
    'FirstName' => $user->FirstName,
    'LastName' => $user->LastName,
    'skypeID' => $user->skypeID,
    'UserID' => $user->UserName,
    'Volunteer' => $user->Volunteer,
    'ResourceOnly' => $user->ResourceOnly,
    'AdminUser' => $user->AdminUser,
    'Trainer' => $user->Trainer,
    'Monitor' => $user->Monitor,
    'Trainee' => $user->Trainee,
    'Location' => $user->Office,
    'PreferredHotline' => $user->PreferredHotline,
    'CallerType' => $user->Desk,
    'AdminResources' => $user->AdminResources,
    'GroupChat' => $user->groupChatMonitor,
    'ResourceAdmin' => $user->resourceAdmin
];

// Set proper JSON content type header
header('Content-Type: application/json');

// Output the JSON encoded user data
echo json_encode($User);
?>
