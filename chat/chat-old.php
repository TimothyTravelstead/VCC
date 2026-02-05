<?php


include('../db_login.php');
session_start();
$flag = $_REQUEST['groupChatTransferFlag'] ?? null;

if ($_SESSION['auth'] != 'yes' && !$flag) {
	session_destroy(); 
//	header('Location: http://www.LGBTHotline.org/chat');
	die("Please return to http://www.LGBThotline.org to start a new chat.");
} 

if($flag && !$_SESSION['CallerID']) {
	$_SESSION['CallerID'] = $_REQUEST['CallerID'];
	$_SESSION['groupChatTransfer'] = $flag;
}

$groupChatTransferMessage = $_SESSION['groupChatTransferMessage'] ?? null;
$key = $_SESSION['CallerID'] ?? null;
$groupChatTransfer = $_SESSION['groupChatTransfer'] ?? null;

/* Chat Statuses:

	1 = No Such Status
	2 = Normal message
	3 = Caller Ended Chat
	4 = Caller Closed Chat window without ending
	5 = Volunteer Ended Chat
*/

// Include the mysql databsae location and login information



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

	$findLoggedon = " WHERE ((LoggedOn = 1) OR 
					(LoggedOn = 4 and Muted = 0) OR 
					(LoggedOn = 6 and Muted = 0))";
	$findEligible = $findLoggedon." AND Ringing is null AND OnCall = 0 AND (((Active1 is null OR Active2 is null) and OneChatOnly is null) or (Active1 is null AND Active2 is null and OneChatOnly = '1')) AND ChatInvite is null AND DESK != 2"; // Desk is used for CallerType and 2 = Calls Only
	$findChatting = $findLoggedon." AND OnCall = 0 AND ((Active1 is not null) OR (Active2 is not null) OR ChatInvite is not null)";
	$findAvailableChatting = $findLoggedon." AND OnCall = 0 AND ((Active1 is not null AND Active2 is null AND OneChatOnly is null) OR (Active2 is not null AND Active1 is null AND OneChatOnly is null)) AND ChatInvite is null";

	$findChatOnly = $findEligible." AND ChatOnly = 1";

	//Determine if Hotline is Open
		$query = "SELECT DayofWeek FROM Hours WHERE (DayofWeek = DATE_FORMAT(curdate(),'%w') + 1) AND (start < curtime() AND end > curtime())";
		$result = mysqli_query($connection,$query);
		while ($result_row = mysqli_fetch_row(($result))) {
			$open =		$result_row[0];
		}

	//Chat Invite Counts Routine
		$query2= "SELECT count(username) FROM volunteers".$findLoggedon;
		$result2 = mysqli_query($connection,$query2);
		while ($result_row = mysqli_fetch_row(($result2))) {
			$loggedon =		$result_row[0];
		}
		
		if ($loggedon < $chatAvailableLevel1) {
			$potential = 0;
		} elseif ($loggedon < $chatAvailableLevel2) {
			$potential = 1; 
		} else {
			$potential = 2;
		}
	
		$query3 = "SELECT count(username) FROM volunteers".$findEligible;
		$result3 = mysqli_query($connection,$query3);
		while ($result_row = mysqli_fetch_row(($result3))) {
			$eligible =		$result_row[0];
		}
	
		$query4 = "SELECT count(username) FROM volunteers".$findChatting;
		$result4 = mysqli_query($connection,$query4);
		while ($result_row = mysqli_fetch_row(($result4))) {
			$chatting =		$result_row[0];
		}
	
	
		$query5 = "SELECT count(username) FROM volunteers".$findAvailableChatting;
		$result5 = mysqli_query($connection,$query5);
		while ($result_row = mysqli_fetch_row(($result5))) {
			$availableChatting =		$result_row[0];
		}
	
		$query6 = "SELECT count(username) FROM volunteers".$findChatOnly;
		$result6 = mysqli_query($connection,$query6);
		while ($result_row = mysqli_fetch_row(($result6))) {
			$chatOnly =		$result_row[0];
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
					$query7A = "UPDATE Volunteers SET Volunteers.InstantMessage = '".$groupChatTransferMessage."' , Volunteers.ChatInvite = '".$key."'".$findChatOnly;
					$result7A = mysqli_query($connection,$query7A);	
			} else if ($loggedon < $chatAvailableLevel1) {
				$status = "busy";
			} else if ($chatting < $potential) {
				if ($eligible > 0) {
					//Invite All Eligible
						$status = "allEligible";
						$query6 = "UPDATE Volunteers SET Volunteers.InstantMessage = '".$groupChatTransferMessage."' , Volunteers.ChatInvite = '".$key."'".$findEligible;
						$result6 = mysqli_query($connection,$query6);	
				} else {	
					$status = "busy";
				}	
			} else if ($chatting == $potential) {
				if ($availableChatting > 0) {
					//Invite All Available Chatting
						$status = "allAvailableChatting";
						$query7 = "UPDATE Volunteers SET Volunteers.InstantMessage = '".$groupChatTransferMessage."' , Volunteers.ChatInvite = '".$key."'".$findAvailableChatting;
						$result7 = mysqli_query($connection,$query7);	
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
		
	$query = "INSERT INTO chatStatus VALUES (null, now(),'".$key."' , null, null, '".$browser['browser']."', '".$browser['version']."', '".$browser['platform_description']."', '".$browser['platform_version']."','".$browser['browser_name_pattern']."' , '".$status."' , '".$browserStatus."' , null , null)";
	$result = mysqli_query($connection,$query);

} else {
	$status = 'connected';
}	
			

if ($browserStatus == "unsupported" && $status != 'connected') {
	$status = "unsupported";
}

mysqli_close($connection);

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
		echo "<script src='index.js' type='text/javascript'></script>";
	} elseif($flag) {
		echo "<script src='index.js' type='text/javascript'></script>";
	} else {
//		die("Status issue: ".$status." - ".$groupChatTransfer);
	}

