<?php


require_once('../../../private_html/db_login.php');
session_start();
$ZipCode = $_REQUEST["ZipCode"];

// Using prepared statement via dataQuery for security
$query = "SELECT 
            City, 
            State, 
            (SELECT Region 
             FROM Regions 
             WHERE ZipCodesPostal.state = Regions.Abbreviation 
             LIMIT 1) as Region 
          FROM ZipCodesPostal 
          WHERE Zip = ? 
          AND LocationType = 'PRIMARY' 
          LIMIT 1";

$result = dataQuery($query, [$ZipCode]);

$data = [];

if (!$result) {
    $data['status'] = 'None';
} else {
    $data['status'] = 'OK';
    $data['City'] = $result[0]->City;
    $data['State'] = $result[0]->State;
    $data['Region'] = $result[0]->Region;
}

echo json_encode($data);
?>
