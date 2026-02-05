<?php


require_once('../../private_html/db_login.php');
session_start();
if ($_SESSION['auth'] != 'yes') {
    die("Unauthorized");
} 


$IdNum = trim($_REQUEST['idNum']); 
$websiteUpdateDate = trim($_REQUEST['webSiteUpdateDate']); 
$websiteUpdateType = trim($_REQUEST['webSiteUpdateType']); 

$query = "UPDATE resourceReview 
          SET webSiteUpdateDate = ?, 
              webSiteUpdateType = ? 
          WHERE IDNUM = ?";

$result = dataQuery($query, [
    $websiteUpdateDate,
    $websiteUpdateType,
    $IdNum
]);

if ($result) {
    echo "OK";
} else {
    echo "Error updating resource review";
}
?>
