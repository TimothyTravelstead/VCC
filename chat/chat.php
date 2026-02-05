<?php


// Enhanced cache control headers

require_once('../../private_html/db_login.php');
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); // HTTP/1.1
header("Pragma: no-cache"); // HTTP/1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header("Vary: *"); // Indicate the response is varied based on the request




// Sanitize and validate input parameters
$flag = isset($_REQUEST['groupChatTransferFlag']) ? filter_var($_REQUEST['groupChatTransferFlag'], FILTER_SANITIZE_STRING) : null;

if ($_SESSION['auth'] != 'yes' && !$flag) {
	session_destroy(); 
//	header('Location: http://www.LGBTHotline.org/chat');
//	die("Please return to http://www.LGBThotline.org to start a new chat.");
} 

$n = 10;
function getRandomString($n) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < $n; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $randomString .= $characters[$index];
    }
    return $randomString;
}


if($flag && !$_SESSION['CallerID']) {
	// Sanitize CallerID to prevent injection
	$callerID = isset($_REQUEST['CallerID']) ? filter_var($_REQUEST['CallerID'], FILTER_SANITIZE_STRING) : '';
	// Validate CallerID format (alphanumeric only)
	if (preg_match('/^[a-zA-Z0-9]+$/', $callerID)) {
		$_SESSION['CallerID'] = $callerID;
		$_SESSION['groupChatTransfer'] = $flag;
	}
}

$groupChatTransferMessage = $_SESSION['groupChatTransferMessage'] ?? null;
$key = $_SESSION['CallerID'] ?? null;

if(!$key) {
	$key=getRandomString(10);
}


$groupChatTransfer = $_SESSION['groupChatTransfer'] ?? null;

/* Chat Statuses:

	1 = No Such Status
	2 = Normal message
	3 = Caller Ended Chat
	4 = Caller Closed Chat window without ending
	5 = Volunteer Ended Chat
*/

// Include the mysql database location and login information



include 'firstChatAvailableLevel.php';
include 'secondChatAvailableLevel.php';


$referringPage = $_SESSION['referringPage'] ?? null;
$ipAddress = json_encode("IP ADDRESS: ".$_SERVER['REMOTE_ADDR']);

//browser detect
$browserSupport = null;

