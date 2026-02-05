<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day volunteer sessions
require_once '../private_html/db_login.php';

// Now start the session with the correct configuration
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, private");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$timestamp = round(microtime(true) * 1000000);

error_reporting(E_ERROR | E_WARNING | E_PARSE);

if (!isset($_SESSION['auth']) || $_SESSION['auth'] != 'yes') {
    http_response_code(401);
    header('Content-Type: text/plain');
    die('Unauthorized access');
}

$_SESSION['CREATED'] = time();

$UserID = $_SESSION['UserID'] ?? null;
$resourceOnly = $_SESSION["resourceOnly"] ?? null;
$trainer = $_SESSION["trainer"] ?? null;
$trainee = $_SESSION["trainee"] ?? null;
$traineeSkypeID = $_SESSION["traineeSkypeID"] ?? null;
$monitor = $_SESSION["monitor"] ?? null;
$traineeName = "";
$trainerName = "";
$trainerID = null; // CRITICAL: Initialize to prevent undefined variable errors

// Determine Whether to Play Video - moved from index.php since users go to index2.php after login
$dir = "HeadquartersMessage/";

// Get volunteer's previous signin time from volunteerlog table (not current session)
// Query for the second-to-last signin (LoggedOnStatus > 0) to get the signin BEFORE this current session
$previousSigninQuery = "SELECT EventTime FROM volunteerlog WHERE UserID = ? AND LoggedOnStatus > 0 ORDER BY EventTime DESC LIMIT 2";
$previousSigninResult = dataQuery($previousSigninQuery, [$UserID]);

$previousSigninTime = 0;
if (!empty($previousSigninResult) && count($previousSigninResult) >= 2) {
    // Use the second record (previous signin, not current one)
    $previousSigninTime = strtotime($previousSigninResult[1]->EventTime);
} else if (!empty($previousSigninResult) && count($previousSigninResult) == 1) {
    // Only one signin record means this is their second login ever - show all videos
    $previousSigninTime = 0;
} else {
    // No previous signin records - first time user, show all videos
    $previousSigninTime = 0;
}

// Only look for videos that are newer than the user's PREVIOUS signin (not current session)
$videoLastMod = 0;
$videoLastModFile = '';
foreach (scandir($dir) as $entry) {
    $file = $dir.$entry;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $fileTime = filemtime($dir.$entry);
    
    // Only consider videos that are newer than previous signin AND newer than current best candidate
    if (is_file($dir.$entry) && ($ext == "mp4" || $ext == "m4v" || $ext == "mov" || $ext == "html") && 
        $fileTime > $previousSigninTime && $fileTime > $videoLastMod) {
        $videoLastMod = $fileTime;
        $videoLastModFile = $dir.$entry;
    }
}

// If no newer video found, don't show any video
if ($videoLastModFile === '') {
    $videoLastModFile = 'none';
    $videoLastMod = 0;
}

$_SESSION['videoFileTime'] = $videoLastMod;
$_SESSION['videoFileName'] = $videoLastModFile;


$lastModFile = $_SESSION["videoFileName"];
$lastMod = $_SESSION['videoFileTime'];
$ext = pathinfo($lastModFile, PATHINFO_EXTENSION);

if($trainer) {
    $_SESSION["trainingShareRoom"] = $UserID;
} else {
    $_SESSION["trainingShareRoom"] = $trainer;
}

// Release session lock after reading/writing all session data
session_write_close();

