<?php
// Include db_login.php FIRST (sets session configuration)
require_once '../../../private_html/db_login.php';

session_start();
session_write_close(); // Prevent session locking for background requests

// Get NotesData
	$Notes1 = $_REQUEST['notes1'];
	$Notes2 = $_REQUEST['notes2'];
	$Notes3 = $_REQUEST['notes3'];

	file_put_contents("CalendarNotes1", $Notes1); 
	file_put_contents("CalendarNotes2", $Notes2); 
	file_put_contents("CalendarNotes3", $Notes3); 

echo "OK";



?>	



