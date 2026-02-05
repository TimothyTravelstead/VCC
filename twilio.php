<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours
require_once('../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Prevent Twilio from caching TwiML responses
header('Cache-Control: no-cache, no-store, must-revalidate, private');
header('Pragma: no-cache');
header('Expires: 0');
header("Content-Type: text/xml");
require_once './vendor/autoload.php';

// Helper function to generate audio URLs with cache-busting
function getAudioUrl($filename) {
    global $WebAddress;
    $audioFile = __DIR__ . "/Audio/" . $filename;
    $cacheBuster = file_exists($audioFile) ? filemtime($audioFile) : time();
    $audioPath = $WebAddress . "/Audio/" . $filename;
    return htmlspecialchars($audioPath, ENT_XML1, 'UTF-8');
}

// Fetch Twilio credentials from environment variables
$accountSid = getenv('TWILIO_ACCOUNT_SID') ?: 'default_account_sid';
$authToken = getenv('TWILIO_AUTH_TOKEN') ?: 'default_auth_token';
$appSid = getenv('TWILIO_APP_SID') ?: 'default_app_sid';
$username = $_SESSION["UserID"] ?: 'TimmyTesting';

// Release session lock after reading session data
session_write_close();

use Twilio\Security\RequestValidator;

// Your auth token from twilio.com/user/account
$token = $authToken;

// The X-Twilio-Signature header
$signature = $_SERVER["HTTP_X_TWILIO_SIGNATURE"] ?? 'Np1nax6uFoY6qpfT5l9jWwJeit0=';

// Initialize the request validator
$validator = new RequestValidator($token);

// The Twilio request URL
$url = $_SERVER['SCRIPT_URI'];

// Store the request parameters
$postVars = $_REQUEST;

// Initialize variables
$Training = null;
$Agent = null;

// Twilio Received Variables with null coalescing
$twilioVars = [
    'From' => $_REQUEST['From'] ?? null,
    'Length' => $_REQUEST["CallDuration"] ?? null,
    'CallSid' => $_REQUEST["CallSid"] ?? null,
    'CallStatus' => $_REQUEST["CallStatus"] ?? null,
    'FromCity' => $_REQUEST["FromCity"] ?? null,
    'FromCountry' => $_REQUEST["FromCountry"] ?? null,
    'FromState' => $_REQUEST["FromState"] ?? null,
    'FromZip' => $_REQUEST["FromZip"] ?? null,
    'To' => $_REQUEST["To"] ?? null,
    'ToCity' => $_REQUEST["ToCity"] ?? null,
    'ToCountry' => $_REQUEST["ToCountry"] ?? null,
    'ToState' => $_REQUEST["ToState"] ?? null,
    'ToZip' => $_REQUEST["ToZip"] ?? null,
    'agent' => $_REQUEST["agent"] ?? null,
    'location' => $_REQUEST["location"] ?? null,
    'training' => $_REQUEST["training"] ?? null,
    'conference' => $_REQUEST["conference"] ?? null,
    'conferenceRole' => $_REQUEST["conferenceRole"] ?? null,
    'vccCaller' => $_REQUEST["vccCaller"] ?? null,
    'translator' => $_REQUEST['translator'] ?? null,
    'clientCallSid' => $_REQUEST['clientCallSid'] ?? null
];
$BlockAreaCode = substr($twilioVars['From'], 0, 5);
$BlockExchange = substr($twilioVars['From'], 0, 8);
$clientCaller = explode(":", $twilioVars['From']);
$CallStatus = "unset";

// Route Call if Monitor is calling or if establishing a training session
if($twilioVars['agent'] && !$twilioVars['training']) {
    header("Location: trainingRouting.php?type=monitor&room=".$twilioVars['agent']);
    die;
} elseif(!$twilioVars['agent'] && $twilioVars['training']) {
    $finalWebAddress = htmlentities("/trainingRouting.php?type=trainer&room=".$twilioVars['training']);
    
    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<Response>";
    echo "<Redirect method='POST'>".$WebAddress.$finalWebAddress."</Redirect>";
    echo "</Response>";
    die;
} elseif($twilioVars['conference']) {
    // Handle conference calls from JavaScript device.connect()
    $conferenceRoom = $twilioVars['conference'];
    $conferenceType = ($twilioVars['conferenceRole'] === 'moderator') ? 'trainer' : 'trainee';
    $finalWebAddress = htmlentities("/trainingRouting.php?type=".$conferenceType."&room=".$conferenceRoom);
    
    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<Response>";
    echo "<Redirect method='POST'>".$WebAddress.$finalWebAddress."</Redirect>";
    echo "</Response>";
    die;
} elseif ($twilioVars['vccCaller']) {
    if($twilioVars['translator']) {
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<Response>";
        echo "<Dial callerId='+16463621511'>";
        echo "<Number>800-225-5254</Number>";
        echo "</Dial>";
        echo "<Dial method='POST'>";
        echo "<Conference beep='onExit' startConferenceOnEnter='true' endConferenceOnExit='true' waitUrl='".$WebAddress."/Audio/waitMusic.php'>".$twilioVars['vccCaller']."</Conference>";
        echo "</Dial>";
        echo "</Response>";
        die;
    } else {
        $finalWebAddress = htmlentities("/trainingRouting.php?type=caller&room=".$twilioVars['vccCaller']);
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<Response>";
        echo "<Redirect method='POST'>".$WebAddress.$finalWebAddress."</Redirect>";
        echo "</Response>";
        die;
    }
}

$open = 0;
$blocked = null;
$InternetNumber = null;

// Check blocked numbers

if($twilioVars['FromCountry'] !== "US" && $twilioVars['FromCountry'] !== "CA") {
	$blockedType = "Overseas";
	$blocked = true;
	$InternetNumber = 0;
}


$query = "SELECT PhoneNumber, Type, InternetNumber 
          FROM BlockList 
          WHERE (PhoneNumber = ? OR PhoneNumber = ? OR PhoneNumber = ?) 
          AND (Type = 'Admin' OR DATE(Date) = CURDATE())";
$result = dataQuery($query, [$twilioVars['From'], $BlockExchange, $BlockAreaCode]);


if ($result) {
    foreach ($result as $row) {
        $blocked = $row->PhoneNumber;
        $blockedType = $row->Type;
        $InternetNumber = $row->InternetNumber;
        
        $Anonymous = preg_match('/[a-zA-Z]/', $twilioVars['From']) === 1;
        
        if($InternetNumber == 1) {
            $blocked = 1;
            $blockedType = $blockedType.'-WebCall 1';
            $InternetNumber = 1;
        } elseif(strcasecmp($twilioVars['From'], "Anonymous") == 0) {
            $blocked = 1;
            $blockedType = $blockedType.'-WebCall 2';
            $InternetNumber = 1;
        } elseif(strcasecmp($twilioVars['From'], "anonymous") == 0) {
            $blocked = 1;
            $blockedType = $blockedType.'-WebCall 3';
            $InternetNumber = 1;
        } elseif(strcasecmp($twilioVars['From'], "+Anonymous") == 0) {
            $blocked = 1;
            $blockedType = $blockedType.'-WebCall 4';
            $InternetNumber = 1;
        } elseif(strcasecmp($twilioVars['From'], "+anonymous") == 0) {
            $blocked = 1;
            $blockedType = $blockedType.'-WebCall 5';
            $InternetNumber = 1;
        } elseif($Anonymous) {
            $blocked = 1;
            $blockedType = $blockedType.'-WebCall 6';
            $InternetNumber = 1;
        }
    }
}

if ($twilioVars['From'] == '+266696687') {
    $blockedType = 'Admin-CallerID';
}

// Check if hotline is open
$query = "SELECT dayofweek 
          FROM Hours 
          WHERE dayofweek = DATE_FORMAT(curdate(),'%w') + 1 
          AND start < now() 
          AND end > now()";
$result = dataQuery($query);
if ($result) {
    $open = $result[0]->dayofweek;
}

// CALLCONTROL FIX: Select available volunteers who can receive calls
// Only volunteers in CallControl table with can_receive_calls = 1 are eligible
// This automatically handles training control (only controller can receive calls)
$query = "SELECT v.username, v.PreferredHotline
          FROM Volunteers v
          INNER JOIN CallControl cc ON v.UserName = cc.user_id
          WHERE cc.can_receive_calls = 1
          AND v.OnCall = 0
          AND (v.Active1 is null OR v.Active1 = 'Blocked')
          AND (v.Active2 is null OR v.Active2 = 'Blocked')
          AND v.ChatInvite is null
          AND v.Ringing is null
          AND v.ChatOnly = 0";

$result = dataQuery($query);
$num_rows = count($result);

$UserID = [];
$PreferredHotline = [];
$Count = 0;

foreach ($result as $row) {
    $UserID[$Count] = $row->username;
    $PreferredHotline[$Count] = $row->PreferredHotline;
    $Count++;
}
$FoundCount = $Count;

// Format caller ID
$CallerID = sprintf("(%s) %s-%s",
    substr($twilioVars['From'], 2, 3),
    substr($twilioVars['From'], 5, 3),
    substr($twilioVars['From'], 8, 4)
);

if($CallerID == "(ony) mou-s") {
    $blocked = 1;
    $blockedType = $blockedType.'-Internet Call';
}

// Get caller location
$AreaCode = substr($CallerID, 1, 3);
$Exchange = substr($CallerID, 6, 3);
$CallerLocation = $twilioVars['FromCity'] . ", " . $twilioVars['FromState'];

// Determine hotline type
const HOTLINE_NUMBERS = [
    'LGBTQ' => ['+18888434564', '+14159929723'],
    'GLSB-NY' => ['+16463621511', '+12129890999'],
    'local' => ['+14153550999'],
    'SENIOR' => ['+18882347243', '+14159929741'],
    'Youth' => ['+18002467743', '+14159928403'],
    'OUT' => ['+18886885428']
];

$Hotline = 'Youth'; // Default
foreach (HOTLINE_NUMBERS as $type => $numbers) {
    if (in_array($twilioVars['To'], $numbers)) {
        $Hotline = $type;
        break;
    }
}

// Set call status
if($blocked && $twilioVars['From'] == '+266696687') {
    $CallStatus = "Block-".$blockedType;
    $InternetNumber = 1;
} elseif ($twilioVars['From'] == "Anonymous" || $CallerID == "(ony) mou-s") {
    $CallStatus = "Block-".$blockedType;
    $InternetNumber = 1;
} elseif ($blocked) {
    $CallStatus = "Block-".$blockedType;
    $InternetNumber = 0;
} elseif ($open == 0 || !$open) {
    $CallStatus = 'Closed';
} elseif ($num_rows == 0) {
    $CallStatus = 'No Volunteers';
} else {
    $CallStatus = 'In Progress';
}

// Insert caller history
$query = "INSERT IGNORE INTO CallerHistory 
          VALUES (?, ?, curdate(), now(), ?, 0, ?, 0, 'N/A', '', ?, now(), now(), null, null, null)";
$params = [$CallerID, $CallerLocation, $Hotline, $CallStatus, $twilioVars['CallSid']];
dataQuery($query, $params);

// Insert call routing
$callObject = json_encode($_REQUEST);
$query = "INSERT INTO CallRouting (CallSid, Volunteer, Date, callObject, callStatus) 
          VALUES (?, null, now(), ?, 'firstRing')";
$params = [$twilioVars['CallSid'], $callObject];
$result = dataQuery($query, $params);

if ($result === false) {
    die("Error inserting into CallRouting");
}

$filename = 'Twilio-Start-MySQL.txt';
file_put_contents($filename, $query);

// Generate Twilio response
if ($blocked && $InternetNumber == 0) {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<Response>";
    echo "<Reject />";
    echo "</Response>";
} elseif ($blocked && $InternetNumber == 1) {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<Response>";
    echo "<Play>".getAudioUrl("noInternetCalls.mp3")."</Play>";
    echo "</Response>";
} elseif($twilioVars['From'] == '+266696687') {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<Response>";
    echo "<Play>".getAudioUrl("Blocked.mp3")."</Play>";
    echo "</Response>";
} else {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<Response>";
    
    if ($Hotline == "local") {
        echo "<Play>".getAudioUrl("local.mp3")."</Play>";
    } elseif (!$open) {
        $closingAudios = [
            'LGBTQ' => 'Closing_GLNH.mp3',
            'Youth' => 'Closing_Youth.mp3',
            'GLSB-NY' => 'Closing_GLSB-NY.mp3',
            'SENIOR' => 'closing_Senior.mp3',
            'OUT' => 'closed_coming_out_hotline.mp3'
        ];
        
        if (isset($closingAudios[$Hotline])) {
            echo "<Play>".getAudioUrl($closingAudios[$Hotline])."</Play>";
        }
    } elseif ($num_rows == 0) {
        $openAudios = [
            'LGBTQ' => ['Open_GLNH_Initial_Greeting2.mp3', 'busy_all.mp3'],
            'Youth' => ['Open-youth-initial-greeting2.mp3', 'busy_all.mp3'],
            'GLSB-NY' => ['Open_GLSB-NY_initial-greeting2.mp3', 'busy_all.mp3'],
            'SENIOR' => ['Open_Senior_Initial_Greeting2.mp3', 'busy_all.mp3'],
            'OUT' => ['open_connecting_coming_out_hotline2.mp3', 'busy_all.mp3']
        ];
        
        if (isset($openAudios[$Hotline])) {
            echo "<Play>".getAudioUrl($openAudios[$Hotline][0])."</Play>";
            echo "<Dial timeout='10'></Dial>";
            echo "<Play>".getAudioUrl($openAudios[$Hotline][1])."</Play>";
        } else {
            echo "<Dial timeout='10'></Dial>";
            echo "<Play>".getAudioUrl("Open_GLNH.mp3")."</Play>";
        }
    } else {
        $finalWebAddress = htmlentities("/dialHotline.php");
        echo "<Redirect method='POST'>".$WebAddress.$finalWebAddress."</Redirect>";
    }
    echo "</Response>";
}
