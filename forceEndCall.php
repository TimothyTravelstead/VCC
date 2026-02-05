<?php
require_once('../private_html/db_login.php');
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

include('Services/Twilio.php');

// Fetch Twilio credentials from environment variables
$accountSid = getenv('TWILIO_ACCOUNT_SID') ?: 'default_account_sid';
$authToken = getenv('TWILIO_AUTH_TOKEN') ?: 'default_auth_token';
$appSid = getenv('TWILIO_APP_SID') ?: 'default_app_sid';
$username = $_SESSION["UserID"] ?: 'TimmyTesting';

// Release session lock after reading session data
session_write_close();

$Conference = $_REQUEST['conference'] ?? null;
$Participant = $_REQUEST['participant'] ?? null;


//	require('twilio-php-master/Twilio/autoload.php');
	require('twilio-php-main/src/Twilio/autoload.php');
	use \Twilio\Rest\Client;
	$client = new Client($accountSid, $authToken);
 
	$client->conferences($Conference)->participants($Participant)->delete();

?>
