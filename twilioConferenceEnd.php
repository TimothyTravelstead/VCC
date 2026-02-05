<?php

// Include the database connection and functions


require_once('../private_html/db_login.php');
session_start();
$VolunteerID = $_SESSION['UserID'];

// Release session lock after reading session data
session_write_close();

if(!$VolunteerID) {
    $VolunteerID = 'brokenUserID';
}

// Update volunteer status
$query4 = "UPDATE Volunteers SET 
    OnCall = 0, 
    Ringing = 'Logging', 
    HotlineName = NULL, 
    CallCity = NULL, 
    CallState = NULL, 
    CallZip = NULL, 
    IncomingCallSid = NULL 
    WHERE UserName = ?";
$result4 = dataQuery($query4, [$VolunteerID]);

// Update trainee status
$query5 = "UPDATE Volunteers SET
    OnCall = 0,
    Ringing = 'Logging',
    HotlineName = NULL,
    CallCity = NULL,
    CallState = NULL,
    CallZip = NULL,
    IncomingCallSid = NULL
    WHERE LoggedOn = 4 AND TraineeID = ?";
$result5 = dataQuery($query5, [$VolunteerID]);

// **REFRESH CACHE FOR POLLING CLIENTS AFTER CONFERENCE END**
try {
    require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
    $publisher = new VCCFeedPublisher();
    $publisher->refreshUserListCache();
} catch (Exception $e) {
    error_log("VCCFeedPublisher error on twilioConferenceEnd: " . $e->getMessage());
}

echo "twilioConferenceEnd: " . $query4;

// No need to close connection as PDO handles this automatically
?>
