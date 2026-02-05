<?php
// Simple session setup for testing PHP polling system
session_start();

$userId = $_GET['userId'] ?? 'TestUser1';
$role = $_GET['role'] ?? 'trainer';

// Set up test session
$_SESSION['auth'] = 'yes';
$_SESSION['UserID'] = $userId;

// Set role-specific session variables
if ($role === 'trainer') {
    $_SESSION['trainer'] = '1';
    $_SESSION['trainee'] = '';
    $_SESSION['LoggedOn'] = 4; // Trainer logged on status
} else {
    $_SESSION['trainer'] = '';
    $_SESSION['trainee'] = '1';
    $_SESSION['LoggedOn'] = 6; // Trainee logged on status
}

echo json_encode([
    'status' => 'success',
    'message' => "Test session created for $userId as $role",
    'session' => [
        'UserID' => $_SESSION['UserID'],
        'trainer' => $_SESSION['trainer'],
        'trainee' => $_SESSION['trainee'],
        'LoggedOn' => $_SESSION['LoggedOn'],
        'role' => $role,
        'auth' => $_SESSION['auth']
    ]
]);
?>