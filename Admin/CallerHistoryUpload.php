<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Verify authentication
if (@$_SESSION["auth"] != "yes") {
    http_response_code(403);
    die("Unauthorized");
}

// Release session lock immediately after reading session data
session_write_close();

// Verify file upload
if (!isset($_FILES["CallerHistory"]) || !is_uploaded_file($_FILES["CallerHistory"]["tmp_name"])) {
    http_response_code(400);
    die("No file uploaded or invalid upload");
}

// Validate file type and size
$allowedMimeTypes = ['text/csv', 'application/csv', 'application/vnd.ms-excel'];
if (!in_array($_FILES["CallerHistory"]["type"], $allowedMimeTypes)) {
    http_response_code(400);
    die("Invalid file type. Only CSV files are allowed.");
}

try {
    // First, clear the existing data
    $deleteQuery = "DELETE FROM CallerHistory";
    $deleteResult = dataQuery($deleteQuery);
    
    if ($deleteResult === false) {
        throw new Exception("Failed to clear existing data");
    }
    
    // Now load the new data
    // Note: We need to use a raw query here as PDO doesn't directly support LOAD DATA LOCAL INFILE
    // We'll still use dataQuery but with the raw SQL
    $loadQuery = "LOAD DATA LOCAL INFILE :filename 
                  INTO TABLE CallerHistory
                  FIELDS TERMINATED BY ',' 
                  ENCLOSED BY '\"' 
                  LINES TERMINATED BY '\r'";
                  
    $loadResult = dataQuery($loadQuery, [
        'filename' => $_FILES["CallerHistory"]["tmp_name"]
    ]);
    
    if ($loadResult === false) {
        throw new Exception("Failed to import data");
    }
    
    // Redirect on success
    header("Location: index.php");
    exit();
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in caller history import: " . $e->getMessage());
    
    // Return error to user
    http_response_code(500);
    die("Error processing file: " . htmlspecialchars($e->getMessage()));
}
?>
