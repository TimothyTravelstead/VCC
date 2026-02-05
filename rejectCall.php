<?php


require_once '../private_html/db_login.php';
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

include('Services/Twilio.php');

// Fetch Twilio credentials from environment variables
  $accountSid = getenv('TWILIO_ACCOUNT_SID') ?: 'default_account_sid';
  $authToken = getenv('TWILIO_AUTH_TOKEN') ?: 'default_auth_token';
  $appSid = getenv('TWILIO_APP_SID') ?: 'default_app_sid';
  $username = $_SESSION["UserID"] ?: 'TimmyTesting';
  


// Construct the full URL for Twilio redirect
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$baseDir = dirname($_SERVER['PHP_SELF']);
$redirectURL = $protocol . $host . $baseDir . "/twilioRedirect.php";

$VolunteerID = $_SESSION['UserID'];
$CallSid = $_REQUEST['clientCallerSid'];

// Release session lock after reading session data
session_write_close();

if(!$VolunteerID) {
    $VolunteerID = 'brokenUserID';
}

// Update Volunteer Table to Show Volunteer Rejected this Call
$query = "UPDATE CallRouting SET Volunteer = ? WHERE CallSid = ? AND Volunteer IS NULL";
$result = dataQuery($query, [$VolunteerID, $CallSid]);

// Examine Volunteer to Determine If Volunteer Answered Call First
$query = "SELECT Volunteer FROM CallRouting WHERE CallSid = ?";
$result = dataQuery($query, [$CallSid]);

if ($result && count($result) > 0) {
    $AnsweringVolunteer = $result[0]->Volunteer;
    
    if($AnsweringVolunteer != $Volunteer) {
        die('Call Already Answered By Another Volunteer.');
    }
}

// Twilio API call handling
require('twilio-php-main/src/Twilio/autoload.php');
use \Twilio\Rest\Client;

$client = new Client($accountSid, $authToken);

try {
    $call = $client->calls($CallSid)->fetch();
    $call->update([
        "Url" => $redirectURL,
        "Method" => "POST"
    ]);
} catch (Services_Twilio_RestException $e) {
    echo "Caller Error:" . $e->getMessage();
}

// **CLEAR RINGING STATE AND REFRESH CACHE FOR POLLING CLIENTS**
try {
    require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
    $publisher = new VCCFeedPublisher();
    $publisher->clearRinging($VolunteerID);
    $publisher->refreshUserListCache();
} catch (Exception $e) {
    error_log("VCCFeedPublisher error on call reject: " . $e->getMessage());
}

echo "OK";
