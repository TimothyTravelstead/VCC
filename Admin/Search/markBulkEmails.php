<?php

// Security and configuration headers

require_once('../../../private_html/db_login.php');
session_start();
header('Access-Control-Allow-Origin: https://www.volunteerlogin.org');
header('Content-Type: application/json; charset=utf-8');

// PHP settings
ini_set('memory_limit', '2G');
ignore_user_abort(true);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Get and validate input
$VolunteerID = $_SESSION["UserID"] ?? "Travelstead";
$bulkSetID = $_REQUEST["bulkSetID"] ?? '';
$resources = json_decode($_REQUEST["resources"], true);

// Validate inputs
if (empty($bulkSetID) || !is_array($resources)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid input parameters'
    ]);
    exit;
}

try {
    // Process each resource
    foreach ($resources as $resourceID) {
        // Validate resourceID
        if (!is_string($resourceID) && !is_numeric($resourceID)) {
            continue;
        }

        // Query to insert bulk log entry
        $query = "INSERT INTO bulkLog 
                    (id, UserName, Date, bulkSetID, ResourceID, Status, emailAddress)
                 SELECT 
                    default,
                    :username,
                    default,
                    :bulkSetID,
                    :resourceID,
                    'Queued',
                    (SELECT INTERNET FROM resource WHERE IDNUM = :resourceID2 LIMIT 1)";

        $params = [
            ':username' => $VolunteerID,
            ':bulkSetID' => $bulkSetID,
            ':resourceID' => $resourceID,
            ':resourceID2' => $resourceID  // Need separate parameter for subquery
        ];

        $result = dataQuery($query, $params);

        if ($result === false) {
            throw new Exception("Failed to insert bulk log for resource: $resourceID");
        }
    }

    // Success response
    echo json_encode([
        'status' => 'success',
        'message' => 'OK'
    ]);

} catch (Exception $e) {
    // Error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
