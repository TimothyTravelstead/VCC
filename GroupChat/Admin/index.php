<?php
// CRITICAL: Comprehensive anti-caching headers - MUST be first
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");

// Check if user is logged in and has admin privileges (matching main Admin system)

require_once('../../../private_html/db_login.php');
session_start();
if (@$_SESSION["auth"] != "yes") {
    die("Unauthorized");
} 

// Determine moderator type based on how they arrived
if (!isset($_SESSION['ModeratorType'])) {
    // Coming from admin panel iframe, not direct login
    if ($_SESSION["auth"] == "yes" && $_SESSION["AdminUser"] == true) {
        $_SESSION['ModeratorType'] = 'admin';
    }
}

// Set behavior based on type
$isAdminModerator = ($_SESSION['ModeratorType'] === 'admin');
$showAdminFeatures = $isAdminModerator; // Clear variable for UI control

function vccLoggedOnStatus($userID) {
	// Use the main dataQuery function for database access
	$query = "SELECT LoggedOn FROM Volunteers WHERE UserName = ?";
	$params = [$userID];
	$result = dataQuery($query, $params);
	
	if ($result && count($result) > 0) {
		return $result[0]->LoggedOn;
	}
	return 0;
}

header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate( 'D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache'); 
header('Content-Type: text/html; charset=utf-8');



$userID = $_SESSION["UserID"];

// Release session lock after reading session data
session_write_close();

$page = $_SERVER['HTTP_REFERER'];


//Info Center List	
	$InfoCenterArray= Array();
	$InfoCenterButtons = "";

	if ($handle = opendir('../../InfoCenter/')) {
		while (false !== ($entry = readdir($handle))) {
			if ($entry != "." && $entry != "..") {
				$extn = explode('.',$entry);
				if($extn[0]) {
					$InfoCenterArray[$extn[0]] = "<input type='button' class='infoCenterButton' value='".$extn[0]."' />";
				}
			}
		}
		ksort($InfoCenterArray);
		foreach ($InfoCenterArray as $value) {
			$InfoCenterButtons .= $value;
		}
		closedir($handle);
	}



$params = [];	
$query = "Select id, name, url from groupChatRooms";
$result = dataQuery($query, $params);
$chatRoomOptions = "";

foreach($result as $item) {
	foreach($item as $key=>$value) {
		switch($key) {
			case 'id':
				$id = $value;
				break;	
			case 'name':
				$name = $value;
				break;			
			case 'url':
				$URL = $value;
				break;			
		}
	}
	$chatRoomOptions .= "<option id='".$id."' value='".$URL."'>".$name."</option>";
}


// $adminMonitor = vccLoggedOnStatus($userID); // No longer needed - using ModeratorType instead




?>