?>
	<title>LGBT National Help Center Peer-Counseling Chat</title>
</head>
<body onunload="">
<div id="AllContents">
<div id='logo'>
	<img src='logo.jpg' alt="LGBT National Help Center Logo">
	<h1>LGBT National Help Center<br>Peer-Support Chat</h1>
</div>
<?php
	  echo "<input type=\"hidden\" id=\"CallerID\" value=\"".$key."\">";
	  echo "<input type=\"hidden\" id=\"referringPage\" value=\"".$referringPage."\">";
?>
			<input type="hidden" id="VolunteerID" value=" ">
			<input type="hidden" id="RoomNo" value=" ">
			<input type="hidden" id="LastMessage" value=" ">
			
		<div id="Chat" >
<?php
	if ($status == "busy") {
		echo "<p>Sorry, our volunteers are currently busy helping other people.  Please try later, or you can email a volunteer at: <br><br> <a href='mailto:help@LGBThotline.org?Subject=Peer-counseling or Information Request'>help@LGBThotline.org</a></p>";
		echo "</div>";
	} elseif ($status == "closed") {
		echo "<p>Chat services are currently unavailable.  Please try back during our open hours.  You can also email a volunteer at: <br><br> <a href='mailto:help@LGBThotline.org?Subject=Peer-counseling or Information Request'>help@LGBThotline.org</a></p>";
		echo "</div>";
	} elseif ($status == "unsupported") {
		echo "<p>".$browserSupportMessage."</p>";
		echo "</div>";
	} else { 
		echo "Trying to connect to a volunteer peer-counselor.  Please wait.";
		echo "</div>";
		echo "<div id='Message'>";
		echo "<p id='MessageText'><textarea id='MessageItself' rows='15' cols='54'></textarea></p>";
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