if(!$groupChatTransfer) {
	$browserSupportMessage = null;
	$browser['browser'] = $_SESSION['callerBrowser'];
	$browser['version'] = $_SESSION['callerBrowserVersion'];
	$browser['platform_description'] =  $_SESSION['callerOS'];
	$browser['platform_version'] =  $_SESSION['callerOSVersion'];

	if($browser['browser'] == "Internet Explorer" && $browser['version'] < 9) {
		$browserSupport = "Not";
	}

	if($browser['browser'] == "Opera") {
		$browserSupport = "Not";
	}

	if($browser['browser'] == "Chrome" && $browser['version'] < 30) {
		$browserSupport = "Not";
	}

	if($browser['browser'] == "Safari" && $browser['version'] < 5) {
		$browserSupport = "Not";
	}

	if($browser['browser'] == "Firefox" && $browser['version'] < 3.6) {
		$browserSupport = "Not";
	}

	if($browser['browser'] == "Android" && $browser['version'] < 4) {
		$browserSupport = "Not";
	}

	if($browser['browser'] != "Firefox" && $browser['browser'] != "Chrome" && $browser['browser'] != "Safari" && $browser['browser'] != "Internet Explorer" && $browser['browser'] != "Android" && $browser['browser'] != "Edge") {
		$browserSupport = "Not";
	}
	

// Temp Fix for browser detection issues
$browserSupport = null;

	if($browserSupport != null) {
		$browserSupportMessage = "Your Browser, ".$browser['browser']." ".$browser['version']." is not supported because it is too old a version.";
		$browserSupportMessage = $browserSupportMessage."<br><br>To use our chat service, please upgrade your 
			browser using one of the following links: <br /><br /><a href='http://www.google.com/chrome/browser'>
			Chrome</a><br /><a href='http://www.apple.com/softwareupdate'>Safari</a><br /><a href='http://www.mozilla.org/firefox'>Firefox</a><br /><a href='http://windows.microsoft.com/en-us/internet-explorer/download-ie'>Internet Explorer</a>";
	}

	if ($browser['browser'] == "Android" && $browserSupport != null) {
		$browserSupportMessage = "Your Browser, ".$browser['browser']." ".$browser['version']." is not supported because it is too old a version.";
		$browserSupportMessage = $browserSupportMessage."<br><br>If possible, please upgrade Android to version 4.0 or higher.  <br />Otherwise, please sign on to our chat service 
			using a desktop, laptop, or Apple mobile device.";
	}

	global $findLoggedon;
	global $findEligible;
	global $findChatting;
	global $findAvailableChatting;
	global $status;
	global $open;
	global $potential;

	// CALLCONTROL FIX: Only volunteers in CallControl table can receive chats
	// Join with CallControl instead of checking LoggedOn/Muted
	$findLoggedon = " INNER JOIN CallControl cc ON volunteers.UserName = cc.user_id WHERE cc.can_receive_chats = 1";
	// OneChatOnly logic (OneChatOnly is int(11)):
	// - OneChatOnly = 1: only eligible if BOTH slots are empty (only wants 1 chat)
	// - OneChatOnly IS NULL or 0: eligible if AT LEAST ONE slot is empty (can take 2 chats)
	$oneChatOnlyCondition = "((volunteers.OneChatOnly = 1 AND (volunteers.Active1 is null OR volunteers.Active1 = '') AND (volunteers.Active2 is null OR volunteers.Active2 = '')) OR ((volunteers.OneChatOnly is null OR volunteers.OneChatOnly = 0) AND ((volunteers.Active1 is null OR volunteers.Active1 = '') OR (volunteers.Active2 is null OR volunteers.Active2 = ''))))";
	$findEligible = $findLoggedon." AND volunteers.Ringing is null AND volunteers.OnCall = 0 AND ".$oneChatOnlyCondition." AND (volunteers.ChatInvite is null or volunteers.chatInvite = '') AND volunteers.DESK != 2"; // Desk is used for CallerType and 2 = Calls Only
	$findChatting = $findLoggedon." AND volunteers.OnCall = 0 AND ((volunteers.Active1 is not null AND volunteers.Active1 != '') OR (volunteers.Active2 is not null AND volunteers.Active2 != '') OR (volunteers.ChatInvite is not null AND volunteers.ChatInvite != ''))";
	// findAvailableChatting: volunteers already on ONE chat who can take a second (OneChatOnly != 1)
	$findAvailableChatting = $findLoggedon." AND volunteers.OnCall = 0 AND (volunteers.OneChatOnly is null OR volunteers.OneChatOnly = 0) AND (((volunteers.Active1 is not null AND volunteers.Active1 != '') AND (volunteers.Active2 is null OR volunteers.Active2 = '')) OR ((volunteers.Active2 is not null AND volunteers.Active2 != '') AND (volunteers.Active1 is null OR volunteers.Active1 = ''))) AND (volunteers.ChatInvite is null or volunteers.chatInvite = '')";

	$findChatOnly = $findEligible." AND volunteers.ChatOnly = 1";

	//Determine if Hotline is Open
		$query = "SELECT DayofWeek FROM Hours WHERE (DayofWeek = DATE_FORMAT(curdate(),'%w') + 1) AND (start < curtime() AND end > curtime())";
		$result = dataQuery($query, []);
		if (!empty($result)) {
			$open = $result[0]->DayofWeek;
		}

	//Chat Invite Counts Routine
		$query2 = "SELECT count(username) as count FROM volunteers".$findLoggedon;
		$result2 = dataQuery($query2, []);
		if (!empty($result2)) {
			$loggedon = $result2[0]->count;
		}
		
		if ($loggedon < $chatAvailableLevel1) {
			$potential = 0;
		} elseif ($loggedon < $chatAvailableLevel2) {
			$potential = 1; 
		} else {
			$potential = 2;
		}
	
		$query3 = "SELECT count(username) as count FROM volunteers".$findEligible;
		$result3 = dataQuery($query3, []);
		if (!empty($result3)) {
			$eligible = $result3[0]->count;
		}
	
		$query4 = "SELECT count(username) as count FROM volunteers".$findChatting;
		$result4 = dataQuery($query4, []);
		if (!empty($result4)) {
			$chatting = $result4[0]->count;
		}
	
		$query5 = "SELECT count(username) as count FROM volunteers".$findAvailableChatting;
		$result5 = dataQuery($query5, []);
		if (!empty($result5)) {
			$availableChatting = $result5[0]->count;
		}
	
		$query6 = "SELECT count(username) as count FROM volunteers".$findChatOnly;
		$result6 = dataQuery($query6, []);
		if (!empty($result6)) {
			$chatOnly = $result6[0]->count;
		}

		if ($browserSupport != null) {
			$browserStatus = "unsupported";	
		} else {
			$browserStatus = "Supported";
	
	
			if (!$open) {
				$status = "closed";	
			} else if ($chatOnly > 0) {
				//Invite All Eligible Chat Only Volunteers
					$status = "allChatOnly";
					// Secure parameterized query to prevent SQL injection
					// OneChatOnly logic (int field): 1 = both slots must be empty, 0/null = at least one slot empty
					$query7A = "UPDATE Volunteers SET InstantMessage = ?, ChatInvite = ? WHERE ((LoggedOn = 1) OR (LoggedOn = 4 and Muted = 0) OR (LoggedOn = 6 and Muted = 0)) AND Ringing is null AND OnCall = 0 AND ((OneChatOnly = 1 AND (Active1 is null OR Active1 = '') AND (Active2 is null OR Active2 = '')) OR ((OneChatOnly is null OR OneChatOnly = 0) AND ((Active1 is null OR Active1 = '') OR (Active2 is null OR Active2 = '')))) AND (ChatInvite is null or chatInvite = '') AND DESK != 2 AND ChatOnly = 1";
					$result7A = dataQuery($query7A, [$groupChatTransferMessage, $key]);
			} else if ($loggedon < $chatAvailableLevel1) {
				$status = "busy";
			} else if ($chatting < $potential) {
				if ($eligible > 0) {
					//Invite All Eligible
						$status = "allEligible";
						// Secure parameterized query to prevent SQL injection
						// OneChatOnly logic (int field): 1 = both slots must be empty, 0/null = at least one slot empty
						$query6 = "UPDATE Volunteers SET InstantMessage = ?, ChatInvite = ? WHERE ((LoggedOn = 1) OR (LoggedOn = 4 and Muted = 0) OR (LoggedOn = 6 and Muted = 0)) AND Ringing is null AND OnCall = 0 AND ((OneChatOnly = 1 AND (Active1 is null OR Active1 = '') AND (Active2 is null OR Active2 = '')) OR ((OneChatOnly is null OR OneChatOnly = 0) AND ((Active1 is null OR Active1 = '') OR (Active2 is null OR Active2 = '')))) AND (ChatInvite is null or chatInvite = '') AND DESK != 2";
						$result6 = dataQuery($query6, [$groupChatTransferMessage, $key]);
				} else {
					$status = "busy";
				}
			} else if ($chatting == $potential) {
				if ($availableChatting > 0) {
					//Invite All Available Chatting
						$status = "allAvailableChatting";
						// Secure parameterized query to prevent SQL injection
						// Only volunteers who can take a second chat (OneChatOnly != 1) and have one slot filled
						$query7 = "UPDATE Volunteers SET InstantMessage = ?, ChatInvite = ? WHERE ((LoggedOn = 1) OR (LoggedOn = 4 and Muted = 0) OR (LoggedOn = 6 and Muted = 0)) AND OnCall = 0 AND (OneChatOnly is null OR OneChatOnly = 0) AND (((Active1 is not null AND Active1 != '') AND (Active2 is null OR Active2 = '')) OR ((Active2 is not null AND Active2 != '') AND (Active1 is null OR Active1 = ''))) AND (ChatInvite is null or chatInvite = '')";
						$result7 = dataQuery($query7, [$groupChatTransferMessage, $key]);	
				} else {
					$status = "busy";
				}
			} else {
				$status = "busy";
			}
		}
	if(!isset($browser['browser_name_pattern'])) {
		$browser['browser_name_pattern']= 'none';
	}
	
	// Secure parameterized query to prevent SQL injection
	$query = "INSERT INTO chatStatus VALUES (null, now(), ?, null, null, ?, ?, ?, ?, ?, ?, ?, null, null)";
	$params = [
		$key,
		$browser['browser'],
		$browser['version'],
		$browser['platform_description'],
		$browser['platform_version'],
		$browser['browser_name_pattern'],
		$status,
		$browserStatus
	];
	$result = dataQuery($query, $params);

} else {
	$status = 'connected';
}
// Release session lock now that we're done with all session data
session_write_close();

