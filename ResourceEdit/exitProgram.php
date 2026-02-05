<?php


require_once('../../private_html/db_login.php');
session_start();
if ($_SESSION['auth'] != 'yes') {
    die("Unauthorized");
}


$query = "DELETE FROM resourceReview WHERE ModifiedTime IS NULL";
$result = dataQuery($query);

if ($result) {
    echo "OK";
} else {
    echo "Error deleting unmodified reviews";
}
?>
