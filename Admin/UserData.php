<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../private_html/db_login.php');
include('../../private_html/csrf_protection.php');

// Now start the session with the correct configuration
session_start();

if ($_SESSION['auth'] != 'yes') {
//    http_response_code(401); // Set the HTTP status code to 401
//    die("Unauthorized"); // Output the error message and terminate the script
}

// Validate CSRF token for POST operations only (GET requests use URL parameters)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRFToken($_REQUEST);
}

// Release session lock after reading session data
session_write_close();

// Get and process input parameters
$params = [
    ':FirstName' => $_REQUEST['FirstName'] ?? '',
    ':LastName' => $_REQUEST['LastName'] ?? '',
    ':SkypeID' => $_REQUEST['skypeID'] ?? '',
    ':UserID' => $_REQUEST['UserID'] ?? '',
    ':Location' => $_REQUEST['Location'] ?? '',
    ':PreferredHotline' => $_REQUEST['PreferredHotline'] ?? '',
    ':CallerType' => $_REQUEST['CallerType'] ?? ''

];



// Process boolean fields
$boolFields = [
    'Volunteer', 'ResourcesOnly', 'AdminUser', 'AdminResources',
    'Trainer', 'Monitor', 'groupChat', 'Trainee', 'ResourceAdmin'
];

foreach ($boolFields as $field) {
    // Only process if the field is explicitly provided in the request
    // This prevents accidentally clearing permissions when updating other fields
    if (isset($_REQUEST[$field])) {
        $value = strtolower($_REQUEST[$field]);
        $params[':' . $field] = ($value === 'true' || $value === '1' || $value === 1) ? 1 : 0;
    } else {
        // Field not provided - preserve existing value by not including in params
        // For INSERT operations, we'll set defaults below
        $params[':' . $field] = null; // Will be handled differently for INSERT vs UPDATE
    }
}

// Set default values
$Type = $_REQUEST['Type'] ?? 1;
$Hotline = 1;
$Shift = 0;
$IdNum = $_REQUEST['IdNum'] ?? '';

if ($IdNum == 'New') {
    // For new users, default boolean fields to 0 if not provided
    foreach ($boolFields as $field) {
        if ($params[':' . $field] === null) {
            $params[':' . $field] = 0;
        }
    }

    $query = "INSERT INTO volunteers (
        UserId, Type, FirstName, LastName, UserName, Password, LoggedOn,
        Office, Desk, Shift, Hotline, OnCall,
        Active1, Active2, ChatOnly, oneChatOnly,
        Trainer, Monitor, ResourceOnly, AdminUser, Volunteer, Trainee,
        skypeID, PreferredHotline, AdminResources, groupChatMonitor, resourceAdmin
    ) VALUES (
        NULL, :Type, :FirstName, :LastName, :UserID, :Password, 0,
        :Location, :CallerType, :Shift, :Hotline, 0,
        NULL, NULL, 0, 0,
        :Trainer, :Monitor, :ResourcesOnly, :AdminUser, :Volunteer, :Trainee,
        :SkypeID, :PreferredHotline, :AdminResources, :groupChat, :ResourceAdmin
    )";

    // Add fixed values to params
    $params[':Password'] = $_REQUEST['Password'];
    $params[':Shift'] = $Shift;
    $params[':Hotline'] = $Hotline;
    $params[':Type'] = $Type;

} else {
    // For updates, build dynamic query to only update provided fields
    $params[':WhereUserID'] = $_REQUEST['IdNum'];

    $updateFields = [
        'FirstName = :FirstName',
        'LastName = :LastName',
        'Office = :Location',
        'UserName = :UserID',
        'Type = :Type',
        'Hotline = :Hotline',
        'Shift = :Shift',
        'Desk = :CallerType',
        'skypeID = :SkypeID',
        'PreferredHotline = :PreferredHotline'
    ];

    // Only update boolean fields that were explicitly provided
    foreach ($boolFields as $field) {
        if ($params[':' . $field] !== null) {
            $columnName = ($field === 'ResourcesOnly') ? 'ResourceOnly' : $field;
            $columnName = ($field === 'groupChat') ? 'groupChatMonitor' : $columnName;
            $columnName = ($field === 'ResourceAdmin') ? 'resourceAdmin' : $columnName;
            $updateFields[] = "$columnName = :$field";
        } else {
            // Remove null params to avoid errors
            unset($params[':' . $field]);
        }
    }

    $query = "UPDATE Volunteers SET " . implode(", ", $updateFields);

    // Handle password parameter properly
    if (!empty($_REQUEST['Password'])) {
        $query .= ", Password = :Password";
        $params[':Password'] = $_REQUEST['Password'];
    }

    $query .= " WHERE UserID = :WhereUserID";

    // Add additional parameters for update
    $params[':Type'] = $Type;
    $params[':Shift'] = $Shift;
    $params[':Hotline'] = $Hotline;
}



$result = dataQuery($query, $params);

if (is_array($result) && isset($result['error']) && $result['error'] === true) {
    echo "Database Error: " . $result['message'] . "\n\n";
    echo "Query: " . $result['query'] . "\n\n";
    echo "Params: ";
    print_r($result['params']);
    die();
} 

echo "OK";
?>
