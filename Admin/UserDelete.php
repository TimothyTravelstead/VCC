<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

if ($_SESSION['auth'] != 'yes') {
    http_response_code(401); // Set the HTTP status code to 401
    die("Unauthorized"); // Output the error message and terminate the script
}

// Release session lock immediately after reading session data
session_write_close();

// Get the user ID from the request
$IdNum = $_REQUEST['IdNum'];

// Create the query with a parameter placeholder
$query = "DELETE FROM Volunteers WHERE UserID = ?";

// Execute the query with the parameter
$result = dataQuery($query, [$IdNum]);

// The dataQuery function will return true if the deletion was successful
// and false if there was an error
if ($result === false) {
    http_response_code(500);
    echo "Error deleting user";
} else {
    http_response_code(200);
    echo "User deleted successfully";
}
?>
