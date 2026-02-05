<?php
session_start();



if(!isset($_SESSION['Moderator'])) {
	session_unset();
	session_destroy();
	session_write_close();
	setcookie(session_name(),'',0,'/');
	session_start();
	session_regenerate_id(true);
}




error_reporting(0);
ini_set('display_errors', 0);

include('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');


header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate( 'D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache'); 



    
function getRealIpAddr()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
    {
        $ip=$_SERVER['HTTP_CLIENT_IP'];
    }
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
    {
        $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    else
    {
        $ip=$_SERVER['REMOTE_ADDR'];
    }
    
    if(empty($ip)) {
    	$ip = 0;   	
    }
    return $ip;
}
    
        
function randomString($length) {
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    $str = NULL;
    $i = 0;
    while ($i < $length) {
        $num = rand(0, 61);
        $tmp = substr($chars, $num, 1);
        $str .= $tmp;
        $i++;
    }
    return $str;
}



$mobile = $_SESSION['mobile'] ?? null;
$page = $_SERVER['HTTP_REFERER'] ?? null;
$host = parse_url($page, PHP_URL_HOST);
$sessionPage = $_SESSION['page'] ?? null;

if($page != $sessionPage && ($host != "vcctest.org" || $host != "travelsteadlaw.com")) {
	$_SESSION['ChatRoomID'] = null;
	$_SESSION['ChatRoomName'] = null;
	$_SESSION['page'] = $page;
	if(!isset($_SESSION['Moderator'])) {
		$_SESSION["UserID"] = null;
	}
}

// Check if user is a Group Chat monitor from VCC and set Moderator status
if (!isset($_SESSION["Moderator"]) && isset($_SESSION["UserID"])) {
    $userID = $_SESSION["UserID"];
    $query = "SELECT groupChatMonitor FROM Volunteers WHERE UserName = ?";
    $result = groupChatDataQuery($query, [$userID]);
    if (!empty($result) && $result[0]->groupChatMonitor == 1) {
        $_SESSION["Moderator"] = 1; // Set as visible moderator for Group Chat monitors
    }
}

$Moderator = $_SESSION["Moderator"] ?? false;
$chatRoomID = $_REQUEST["ChatRoomID"];
$chatRoomName = $_SESSION["ChatRoomName"] ?? false;				
$chatRoomStatus = null;
$chatRoomList = array();

if(!$Moderator && isset($chatRoomID)) {  
	$params = null;
	$query = "SELECT * from groupChatRooms WHERE id = ".$chatRoomID;	
	$result = groupChatDataQuery($query, $params);

	if ($result) {
		foreach($result as $item) {
			foreach($item as $key=>$value) {
				$hostSite =  parse_url($value, PHP_URL_HOST);
				if($key == "id") {
					$chatRoomID = $value;
					$_SESSION["ChatRoomID"] = $value;
				} else if ($key == "Name") {
					$chatRoomName = $value ?? NULL; 
					$_SESSION["ChatRoomName"] = $value ?? NULL;				
                } else if ($key == "URL") {
                    $chatRoomList[$value] = true;
                    $chatRoomList[$hostSite] = true;
                } else if ($key == "URL2") {
                    $chatRoomList[$value] = true;
                    $chatRoomList[$hostSite] = true;
                } else if ($key == "URL3") {
                    $chatRoomList[$value] = true;
                    $chatRoomList[$hostSite] = true;
                } else if ($key == "Open") {
					$chatRoomStatus = $value ?? NULL;
                }
			}
		}
	}
}

/*
if(!isset($chatRoomList[$page]) && !$Moderator && !isset($chatRoomList[$host])) {
    echo "<html><head><style>.defaultButton  {
		border-radius:        15px;
		background-image: -o-linear-gradient(top, #D3F7FD 0%, #87C5FB 50%,#A1D1F9 50%,#D4E9FC 100%);
		background-image: -moz-linear-gradient(top, #D3F7FD 0%, #87C5FB 50%,#A1D1F9 50%,#D4E9FC 100%);
		background-image: -webkit-linear-gradient(top, #D3F7FD 0%, #87C5FB 50%,#A1D1F9 50%,#D4E9FC 100%);
		background-image: -ms-linear-gradient(top, #D3F7FD 0%, #87C5FB 50%,#A1D1F9 50%,#D4E9FC 100%);
		background-image: linear-gradient(to top, #D3F7FD 0%, #87C5FB 50%,#A1D1F9 50%,#D4E9FC 100%);
		}
		p {color:red;border: 1px solid black;
		margin:auto;height: 30px;text-align: center;line-height:30px;vertical-align:middle;border-radius: 15px;100px;width:50%;}h1 {color: black;text-align: center;width:100%}</style></head><body><h1>You may be in privacy mode.</h1><h1>Click below to enter room.</h1><p class='defaultButton'>CLICK HERE</p></body></html>";
}
*/
    


$ipAddress = $_SESSION['ipAddress'] = getRealIpAddr();
$userID = $_SESSION["UserID"];
$referringPage = $_SERVER['HTTP_REFERER'] ?? null;
$_SESSION['referringPage'] = $referringPage;

    
$_SESSION['CallerID'] = $userID;


if($Moderator == 2) {
	$params = [$userID];	
	$query = "UPDATE callers Set Moderator = 2, status = 0 WHERE userID = ?";
	$result = groupChatDataQuery($query, $params);
}


