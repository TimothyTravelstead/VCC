<?php



require_once('../../../private_html/db_login.php');
session_start();
$VolunteerID = $_SESSION["UserID"];

$query = "INSERT INTO bulkSets 
          (id, UserName, Date, ResourcesIncluded, ResourcesEmailed, ResourcesErrors, Status) 
          VALUES 
          (default, :volunteerId, default, 0, 0, 0, 'New')";

$params = [':volunteerId' => $VolunteerID];

$result = dataQuery($query, $params);

// Note: dataQuery doesn't provide direct access to last insert ID
// We need to make a separate query to get it
if ($result) {
    $lastIdQuery = "SELECT LAST_INSERT_ID() as id";
    $lastIdResult = dataQuery($lastIdQuery);
    
    if ($lastIdResult && isset($lastIdResult[0]->id)) {
        echo $lastIdResult[0]->id;
    } else {
        echo "ERROR";
    }
} else {
    echo "ERROR";
}

exit();
?>
