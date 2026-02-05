<?php


require_once('../../private_html/db_login.php');
session_start();
if ($_SESSION['auth'] != 'yes') {
    die("Unauthorized");
}


$resourceID = $_REQUEST["idNum"];
$UserID = $_SESSION['UserID'] ?: 'Travelstead';
$admin = $_SESSION['editResources'] == "user" ? false : true;

if ($admin) {
    // Use prepared statements with dataQuery for security
    $deleteQuery = "DELETE FROM resourceReview WHERE IDNUM = ?";
    $deleteResult = dataQuery($deleteQuery, [$resourceID]);
    
    if ($deleteResult) {
        // Log the deletion action
        $logQuery = "INSERT INTO resourceEditLog (UserName, resourceIDNUM, Action) VALUES (?, ?, 'Rejected')";
        $logResult = dataQuery($logQuery, [$UserID, $resourceID]);
        
        if (!$logResult) {
            die("Error: Failed to log resource review deletion");
        }
    } else {
        die("Error: Failed to delete resource review");
    }
    
    // Return the executed query for debugging (consider removing in production)
    echo $deleteQuery;
}
?>