<?php $adminCacheBuster = microtime(true) . '_' . mt_rand(100000, 999999); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta HTTP-EQUIV="CACHE-CONTROL" CONTENT="NO-CACHE, NO-STORE, MUST-REVALIDATE" />
	<meta NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
	<meta http-equiv="Pragma" content="no-cache" />
	<meta http-equiv="Expires" content="0" />
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1.0">
	<script src="../../LibraryScripts/Ajax.js?cb=<?php echo $adminCacheBuster; ?>" type="text/javascript"></script>
	<script src="../../LibraryScripts/domAndString.js?cb=<?php echo $adminCacheBuster; ?>" type="text/javascript"></script>
	<script src="../../LibraryScripts/Dates.js?cb=<?php echo $adminCacheBuster; ?>" type="text/javascript"></script>
	<script src="groupChatAdmin.js?cb=<?php echo $adminCacheBuster; ?>" type="text/javascript"></script>


	<style>
		h1 {
			display:			inline-block;
			margin-left:		100px;
		}
	
		#infoCenterPane {
			position:			absolute;
			top:				160px;
			left:				600px;
			height:				420px;
			width:				802px;
			white-space:		nowrap;
			overflow:			hidden;
			border:				3px solid yellow;
			display:			none;
			border-radius:		10px;
			margin-bottom:		0px;
			background: 		linear-gradient(#550000,  #770000);
			box-shadow:			10px 10px 10px black;


		}

	
		#infoCenterButtons {
			position:           absolute;
			top:                0px;
			left:               2px;
			width:              200px;
			height:             200px;
			border-radius:		0px;
			height:				479px;
			overflow:			scroll;
			font-size:			90%;	
		}
		
		.infoCenterButton {
			background: 		linear-gradient(silver,  white);
			font-family:        Arial, Sans-Serif;
			font-size:          16px;
			line-height:        10px;
			font-weight:        bold;
			text-align:         left;
			display:            block;
			width:              200px;
			padding-top:        7px;
			padding-bottom:     7px;
			padding-left:       3px;
			font-size:          93%;
		}


		#infoCenterText {
			background-color:	silver;
			position:			absolute;
			top:				0px;
			left:				205px;
			height:				365px;
			width:				575px;
			border-radius:		5px;
			white-space:		normal;
			overflow:			hidden;
			color:              black;
			padding:            5px;
			background: -moz-linear-gradient(bottom,  #DDDDDD,  silver); /* for firefox 3.6+ */
			text-align:			left;
			font-size:			95%;
		}

		#infoCenterText table {
			position:           relative;
			top:                30px;
			left:               0px;
			text-align:         center;
		}

		#infoCenterText td {
			border:             2px solid black;
			padding:            3px;
			text-align:         left;
		}

		#infoCenterClose {
			position:			absolute;
			top:				400px;
			left:				750px;
			z-index:			500000000000000;
		}




		#infoCenterMenu {
			display:			inline-block;
			color:              black;
			font-weight:		bold;
			padding-left:		5px;
			width:              145px;
			height:				25px;
			font-size:			9pt;
			white-space:		nowrap;
			cursor:             pointer;
			margin-left:		20px;
			margin-top:			1px;
			margin-bottom:		1px;
			font-size:			130%;
			border-radius:		3px;
			float:				left;

			border: 0 !important;  /*Removes border*/
			-webkit-appearance: none;  /*Removes default chrome and safari style*/
			-moz-appearance: none; /* Removes Default Firefox style*/
			text-indent: 0.01px; /* Removes default arrow from firefox*/
			text-overflow: "";  /*Removes default arrow from firefox*/
			
			background-color:	rgba(100,250,100,1);
		}


		#titleBar {
			position:			relative;
			top:				0px;
			left:				0px;
			height:				auto;
			width: 				97.5%;
			border:				1px solid gray;
			border-radius:		10px;
			color:				rgba(150,150,150,1);
			background-color:	maroon;
			line-height: 		1.6em;
			margin-bottom: 		10px;
			background: 		linear-gradient(#550000,  #770000);
			text-align:			left;
		}

		
		#VCCIMButton {
			display:	none;
			float:		left;
			margin-left: 20px;

		}
		
		#volunteerList {
			position:			relative;
			top:				5px;
			height:				150px;
			width:				auto
			min-width:			60%;
			margin: 			0 auto;
			background: 		radial-gradient(rgba(160,160,160,1), rgba(200,200,200,1));
			padding:			0px;
			display:			block;
			overflow:			auto;
			border:				1px solid black;
			color:				maroon;
			z-index:			100;

		}

		#volunteerListHaeder {
			font-size:			125%;
			line-height:		1.1em;
			font-weight:		bold;
			text-decoration:	underline;
			margin:				0 auto;
			display:			inline-block;
		}
					
		#volunteerList table {
			margin:				auto;
		}
		
		#volunteerList th {
			width:				auto;
			min-width:			30px;
			text-decoration:	underline;
		}
		
		.hover:hover {
			cursor:				pointer;
			background: linear-gradient(to top, #D3F7FD 0%, #87C5FB 50%,#A1D1F9 50%,#D4E9FC 100%);
		}

		
		
		
					
		#groupChatMonitor1 {
			margin-right: 1.5%;
		}
        
        .roomCloseButton {
            display: none;
                                        
        }
		
		#groupChatMonitor2 {
			display: none;
		}
		
		body {
			width: 		100%;
			min-width:	1000px;
			margin:		auto;
			text-align: center;

		}
		
		div {
			display: inline-block;
			width: 	47.5%;
			height: 700px;
		}
		
		iframe {
			width: 100%;
			border: none;
			background: gray;
			border-radius: 5px;
		}
		
		#ExitButton {
			position:			absolute;
			background-color:	#990000;
			top:				10px;
			left:				90%;
			height:				40px;
			width:				131px;
			white-space:		nowrap;
			overflow:			hidden;