if ($browserStatus == "unsupported" && $status != 'connected') {
	$status = "unsupported";
}

// Connection is automatically closed by PDO

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta HTTP-EQUIV="CACHE-CONTROL" CONTENT="NO-CACHE" />
	<meta NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
	<meta http-equiv="Pragma" content="no-cache" />
	<meta http-equiv="Expires" content="Mon, 22 Jul 2002 11:12:01 GMT" />
	<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
	<link rel="stylesheet" type="text/css" href="index.css">
	<script src = 'https://cdnjs.cloudflare.com/ajax/libs/fingerprintjs2/1.5.1/fingerprint2.min.js' type='text/javascript'></script>
	<script src = 'chatCallerData.js' type='text/javascript'></script>

	
<?php
	if ($status !== "busy" && $status !== "closed" && $browserSupport === null) {
		echo "<script src='../LibraryScripts/ErrorModal.js' type='text/javascript'></script>";
		echo "<script src='index.js' type='text/javascript'></script>";
	} elseif($flag) {
		echo "<script src='../LibraryScripts/ErrorModal.js' type='text/javascript'></script>";
		echo "<script src='index.js' type='text/javascript'></script>";
	} else {
//		die("Status issue: ".$status." - ".$groupChatTransfer);
	}

?>
	<title>LGBT National Help Center Peer-Counseling Chat</title>
