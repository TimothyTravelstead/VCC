<?php

// Include the mysql databsae location and login information




require_once('../../private_html/db_login.php');
session_start();
session_write_close();

$VolunteerID = $_REQUEST["VolunteerID"] ?? null;


//Define the Arrays to hold the results and fill the arrays with the results
	$CallerID = array();

	
//CHECK FOR ENDED CHATS
	header("Content-Type: text/xml");
	echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
	echo "<responses>";
	
	$query="SELECT CallerID, Status from ChatRequests WHERE VolunteerID = '".$VolunteerID."'"; 
	$result = mysqli_query($connection,$query);

	if(!$result) {
		die ("Could not query the database: <br />". mysqli_error());
	}
	
	$Count = 1;
	//Post Status as Record if No Messages and Status Indicates Chat Has Ended
	while ($result_row = mysqli_fetch_row(($result))) {
		$Name[$Count] = 				$result_row[0];
		$Room[$Count] = 				$result_row[1];
		$Text[$Count] = 				$result_row[2];
		$MessageNo[$Count] =	 		$result_row[3];
		$Count = $Count + 1;
	}
	
	$FoundCount = $Count;
	$Count = 1;
	
	//Write the Resulting Records
	while ($Count < $FoundCount) {
		echo "<item>";
		echo "<CallerID>".$CallerID[$Count]."</CallerID>";
		echo "<Status>".$Status[$Count]."</Status>";
		echo "</item>";
		$Count = $Count + 1;
	}
		
		//Update Message Session Variable to Show Messages Already Pulled
	
	echo "</responses>";
	
mysql_close($connection);


?>