<?php
	if($showAdminFeatures) {
		echo "display:  none;";
	} else {
		echo "display:			block;";
	}
?>			
			border:             3px outset #A08010;
			font-family:        arial, san-serif;
			font-size:          100%;
			text-align:         center;
			font-weight:        bold;
			cursor:             pointer;
			background:		 	radial-gradient(#BB0000, #880000);
			border-radius:		15px;
			vertical-align: 	middle;
			color: 				silver;
		}

		.imPane {
			position:			absolute;
			top:				470px;
			left:				20px;
			height:				320px;
			width:				280px;
			background:			rgba(265,165,0,1);
			z-index:			100000;
			box-shadow:			10px 10px 10px black;
			border-radius:		10px;
			display:			none;
			color:				black;

		}
			
		.imPane div {
			position:			relative;
			margin-left:		10px;
			margin-top:			20px;
			height:				140px;
			width:				257px;
			text-align:			left;
			overflow:			auto;
			text-wrap:			auto;
			border-radius:		5px;
			background-color:	white;
		}	
		
		.imMessage {
			display:			block;
			position:			relative;
			margin-left:		10px;
			margin-top:			5px;
			width:				252px;
			height:				38px;
			border-radius:		5px;
			display:			block;
			background-color:	white;
			font-family:		Times New Roman;
			font-size:			12pt;
		}
		
			

		
	</style>
	<title>LGBT Help Center - Group Chat Monitor</title>	
</head>
<body id = "body">
  	<div id="titleBar">	  		
		<input type='button' id='VCCIMButton' value='VCC Users' />				
		<input id='infoCenterMenu' type='button' value="INFO CENTER" />
		<h1>LGBT National Help Center - Group Chat Monitor</h1>
		<input type='button' id="ExitButton" value = "EXIT">
  	</div>
	<div id='groupChatMonitor1'>
		<select id="groupChatMonitor1RoomSelector">
			<option id='0' value='none'>None</option>
			<?php
				echo $chatRoomOptions;
			?>
		</select>
        <input class='roomCloseButton' type='button' id='groupChatMonitor1CloseButton' name='groupChatMonitor1CloseButton' value='Close Room' />
		<iframe id='groupChatAdminFrame1' src="" width="100%" height="100%">Is this Working?</iframe>
		<h2 id='volunteerListHaeder'>Hotline Volunteers</h2>
		<div id='volunteerList'>
			<table >
				<thead>
					<tr id="userListHeader"><th>Name</th><th> </th><th>Call</th><th>Chat</th></tr>
				</thead>
				<tbody id='volunteerListTable'>
				</tbody>
			</table>
		</div>
	</div>

	<div id='groupChatMonitor2'>
		<select id="groupChatMonitor2RoomSelector">
			<option id='0' value='none'>None</option>
			<?php
				echo $chatRoomOptions;
			?>
		</select>
        <input class='roomCloseButton' type='button' id='groupChatMonitor2CloseButton' name='groupChatMonitor2CloseButton' value='Close Room' />
		<iframe id='groupChatAdminFrame2' src="" width="100%" height="100%">Is this Working?</iframe>
	</div>
	<div id="infoCenterPane" class='redGradient' draggable="true">
		<div id='infoCenterButtons'>
			<?php
				echo $InfoCenterButtons;
			?>
		</div>
		<div id="infoCenterText"></div>
		<input type="button" id="infoCenterClose" value="Close" />
	</div>

	<input type="hidden" value="
		<?php 
			echo $userID;
		?>" id="AdministratorID" />
	<input type="hidden" value="<?php echo $showAdminFeatures ? '1' : '0'; ?>" id="showAdminFeatures" />
	




</body>
</html>
