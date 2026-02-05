<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Release session lock immediately - this script doesn't read/write session data
session_write_close();

// Get the ID with proper type checking
$IdNum = isset($_REQUEST["idnum"]) ? $_REQUEST["idnum"] : '';

if (empty($IdNum)) {
    http_response_code(400);
    echo "Error: Invalid ID";
    exit;
}

// Use parameter binding for safe deletion
$query = "DELETE FROM `resource` WHERE IDNUM = :idnum";
$params = [':idnum' => $IdNum];

$result = dataQuery($query, $params);

if ($result) {
    echo "OK";
} else {
    http_response_code(500);
    echo "Error: Delete failed";
}
?>
