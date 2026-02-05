<?php

// Set cache control headers

require_once('../../../private_html/db_login.php');
session_start();
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Content-Type: application/json"); // Changed to JSON content type


$query = "SELECT mytype FROM Types ORDER BY mytype";
$results = dataQuery($query);

if ($results === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed']);
    exit;
}

// Convert the results to a simple indexed array
$types = array_map(function($row) {
    return $row->mytype;
}, $results);

// Start array at index 1 to match original behavior
array_unshift($types, null); // Add null at index 0
unset($types[0]); // Remove the null value but keep indexing from 1

echo json_encode($types);
?>


