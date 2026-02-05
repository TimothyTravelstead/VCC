<?php

// Include the mysql databsae location and login information


require_once('../../private_html/db_login.php');
session_start();
$CallerID = $_REQUEST["CallerID"] ?? null;
$LastMessage = $_REQUEST["LastMessage"] ?? null;
if(!$LastMessage) {
	$LastMessage = 0;
}



$query="SELECT UserID from Volunteers WHERE Active1 = '".$CallerID."' OR Active2 = '".$CallerID."'"; 
$result = mysqli_query($connection,$query);


while ($result_row = mysqli_fetch_row(($result))) {
	$accepted =		$result_row[0];
}

if(!$accepted) {
	session_write_close();
	echo "no";
} else {
	$_SESSION['auth'] = 'yes';
	session_write_close();
	echo "connected";
}

mysql_close($connection);

?>