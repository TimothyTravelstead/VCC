<?php

	$Slides = array();

	foreach(glob('./Images/Avatars/*.*') as $filename){
	
		array_push($Slides, $filename);
	 }

	echo json_encode($Slides);
	

?>