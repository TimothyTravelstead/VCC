<?php

// Check authentication

require_once '../private_html/db_login.php';
session_start();
if (!isset($_SESSION['auth']) || $_SESSION['auth'] != 'yes') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include database connection

// Get username from request
$username = $_POST['username'] ?? $_GET['username'] ?? '';

if (empty($username)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username required']);
    exit;
}

// Query user details using existing dataQuery function
$query = "SELECT UserName, firstname, lastname, LoggedOn FROM volunteers WHERE UserName = ?";
$result = dataQuery($query, [$username]);

header('Content-Type: application/json');

if (empty($result)) {
    echo json_encode(['error' => 'User not found']);
} else {
    $user = $result[0];
    echo json_encode([
        'username' => $user->UserName,
        'name' => trim($user->firstname . ' ' . $user->lastname),
        'firstname' => $user->firstname,
        'lastname' => $user->lastname,
        'loggedOn' => $user->LoggedOn,
        'isOnline' => ($user->LoggedOn > 0)
    ]);
}
?>