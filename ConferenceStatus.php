<?php


require_once('../private_html/db_login.php');
session_start();

// Read session data
$username = $_SESSION["UserID"] ?? 'TimmyTesting';

// Release session lock immediately after reading session data
session_write_close();

// Load Twilio library
require('twilio-php-main/src/Twilio/autoload.php');
use \Twilio\Rest\Client;

// Fetch Twilio credentials from environment variables
$accountSid = getenv('TWILIO_ACCOUNT_SID') ?: 'default_account_sid';
$authToken = getenv('TWILIO_AUTH_TOKEN') ?: 'default_auth_token';
$appSid = getenv('TWILIO_APP_SID') ?: 'default_app_sid';

$client = new Client($accountSid, $authToken);
	
$call = $client->calls($CallSid)->fetch();
 
// Loop over the list of conferences and echo a property for each one
foreach ($client->conferences->read(array(
    	"FriendlyName" => "Travelstead",
    	"DateCreated>" => "2014-12-14"
    ), 50) as $conference
) {
    echo $conference->sid;
}

?>
