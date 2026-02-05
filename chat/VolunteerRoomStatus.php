<?php

// Include the mysql databsae location and login information
require_once('../db_login.php');


$Room = $_REQUEST["Room"] ?? null;
$VolunteerID = $_REQUEST["VolunteerID"] ?? null;
	
$query="SELECT Active".$Room." from Volunteers WHERE UserName = '".$VolunteerID."' and LoggedOn = 1"; 
$result = mysqli_query($connection,$query);

$num_rows = mysqli_num_rows($result);
if($num_rows == 0) {
	echo "ERROR";
} else {
	while ($result_row = mysqli_fetch_row(($result))) {
		$CallerID =	$result_row[0];
	}	
	if (!$CallerID) {
		echo "Open";
	} else if ($CallerID == "End") {
		echo "Closed";
	} else {
		echo "Active";
	}
}
	
mysql_close($connection);


?>