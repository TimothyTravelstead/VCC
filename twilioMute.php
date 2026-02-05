<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours
require_once('../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

// Read all needed session data
$username = $_SESSION["UserID"] ?? 'TimmyTesting';
$ConferenceSid = $_SESSION['ConferenceSid'] ?? null;
$CallSid = $_SESSION['CallSid'] ?? null;

// Get request parameter
$Mute = $_REQUEST['muted'] ?? null;

// Release session lock immediately after reading session data
session_write_close();

// Fetch Twilio credentials from environment variables
$accountSid = getenv('TWILIO_ACCOUNT_SID') ?: 'default_account_sid';
$authToken = getenv('TWILIO_AUTH_TOKEN') ?: 'default_auth_token';
$appSid = getenv('TWILIO_APP_SID') ?: 'default_app_sid'; 
 
require('twilio-php-main/src/Twilio/autoload.php');
use \Twilio\Rest\Client;
$client = new Client($accountSid, $authToken);
	

$participant = $client->conferences($ConferenceSid)->participants($CallSid)->fetch();

if($Mute == 'true') {
	$participant->update(array(
			"Muted" => "True"
		));
} elseif($Muted == 'false') {
	$participant->update(array(
			"Muted" => "False"
		));
}

echo $participant->muted;

?>
