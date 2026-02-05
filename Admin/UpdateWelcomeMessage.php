<?php
// Include database connection FIRST to set session configuration
require_once('../../private_html/db_login.php');

session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately - this file doesn't need session data
session_write_close();

$Message = trim($_REQUEST['Message']);

$query = "UPDATE SystemMessages SET text = :message WHERE Status = 9";
$params = [':message' => $Message];

$result = dataQuery($query, $params);

if ($result === false) {
    die("Update failed");
}

echo "OK";
?>