</head>
<body onunload="">
    <div class="chat-container">
        <div class="chat-header">
            <p><img src="logo.png" alt="Logo" class="chat-logo"></p>
            <h1>Peer Chat</h1>
        </div>
<?php
	  echo "<input type=\"hidden\" id=\"CallerID\" value=\"" . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . "\">";
	  echo "<input type=\"hidden\" id=\"referringPage\" value=\"" . htmlspecialchars($referringPage, ENT_QUOTES, 'UTF-8') . "\">";
?>
			<input type="hidden" id="VolunteerID" value=" ">
			<input type="hidden" id="RoomNo" value=" ">
			<input type="hidden" id="LastMessage" value=" ">
			
		<div id="Chat" >
<?php

	if ($status == "busy") {
		echo "<h1>Sorry, our volunteers are currently busy helping other people.  Please try later, or you can email a volunteer at: <br><br> <a href='mailto:help@LGBThotline.org?Subject=Peer-counseling or Information Request'>help@LGBThotline.org</a></h1>";
		echo "</div>";
	} elseif ($status == "closed") {
		echo "<h1>Chat services are currently unavailable.  Please try back during our open hours.  You can also email a volunteer at: <br><br> <a href='mailto:help@LGBThotline.org?Subject=Peer-counseling or Information Request'>help@LGBThotline.org</a></h1>";
		echo "</div>";
	} elseif ($status == "unsupported") {
		echo "<p>".$browserSupportMessage."</p>";
		echo "</div>";
	} else { 
		echo "Trying to connect to a volunteer peer-counselor.  Please wait.";
		echo "</div>";
		echo "<div id='Message'>";
		echo "<p id='MessageText'><textarea id='MessageItself'></textarea></p>";
		echo "</div>";
		echo "<div id='Send'>";
		echo "<input type='button' id='endChatButton' class='buttons' value='End Chat'>";
		echo "<input type='button' id='SendButton' class='buttons' value='Send Message'>";
		echo "</div>";
	}
?>
</div>
<script>
<?php
	echo "recordCallerData(".$ipAddress.");";
?>
</script>
</body>
</html>		



