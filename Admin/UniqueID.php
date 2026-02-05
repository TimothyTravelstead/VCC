<?php
// Include database connection FIRST to set session configuration
require_once('../../private_html/db_login.php');

session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately - this file doesn't need session data
session_write_close();

$UserID = $_REQUEST["UserName"];

$query = "SELECT COUNT(UserID) as count FROM Volunteers WHERE UserName = :username";
$params = [':username' => $UserID];

$result = dataQuery($query, $params);

echo ($result && $result[0]->count == 0) ? "OK" : "DUPLICATE";
?>
