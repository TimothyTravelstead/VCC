<?php
require_once('../../../private_html/db_login2.php');

$userID = 'Travelstead';
$query = "SELECT FirstName, LastName FROM Volunteers WHERE UserName = ?";
$params = [$userID];
$result = dataQuery2($query, $params);
$volunteerName = null;
if ($result && !empty($result)) {
    $volunteerName = $result[0]->FirstName . " " . $result[0]->LastName;
} else {
    error_log("DEBUG: No volunteer record found for userID: " . $userID);
}

// Optionally, you might output the volunteer name to the browser for debugging:
return $volunteerName;
?>