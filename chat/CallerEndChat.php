<?php

// Include the mysql databsae location and login information



require_once('../../private_html/db_login.php');
session_start();
session_write_close();

$CallerID = $_REQUEST["CallerID"];

	
$query="UPDATE chatrequests SET Status = 6 WHERE CallerID = '".$CallerID."'"; 
$result = mysqli_query($connection,$query);

if(!$result) {
	die ("Could not query the database: <br />". mysqli_error());
}
	
mysql_close($connection);

?>