<?php


// Include the mysql databsae location and login information




require_once('../../private_html/db_login.php');
session_start();
session_write_close();

$CallerID = $_REQUEST["CallerID"] ?? null;

$query = "DELETE from ChatRequests WHERE CallerID = \"".$CallerID."\"";
$result = mysqli_query($connection,$query);

$query2 = "DELETE from chat WHERE CallerID = \"".$CallerID."\"";
$result2 = mysqli_query($connection,$query2);
mysql_close($connection);


?>