// Get Trainer/Trainee Data - Fixed logic to handle both cases
if($trainee != 1 && $trainee) {
    // Case 1: Current user is trainer, $trainee contains the trainee's username
    $query = "SELECT firstname, lastname, UserName FROM Volunteers WHERE UserName = ?";
    $result = dataQuery($query, [$trainee]);

    if (!empty($result)) {
        $First = $result[0]->firstname;
        $Last = $result[0]->lastname;
        
        if($trainer == 1) {
            $traineeID = $result[0]->UserName ?? null;
            $traineeName = "$First $Last";
        }
    }
} elseif ($trainee == 1) {
    // Case 2: Current user is trainee, get trainer from session (set during login)
    // CRITICAL FIX: Read from session first - loginverify2.php looks this up during login
    $trainerID = $_SESSION['trainerID'] ?? null;
    $trainerName = $_SESSION['trainerName'] ?? "No Trainer Assigned";

    // Fallback: If session doesn't have it, try database lookup (shouldn't happen)
    if (empty($trainerID)) {
        error_log("WARNING: Trainee $UserID has no trainerID in session, attempting database lookup");

        $trainerQuery = "SELECT UserName, firstname, lastname, TraineeID FROM volunteers WHERE FIND_IN_SET(?, TraineeID) > 0 LIMIT 1";
        $trainerResult = dataQuery($trainerQuery, [$UserID]);

        if (!empty($trainerResult)) {
            $trainerID = $trainerResult[0]->UserName;
            $trainerName = $trainerResult[0]->firstname . " " . $trainerResult[0]->lastname;
            error_log("DEBUG: Database lookup found trainer for trainee $UserID: TrainerID=$trainerID");
        } else {
            error_log("ERROR: No trainer found for trainee $UserID in database or session");
            $trainerID = null;
            $trainerName = "No Trainer Assigned";
        }
    } else {
        error_log("DEBUG: Trainee $UserID loaded trainer from session: TrainerID=$trainerID");
    }
} elseif ($trainer == 1) {
    // Case 3: Current user is trainer, get their assigned trainees from TraineeID field
    // Set trainerID to the trainer's own UserID
    $trainerID = $UserID;
    
    $trainerTraineeQuery = "SELECT TraineeID FROM volunteers WHERE UserName = ?";
    $trainerTraineeResult = dataQuery($trainerTraineeQuery, [$UserID]);
    
    if (!empty($trainerTraineeResult) && $trainerTraineeResult[0]->TraineeID) {
        $assignedTrainees = $trainerTraineeResult[0]->TraineeID;
        error_log("DEBUG: Trainer $UserID has assigned trainees: " . $assignedTrainees);
    } else {
        $assignedTrainees = "";
        error_log("DEBUG: Trainer $UserID has no assigned trainees in TraineeID field");
    }
} 

// Set Shift and Line Parameters from Database
$query = "SELECT Shift, TIME_FORMAT(Start, '%h:%i %p') as start_time, 
          TIME_FORMAT(End, '%h:%i %p') as end_time 
          FROM Hours 
          WHERE DayofWeek = dayofweek(now()) 
          AND subtime(start, '00:30:00') <= time(now()) 
          AND end > time(now()) 
          ORDER BY shift DESC 
          LIMIT 1";
$result = dataQuery($query);

if (!empty($result)) {
    $Shift = $result[0]->Shift;
    $ShiftStart = $result[0]->start_time;
    $ShiftEnd = $result[0]->end_time;
} else {
    $Shift = 1;
}

// Update volunteer's shift
$query = "UPDATE Volunteers SET Shift = ? WHERE UserName = ?";
dataQuery($query, [$Shift, $UserID]);

// Get volunteer details
$query = "SELECT v.firstname, v.lastname,
          v.hotline, v.office, v.desk, v.loggedon, v.ChatOnly,
          v.OneChatOnly, v.username
          FROM Volunteers v
          WHERE v.UserName = ?";
$result = dataQuery($query, [$UserID]);

if (empty($result)) {
    die("INVALID");
}

$volunteerData = $result[0];
$FirstName = $volunteerData->firstname;
$LastName = $volunteerData->lastname;
$HotlineNumber = $volunteerData->hotline;
$Office = $volunteerData->office;
$Desk = $volunteerData->desk;
$LoggedOn = $volunteerData->loggedon;
$ChatOnly = $volunteerData->ChatOnly;
$oneChatOnly = $volunteerData->OneChatOnly;
$username = $volunteerData->username;

if($Desk == 1) {  // Set Chat Only if User is Set to Chat Only as a volunteer
    $ChatOnly = 1;
}


// Get Canned Text List
$query = "SELECT Menu FROM ChatText ORDER BY OrderNumber";
$result = dataQuery($query);
$CannedTextMenu = "<option value='Canned Responses'>CANNED RESPONSES</option>";
foreach ($result as $row) {
    $CannedTextMenu .= "<option value='" . $row->Menu . "'>" . $row->Menu . "</option>";
}

// Info Center List    
$InfoCenterArray = Array();
$InfoCenterButtons = "";

