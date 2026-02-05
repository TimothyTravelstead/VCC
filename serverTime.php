<?php
require_once('../private_html/db_login.php');
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

// Allow CORS
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');    
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
	header("Access-Control-Allow-Headers: Cache-Control");

}   


// Access-Control headers are received during OPTIONS requests




if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Headers: Cache-Control");
}



	define('TIMEZONE', 'America/Los_Angeles');
	date_default_timezone_set(TIMEZONE);

	$closeTime = new DateTime();
	$closeTime->setTime(18,2);
		
	$currentTime = new DateTime();
	
	$timeRemaining = $closeTime->diff($currentTime);
	$hours = $timeRemaining->format('%h');
	$minutes = $timeRemaining->format('%i');
	$seconds = $timeRemaining->format('%s');
	
	$elapsedMinutes = ($hours * 60) + $minutes;
	$elapsedSeconds = ($hours * 60 * 60) + ($minutes * 60) + $seconds;
	$milliseconds = $elapsedSeconds * 1000;
	if($currentTime > $closeTime) {
		$milliseconds = (24*3600*1000) - $milliseconds;
	}
	
	echo $milliseconds;
?>