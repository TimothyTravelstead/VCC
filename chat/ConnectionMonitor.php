<?php
register_shutdown_function(callerShutDown);


// Include the mysql databsae location and login information
require_once('../db_login.php');




function callerShutDown() {
	$query "UPDATE chatrequests set VolunteerCounter = 8";
	$result = mysqli_query($connection,$query);
}


$CallerID = $_REQUEST['CallerID'];
set_time_limit(1);


$status = connection_status();
$count = 1;


while ($status == 0) {
	$count = $count + 1;
	$status = connection_status();
}


mysql_close($connection);

?>