if ($handle = opendir('InfoCenter/')) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            $extn = explode('.', $entry);
            if($extn[0]) {
                $InfoCenterArray[$extn[0]] = "<input type='button' class='infoCenterButton' value='" . $extn[0] . "' />";
            }
        }
    }
    ksort($InfoCenterArray);
    foreach ($InfoCenterArray as $value) {
        $InfoCenterButtons .= $value;
    }
    closedir($handle);
}


?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta HTTP-EQUIV="CACHE-CONTROL" CONTENT="no-cache, no-store, must-revalidate" />
        <meta http-equiv="cache-control" content="max-age=0" />
        <meta http-equiv="expires" content="-1" />
        <meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT" />
        <meta http-equiv="pragma" content="no-cache" />
        <meta http-equiv="content-type" content="text/html;charset=utf-8" />
        <meta NAME="ROBOTS" CONTENT="NONE" />
        <meta NAME="GOOGLEBOT" CONTENT="NOARCHIVE" />
        <script src="LibraryScripts/ConsoleCapture.js" type="text/javascript"></script>
        <script src="trainingShare3/screenSharingControlMulti.js?v=2026012905" type="text/javascript"></script>
        <script src="trainingShare3/trainingSessionUpdated.js?v=2026012904" type="text/javascript"></script>
        <script src="node_modules/@twilio/voice-sdk/dist/twilio.min.js" type="text/javascript"></script>
        <script src="twilioModule.js" type="text/javascript"></script>
        <script src="LibraryScripts/Ajax.js" type="text/javascript"></script>
        <script src="LibraryScripts/domAndString.js" type="text/javascript"></script>
        <script src="LibraryScripts/ErrorModal.js" type="text/javascript"></script>
        <script src="index.js" type="text/javascript"></script>
        <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBL_Mp0VJY5mOM1uGc-jT41FCN2z3XP5Z0" type="text/javascript"></script>
        <link rel="stylesheet" href="index.css" />
        <link rel="icon" href="data:,">
        <title>LGBT Hotline - Volunteer Communication System</title>
    </head>
    <body id="body" onbeforeunload="return improperExit()" onpagehide="return improperExit()">
		<!-- Skip navigation link for screen reader users -->
		<a href="#mainPane" class="sr-only sr-only-focusable">Skip to main content</a>

			<div id="titleBar" class='redGradient' >
				<div id='volunteerDetails'>
					<div id='volunteerDetailsTitle'>
						<?php 
							if($resourceOnly) {
								echo "RESOURCE SEARCHES ONLY";
							} elseif ($trainer == 1) {
								echo "TRAINEE: ".$traineeName;
							} elseif ($trainee == 1) {
								echo "TRAINER: " . $trainerName;
							} elseif ($monitor) {
								echo "MONITOR";
							} elseif ($Desk == 2) {
								echo "CALLS ONLY";
							} elseif ($ChatOnly) {
								echo "CHAT ONLY";
							}
						 ?>
					</div>
				</div>
				<h1>LGBT National Help Center</h1>
				<?php
					if($UserID == 'BradBecker99' || $UserID == 'Travelstead') {
						echo "<input id='bradStatsButton' type='button' value='Stats' />";
					}
				?>
				<div id="searchParameters"></div>
			</div>
			<div id="mainPane" tabindex="-1">
				<div id="infoCenterPane" class='redGradient'>
					<h2>Information Center</h2>
					<div id='infoCenterButtons'>
						<?php
							echo $InfoCenterButtons;
						?>
					</div>
					<div id="infoCenterText"></div>
					<input type="button" id="infoCenterClose" value="Close" aria-label="Close information center" />
				</div>
				<div id="callPane" class='redGradient'>
					<br />
					<br />
					<h2 id='callHotlineDisplay'></h2>
					<div id='incomingCall'>
						<img id="answerCall" class="answerCallLeft" src="Images/answer.png" alt="Answer call" role="button" tabindex="0" aria-label="Answer incoming call" />
						<img id="rejectCall" src="Images/reject.png" alt="Reject call" role="button" tabindex="0" aria-label="Reject incoming call" />
					</div>
				</div>
				<div id="newSearchPane" class='redGradient' >
					<h3 title="Sign in to Facebook before clicking the Facebook icon.">Had an interesting or challenging call or chat?
					Share it on our volunteer-only Facebook page.<a target="_blank" title="Sign in to Facebook before clicking the Facebook icon." 
					href="https://www.facebook.com/groups/107973445890093/">
					<img src="Images/facebook.png" alt="Facebook volunteer group"></a></h3>			

					<div id='volunteerMessage' >
						<img id='volunteerMessageSlide' src="Images/Welcome/first website.jpg" alt="Volunteer message" />
					</div>
					<h2 id="volunteerListLabel"></h2>
					<div id='volunteerList'>
						<table id='volunteerListTable'>
							<caption class="sr-only">Currently logged in volunteers</caption>
							<thead>
								<tr id="userListHeader"><th scope="col">Name</th><th scope="col"><span class="sr-only">Status</span></th><th scope="col">Call</th><th scope="col">Chat</th></tr>
							</thead>
							<tbody>
							</tbody>
						</table>
					</div>
					<div id="searchBox" draggable="true">
						<form id="searchBoxForm" action="javascript:validate()">
							<table role="presentation">
								<tr><th scope="row">Zip/Postal:</th><td><input type="text" id="ZipCode" aria-label="Zip or postal code" /></td></tr>
								<tr><th scope="row">Range:</th><td><input type="text" id="Distance" aria-label="Search range in miles" /></td></tr>
								<tr><th scope="row">City:</th><td><input type="text" id="City" aria-label="City name" /></td></tr>
								<tr><th scope="row">State/Prov.:</th><td><input type="text" id="State" aria-label="State or province" /></td></tr>
								<tr><th scope="row">Name:</th><td><input type="text" id="Name" aria-label="Resource name" /></td></tr>
							</table>
							<input type='button' value="NATIONAL" class="nationalSearch hover" id="nationalSearch" aria-label="Search all national resources" />
							<select class="internationalSearch hover" id="internationalSearch" aria-label="Select country for international search"></select></td>
							<input id='newSearchClose' type="button" value="Close" aria-label="Close search box" />
							<input type="button" class="hover" value="Find Zip" id="findZipSearch" aria-label="Find zip code by city and state" />
							<input type="Submit" class="mainSearch defaultButton" value="Search" aria-label="Search for resources" />
						</form>
					</div>
				</div>
				<div id="resourceList" class='redGradient'>
					<?php
						include("categories.inc");
					?>
					<div id="resourceListCategory"></div>
					<div id="resourceListCount"></div>
					<div id="resultsControls">
						<table role="presentation">
							<tr><td class='resourceCounter' ></td>
								<td><div><input type="button" id="sortByNameButton" class="Name" value="Name" aria-label="Sort by name" /></div></td>
								<td><div><input type="text" id="sortByType1Button" class="Type1" value="First Category" readonly aria-label="First Category"/></div></td>
								<td><div><input type="text" id="sortByType2Button" class="Type2" value="Second Category" readonly aria-label="Second Category"/></div></td>
								<td><div><input type="button" id="sortByLocationButton" class="Location" value="Location" aria-label="Sort by location" /></div></td>
								<td><div><input type="button" id="sortByZipButton" class="Zip" value="Zip" aria-label="Sort by zip code" /></div></td>
								<td><div><input type="button" id="sortByDistanceButton" class="distance" value="Dist." aria-label="Sort by distance" /></div></td>
							</tr>
						</table>
					</div>
					<div id="resourceResults" tabindex="0" role="region" aria-label="Search Results">
					</div>
				</div>
				<div id="resourceDetail" class='silverGradient' role="region" aria-label="Resource details">
					<div id="resourceDetailData">
							<div type="text" class='resourceDetailidnum' id="resourceDetailidnum" aria-label="Resource ID number"></div>
							<div class='resourceDetailEdate' id="resourceDetailEdate" aria-label="Last updated date"></div>
							<div class='resourceDetailNormalWidth' id="resourceDetailName" draggable="true" aria-label="Resource name"></div><br />
						<form id="resourceDetailForm"><br />
							<label class='resourceDetailNormalLabelWidth' id="labelDistance">Distance</label><div class='resourceDetailZipWidth' id="resourceDetailDistance" aria-labelledby="labelDistance"></div><br />
							<label class='resourceDetailNormalLabelWidth' id="labelAddress">Address</label><div class='resourceDetailNormalWidth' id="resourceDetailAddress1" aria-labelledby="labelAddress"></div><br />
							<label class='resourceDetailNormalLabelWidth' for="resourceDetailContact">Contact</label><input type="text" class='resourceDetailNormalWidth'  id="resourceDetailContact" readonly /><br /><br />
							<label class='resourceDetailNormalLabelWidth' for="resourceDetailPhone">Phone</label><input type="text" class='resourceDetailPhoneWidth'  id="resourceDetailPhone" readonly />
							<label class='resourceDetailPhoneLabelWidth' for="resourceDetailEXT">Ext.</label><input type="text" class='resourceDetailPhoneWidth'  id="resourceDetailEXT" readonly /><br />
							<label class='resourceDetailNormalLabelWidth' for="resourceDetailHotline">Toll-Free</label><input type="text" class='resourceDetailPhoneWidth'  id="resourceDetailHotline" readonly />
							<label class='resourceDetailPhoneLabelWidth' for="resourceDetailFax">Fax</label><input type="text" class='resourceDetailPhoneWidth'  id="resourceDetailFax" readonly /><br />
							<label class='resourceDetailNormalLabelWidth' for="resourceDetailInternet">Email</label><input type="text" class='resourceDetailNormalWidth'  id="resourceDetailInternet" readonly /><br />
							<label class='resourceDetailNormalLabelWidth' id="labelWebsite">Website</label><div class='resourceDetailNormalWidth' id="resourceDetailWWWEB" aria-labelledby="labelWebsite"></div><br />
							<label class='resourceDetailNormalLabelWidth' id="labelWebsite2">Website 2</label><div class='resourceDetailNormalWidth' id="resourceDetailWWWEB2" aria-labelledby="labelWebsite2"></div><br />
							<label class='resourceDetailNormalLabelWidth' id="labelWebsite3">Website 3</label><div class='resourceDetailNormalWidth' id="resourceDetailWWWEB3" aria-labelledby="labelWebsite3"></div><br /><br />
							<label class='resourceDetailNormalLabelWidth' for="resourceDetailType1">Categories</label><input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType1" readonly aria-label="Category 1" />
							<input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType2" readonly aria-label="Category 2" /><br />
							<label class='resourceDetailNormalLabelWidth'></label><input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType3" readonly aria-label="Category 3" />
							<input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType4" readonly aria-label="Category 4" /><br />
							<label class='resourceDetailNormalLabelWidth'></label><input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType5" readonly aria-label="Category 5" />
							<input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType6" readonly aria-label="Category 6" /><br />
							<label class='resourceDetailNormalLabelWidth'></label><input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType7" readonly aria-label="Category 7" />
							<input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType8" readonly aria-label="Category 8" /><br /><br />

							<label id='resourceDetailLabelDescription' for="resourceDetailDescription">Description</label><div id="resourceDetailDescription" role="textbox" aria-readonly="true" aria-labelledby="resourceDetailLabelDescription"></div><br />
							<label id='resourceDetailLabelNote' for="resourceDetailNote">Note</label><span id='resourceDetailLabelStreetview' role="button" tabindex="0" aria-label="Toggle streetview">Streetview</span><br />
							<textarea class='resourceDetailNote'  id="resourceDetailNote" readonly aria-label="Resource notes"></textarea>
							<div id='resourceDetailStreetview' aria-label="Google Street View"></div>
						</form>
					</div>
				</div>
				<div id="timelinePane" class='redGradient'>
					<input id='timelineClose' type='button' value='Close' aria-label="Close timeline" />
					<div id="timelineData"></div>
				</div>			
			</div>

			<div id='newSearchPaneControls'>
				<input id='infoCenterMenu' type='button' value="INFO CENTER" />
				<span id='trainingSessionMuteIndicator'><strong>MIC Muted</strong></span>
				<?php
					if($resourceOnly) {
						echo "<input type='button' value='Copy List' id='copyableList' />";
					}
				?>
				<input type="button" id="mainNewSearchButton" class="defaultButton" value="RESOURCE SEARCH" />
			</div>
			<div id="resourceDetailControl" class=''>
				<input type="button" id="resourceDetailFirstButton" class='smallButton' value="<<-" aria-label="First result" />
				<input type="button" id="resourceDetailPreviousButton" class='smallButton' value="<-" aria-label="Previous result" />
				<input type="button" id="resourceDetailNextButton" class='smallButton' value="->" aria-label="Next result" />
				<input type="button" id="resourceShowListButton" class="defaultButton" value="LIST" aria-label="Return to results list" />
			</div>

			<div id="chatPane" class='redGradient' >
				<form id='chat1Controls' action="javascript:this.reset();">
					<input id='chat1EndButton' type="button" value="END CHAT" aria-label="End chat 1" />
					<select id='chat1Select' aria-label="Canned responses for chat 1">
						<?php
							echo $CannedTextMenu;
						?>
					</select>
					<input id='chat1PostButton' type="submit"  class="defaultButton" value="Post" aria-label="Send message in chat 1" />
				</form>
				<div id="chatBody1" role="log" aria-live="polite" aria-label="Chat 1 messages"></div>
				<textarea id="chatMessage1" aria-label="Type message to caller in chat 1"></textarea>
				<div id="chatBody2" role="log" aria-live="polite" aria-label="Chat 2 messages"></div>
				<textarea id="chatMessage2" aria-label="Type message to caller in chat 2"></textarea>
				<form name='chatForm2' id='chat2Controls' action="javascript:this.reset();">
					<input id='chat2EndButton' type="button" value="END CHAT" aria-label="End chat 2" />
					<select id='chat2Select' aria-label="Canned responses for chat 2">
						<?php
							echo $CannedTextMenu;
						?>
					</select>
					<input id='chat2PostButton' type="submit" class="defaultButton" value="Post" aria-label="Send message in chat 2" />
				</form>
			</div>
			<div id='callHistoryPane' class='redGradient'>
				<h2 id='callHistoryHotline'></h2>
				<h3 id='callHistoryWarning'>Connected Calls<br />(most recent 3 months only)</h3><h4>Caution:  do not share with caller</h4>
				<h3 id='callHistoryLocation'></h3>
				<div id='callHistoryData'></div>
				<div id='callHistoryButtons'>
					<input type="button" value="End Call" id="callHangUpButton" class="defaultButton" aria-label="End current call" />
					<span id='genderDisplayMessage'></span>
					<input type="button" value="Block Caller" id="callBlockButton" aria-label="Block this caller" />
				</div>
			</div>
			<div id="logPane" class="logPane redGradient" >
				<div id="activeLogPane" class="3">
					<form id='logPaneForm' name='logPaneForm' action="postCallLog.php">
						<?php
							include("LogPane-NonSage.inc");
						?>
					</form>
				</div>
				<div id="inactiveLogPane"></div>
			</div>
			<div id="oneChatOnlyDiv" class="redGradient">
				<?php
					if($oneChatOnly) {
						echo "<label for='oneChatOnly'><input type='checkbox' name='OneChatOnly' id='oneChatOnly' value='1' checked /><span>ONE CHAT ONLY</span></label>";
					} else {
						echo "<label for='oneChatOnly'><input type='checkbox' name='OneChatOnly' id='oneChatOnly' value='1' /><span>ONE CHAT ONLY</span></label>";
					}
				?>
			</div>
			<div id="ExitButton" class="redGradient">
				<audio id="chatSound" volume=0.5 src="chat.wav" autobuffer="true"></audio> 

				<?php
					if($UserID == 'Travelstead' || $UserID == 'Aaron123' || $UserID == 'Gabe0896' || $UserID == 'Shadarko1' || $UserID == 'Tanya101') {
						echo "<audio id='inviteSound' volume=0.5 src='Audio/Gabe_Invite.mp3' autobuffer='true'></audio>"; 
						echo "<audio id='IMSound' volume=0.5 src='Audio/Gabe_IM.mp3' autobuffer='true'></audio>" ;
						echo "<audio id='message1Sound' volume=0.5 src='Audio/Gabe_Message_1.mp3' autobuffer='true'></audio>"; 
						echo "<audio id='message2Sound' volume=0.5 src='Audio/Gabe_Message_2.mp3' autobuffer='true'></audio>";
						echo "<audio id='End1Sound' volume=0.5 src='Audio/Gabe_End_Chat_1.mp3' autobuffer='true'></audio>"; 
						echo "<audio id='End2Sound' volume=0.5 src='Audio/Gabe_End_Chat_2.mp3' autobuffer='true'></audio>";
					}
				?>



				<span>EXIT PROGRAM</span>
			</div>
			<div id="statsButton" class="redGradient">STATISTICS</div>
			<div id="timer1" class='timers'></div>
			<div id="timer2" class='timers'></div>
 			<?php
				echo "<input type='hidden' id='token' value = ''>";
				echo "<input type='hidden' id='volunteerID' value = '".$UserID."'>";
				echo "<input type='hidden' id='chatOnlyFlag' value = '".$ChatOnly."'>";
				echo "<input type='hidden' id='userLoggedOn' value = '".$LoggedOn."'>";
				echo "<input type='hidden' id='trainer' value = '".$trainer."'>";
		 		// Set assignedTraineeIDs based on role
				$assignedTraineeIDsValue = "";
				if ($trainer == 1 && isset($assignedTrainees)) {
					$assignedTraineeIDsValue = $assignedTrainees;
				} elseif ($trainee == 1) {
					$assignedTraineeIDsValue = $trainee;
				}
				echo "<input type='hidden' id='assignedTraineeIDs' value = '".$assignedTraineeIDsValue."'>";
				echo "<input type='hidden' id='trainee' value = '".$trainee."'>";
				echo "<input type='hidden' id='monitor' value = '".$monitor."'>";
				echo "<input type='hidden' id='serverOffsetMinutes' value = '".$mins."'>";
				echo "<input type='hidden' id='volunteerSessionId' value = '".$_SESSION['volunteer_session_id']."'>";
				
				// Additional fields for multi-trainee screen sharing compatibility
				$trainerIDValue = $trainerID ?? '';
				$traineeIDValue = isset($traineeID) ? $traineeID : ($trainee == 1 ? $UserID : '');

				// CRITICAL: Log hidden field values to verify they're populated correctly
				if ($trainee == 1 && empty($trainerIDValue)) {
					error_log("CRITICAL: Trainee $UserID has EMPTY trainerID hidden field! This will cause 400/500 errors.");
				}
				error_log("DEBUG: Hidden fields - UserID=$UserID, trainerID='$trainerIDValue', traineeID='$traineeIDValue', trainer=$trainer, trainee=$trainee");

				echo "<input type='hidden' id='trainerID' value = '".$trainerIDValue."'>";
				echo "<input type='hidden' id='traineeID' value = '".$traineeIDValue."'>";
				
				// User names for training display
				$currentUserName = $FirstName . " " . $LastName;
				echo "<input type='hidden' id='currentUserName' value = '".$currentUserName."'>";
				echo "<input type='hidden' id='trainerName' value = '".$trainerName."'>";
			?>
			<script>
				// Make session ID available to JavaScript
				window.volunteerSessionId = document.getElementById('volunteerSessionId').value;
				console.log('Session ID for Socket.IO:', window.volunteerSessionId);
			</script>
		<div id="videoWindow" class="<?php echo $lastModFile; ?>">
            <h2>Please watch this short message before beginning today’s shift:</h2>
            <video id='video1'>
                <?php
                	echo "<source src=".$lastModFile." type='video/mp4'>";
                	echo "<source src=".$lastModFile." type='video/ogg'>";
                ?>
                Your browser does not support HTML5 video.
            </video>
            <button id='videoPlayButton' onclick='playPauseVideo()'>Play/Pause</button>
		</div>
		<!-- Training System: Video element for receiving trainer's shared screen -->
		<!-- FIXED Jan 28, 2025: Updated poster path from trainingShare/ to trainingShare3/, added playsinline for mobile -->
		<video id='remoteVideo' poster="trainingShare3/poster.png" autoplay playsinline></video>
		<video id='localVideo' autoplay muted style="display: none;"></video>
<!--    <button id="start-call-monitor" style="padding: 10px 20px; font-size: 16px;">
        	Start Call Monitor
    	</button>
-->
		<!-- Screen Reader Announcement Regions -->
		<div id="sr-announcements" aria-live="polite" aria-atomic="true" role="status"></div>
		<div id="sr-announcements-urgent" aria-live="assertive" aria-atomic="true" role="alert"></div>
	</body>
</html>

