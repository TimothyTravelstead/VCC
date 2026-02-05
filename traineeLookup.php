<?php
/**
 * traineeLookup.php - Returns list of users with trainee permission
 *
 * IMPORTANT: This file is called during the login flow BEFORE the user is authenticated.
 * When a user selects "Trainer" from the login type dropdown, this endpoint is called
 * to populate the trainee selection list. Authentication is NOT required here because:
 * 1. This is called BEFORE login credentials are submitted
 * 2. It only returns non-sensitive data (names of users with trainee flag)
 * 3. Similar to Calendar/getFutureVolunteerSchedule.php pattern documented in CLAUDE.md
 *
 * DO NOT add requireAuth() - it will break trainer login flow.
 */

require_once('../private_html/db_login.php');

// No session needed - this is a pre-login request
// No authentication - called before user logs in

$query = "SELECT FirstName, LastName, UserName FROM Volunteers WHERE Trainee = ?";
$result = dataQuery($query, ['1']);

if (!$result || empty($result)) {
    die("No Data");
}

$Users = [];
foreach ($result as $row) {
    $User = [
        "firstName" => $row->FirstName,
        "lastName" => $row->LastName,
        "userName" => $row->UserName
    ];
    $Users[] = $User;
}

echo json_encode($Users) . "\n\n";
