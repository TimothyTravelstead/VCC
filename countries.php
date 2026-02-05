<?php
// Include database connection and functions
require_once('../private_html/db_login.php');

try {
    // Get unique countries from active resources
    $query = "SELECT DISTINCT TRIM(Country) as country 
              FROM resource 
              WHERE Closed = 'N' 
              GROUP BY country 
              ORDER BY Country";
              
    $result = dataQuery($query);

    // Extract country names into array
    $countries = array_map(function($row) {
        return $row->country;
    }, $result);

    // Set JSON content type header
    header('Content-Type: application/json');
    
    // Output JSON encoded array
    echo json_encode($countries);

} catch (Exception $e) {
    // Log error and return empty array
    error_log("Countries query error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([]);
}