$params = [$userID, $chatRoomID];	
$query = "SELECT COUNT(id) as 'exists' from callers where userID = ? and chatRoomID = ?";
$result = groupChatDataQuery($query, $params);

foreach($result as $item) {
	foreach($item as $key=>$value) {
		if($value == 0) {
			if(!isset($Moderator) || !$Moderator) {
				$userID = randomString(20);
				$_SESSION["UserID"] = $userID;
				$_SESSION['CallerID'] = $userID;
			}
			$params = [$userID, $chatRoomID, $ipAddress];	
			$query = "INSERT INTO callers VALUES (null, ?, null , null , ? , ?, 0, now(), null, null, null)
					ON DUPLICATE KEY UPDATE modified = now()";
			$result = groupChatDataQuery($query, $params);
		}
	}
}


header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past


?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta HTTP-EQUIV="CACHE-CONTROL" CONTENT="no-store" />
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black">
	<meta NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
	<META HTTP-EQUIV="Expires" CONTENT="-1">
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no" />
	<title>LGBT Help Center Group Chat</title>
	<link type="text/css" rel="stylesheet" href="groupChat.css">
	<link type="text/css" rel="stylesheet" href="nicEditPanel.css">
	<script src = 'https://cdnjs.cloudflare.com/ajax/libs/fingerprintjs2/2.0.1/fingerprint2.min.js' type='text/javascript'></script>
	<script src="nicEdit/nicEdit.js" type="text/javascript"></script>
	<script src="chatCallerData.js" type="text/javascript"></script>


	<script src="groupChat.js" type="text/javascript"></script>
	<script src="../LibraryScripts/Ajax.js" type="text/javascript"></script>
	<script src="../LibraryScripts/domAndString.js" type="text/javascript"></script>
	<script src="../LibraryScripts/Dates.js" type="text/javascript"></script>
		
</head>
	<nav id='menuButton' title="Click to show group members.">&#9776</nav>
	<body id = "body" onbeforeunload="return improperExit()" onpagehide="improperExit()">
		<div id='groupChatPortraitMode'>
			<div id='groupChatRoomName'>
				<h1><?php echo $chatRoomName; ?></h1>		
				<a id='escapeLink' href="https://google.com">
					<img src="Images/escape-info3.jpg" title='Click to get away from this screen quickly'/>
				</a>
				</span>
			</div>
			<div id='groupChatUpperChatArea' >
				<div id='groupChatMainWindow' class ="div">
				</div>
				<div id="groupChatMemberListContainer" class="hideMembers">
					<div id='groupChatMembersListWindow'  class ="div">
						<h3><stong>GROUP MEMBERS</strong></h3>
					</div>  
					<div id='emojiInstructions' class='div'><strong><u>Entering Emojis:</u></strong><br>
						<span><strong>Mac: </strong>Command + Control + Space</span><br>
						<span><strong>PC: </strong>Win + period (.)</span>
					</div>
					<div id="roomMemberCount" class ="div"><strong>Number in Room: </strong><span id="groupChatNumberCount">0</span></div>
				</div>
			</div>		
			<div id="groupChatTypingWrapper">
				<div id="groupChatTypingWindow"></div> 
				<div id='groupChatControlButtonArea' >
					<input type='button' value="EXIT" id="groupChatExitButton"/>
					<input type='submit' value="SUBMIT" id="groupChatSubmitButton"/>
					<div id="groupChatScroll">
						<input type='checkbox' value="1" id="groupChatScrollCheckBox" checked/><label>Scroll down for new messages</label>
						<input type='hidden' value="0" id="LastMessage"/>
						<input type='hidden' value="Tim" id="userName"/>
						<?php 
							if($Moderator) {
								echo "<input type='checkbox' value='1' id='groupChatNoNewMembers' /><label>Prevent New Members</label>";
							}
							echo "<input type='hidden' value='".trim($userID)."' id='userID'/>";
							echo "<input type='hidden' value='".trim($ipAddress)."' id='ipAddress'/>";
							echo "<input type='hidden' value='".trim($chatRoomID)."' id='groupChatRoomID'/>";
							echo "<input type='hidden' value='".trim($Moderator)."' id='groupChatModeratorFlag'/>";
							echo "<input type='hidden' value='".trim($page)."' id='groupChatPageReferrer'/>";
							echo "<input type='hidden' value='".trim($mobile)."' id='groupChatMobileFlag'/>";
							echo "<input type='hidden' value='".trim($chatRoomStatus)."' id='groupChatRoomStatus'/>";
						?>
					</div>

				</div>
			</div> 
			<div id="groupChatSignIn">
				<?php 
					if($Moderator) {
						echo "<h3>Moderator Login To Group Chat</h3>";
					} else {
						echo "<h3>Login To Join Chat</h3>";
					 }
					?>
				<div name="groupChatSignInForm" id="groupChatSignInForm">
					<label>Name:</label><input type="text" name="groupChatSignInFormName" id="groupChatSignInFormName" maxlength="30"/><br />
					<label>Avatar:</label>
					<div id='groupChatAvatarSelectionArea'></div>
					<input type="button" id="groupChatSignInFormSubmit" name="groupChatSignInFormSubmit" value="Login"/>
				</div>
			</div>
		</div>
		<div id='groupChatLandscapeMode'>
			Please Use Portrait Orientation.
		</div>
	</body>
</html>
