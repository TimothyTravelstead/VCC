<?php

// Handle URL-encoded data instead of raw JSON

require_once('../private_html/db_login.php');
session_start();
$UserID = $_POST['UserID'] ?? null;

if ($UserID === null) {
    http_response_code(400);
    echo json_encode(['error' => 'UserID is required']);
    exit;
}

// User details query with proper parameter binding
$query = "SELECT 
    Volunteer,
    ResourceOnly,
    AdminUser,
    Trainer,
    Monitor,
    Trainee,
    UserName,
    Desk AS CallerType,
    AdminResources,
    groupChatMonitor,
    resourceAdmin 
FROM Volunteers 
WHERE UserName = :userId";

$params = array(':userId' => $UserID);
$result = dataQuery($query, $params);

$User = array();

if (!empty($result)) {
    $row = $result[0];
    $User = array(
        'Volunteer' => $row->Volunteer,
        'ResourceOnly' => $row->ResourceOnly,
        'AdminUser' => $row->AdminUser,
        'Trainer' => $row->Trainer,
        'Monitor' => $row->Monitor,
        'Trainee' => $row->Trainee,
        'UserName' => $row->UserName,
        'CallerType' => $row->CallerType,
        'AdminResources' => $row->AdminResources,
        'groupChatMonitor' => $row->groupChatMonitor,
        'ResourceAdmin' => $row->resourceAdmin
    );

    // Get last logon time
    $query2 = "SELECT EventTime 
        FROM Volunteerlog 
        WHERE UserID = :userId 
        AND LoggedOnStatus > 0 
        ORDER BY EventTime DESC 
        LIMIT 1";
    
    $params2 = array(':userId' => $UserID);
    $result2 = dataQuery($query2, $params2);

    if (!empty($result2)) {
        $_SESSION['lastLogon'] = strtotime($result2[0]->EventTime);
    }
}

// Release session lock immediately after writing session data
session_write_close();

// Output the results as JSON
header('Content-Type: application/json');
echo json_encode($User) . "\n\n";
?>
