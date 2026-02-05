<?php
	// Include database connection FIRST to set session configuration before session_start()
	// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
	require_once('../../private_html/db_login.php');

	session_cache_limiter('nocache');
	session_start();

	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);

	error_reporting(E_ERROR | E_WARNING | E_PARSE);

if ($_SESSION['auth'] != 'yes') {
    http_response_code(401); // Set the HTTP status code to 401
    die("Unauthorized"); // Output the error message and terminate the script
}

// Release session lock immediately after reading session data
session_write_close();
	
?>
<!DOCTYPE html>
<html>
	<head>
		<meta HTTP-EQUIV="CACHE-CONTROL" CONTENT="no-cache, no-store, must-revalidate" />
		<meta http-equiv="cache-control" content="max-age=0" />
		<meta http-equiv="expires" content="-1" />
		<meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT" />
		<meta http-equiv="pragma" content="no-cache" />
		<meta http-equiv="content-type" content="text/html;charset=utf-8" />
		<meta NAME="ROBOTS" CONTENT="NONE" />    
		<meta NAME="GOOGLEBOT" CONTENT="NOARCHIVE" />
		<script src="../LibraryScripts/Ajax.js" type="text/javascript"></script>
		<script src="AdminMini.js" type="text/javascript"></script>
		<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBL_Mp0VJY5mOM1uGc-jT41FCN2z3XP5Z0" type="text/javascript"></script>
		<link rel="stylesheet" href="../index.css" />
		<title>LGBT Hotline - Volunteer Communication System</title>
	</head>
	<body>
		<div id="searchParameters"></div>
		<div id="newSearchPane" class='redGradient' >
			<h2 id="volunteerListLabel"></h2>
			<div id="searchBox" draggable="true">
				<form id="searchBoxForm" action="javascript:validate()">
					<table>
						<tr><th>Zip/Postal:</th><td><input type="text" id="ZipCode" /></td></tr>
						<tr><th>Range:</th><td><input type="text" id="Distance" /></td></tr>
						<tr><th>City:</th><td><input type="text" id="City" /></td></tr>
						<tr><th>State/Prov.:</th><td><input type="text" id="State" /></td></tr>
						<tr><th>Name:</th><td><input type="text" id="Name" /></td></tr>
					</table>
					<input type='button' value="NATIONAL" class="nationalSearch hover" id="nationalSearch" />							
					<select class="internationalSearch hover" id="internationalSearch"></select></td>
					<input type="button" class="hover" value="Find Zip" id="findZipSearch" />
					<input type="Submit" class="mainSearch defaultButton" value="Search" />
				</form>
			</div>
		</div>
		<div id="resourceList" class='redGradient'>
			<?php
				include("../categories.inc");
			?>
			<div id="resourceListCategory"></div>
			<div id="resourceListCount"></div>
			<div id="resultsControls">
				<table>
					<tr><td class='resourceCounter' ></td>
						<td><div><input type="button" id="sortByNameButton" class="Name" value="Name" /></div></td>
						<td><div><input type="text" id="sortByType1Button" class="Type1" value="First Category" readonly/></div></td>
						<td><div><input type="text" id="sortByType2Button" class="Type2" value="Second Category" readonly/></div></td>
						<td><div><input type="button" id="sortByLocationButton" class="Location" value="Location" /></div></td>
						<td><div><input type="button" id="sortByZipButton" class="Zip" value="Zip" /></div></td>
						<td><div><input type="button" id="sortByDistanceButton" class="distance" value="Dist." /></div></td>
					</tr>
				</table>
			</div>
			<div id="resourceResults">
			</div>
		</div>
		<div id="resourceDetail" class='silverGradient' >
			<div id="resourceDetailData">				
					<div type="text" class='resourceDetailidnum' id="resourceDetailidnum"></div>
					<div class='resourceDetailEdate' id="resourceDetailEdate"></div>
					<div class='resourceDetailNormalWidth' id="resourceDetailName" draggable="true"></div><br />
				<form id="resourceDetailForm"><br />
					<label class='resourceDetailNormalLabelWidth'>Distance</label><div class='resourceDetailZipWidth' id="resourceDetailDistance"></div><br />
					<label class='resourceDetailNormalLabelWidth'>Address</label><div class='resourceDetailNormalWidth' id="resourceDetailAddress1"></div><br />
					<label class='resourceDetailNormalLabelWidth'>Contact</label><input type="text" class='resourceDetailNormalWidth'  id="resourceDetailContact" readonly /><br /><br />
					<label class='resourceDetailNormalLabelWidth'>Phone</label><input type="text" class='resourceDetailPhoneWidth'  id="resourceDetailPhone" readonly />
					<label class='resourceDetailPhoneLabelWidth'>Ext.</label><input type="text" class='resourceDetailPhoneWidth'  id="resourceDetailEXT" readonly /><br />
					<label class='resourceDetailNormalLabelWidth'>Toll-Free</label><input type="text" class='resourceDetailPhoneWidth'  id="resourceDetailHotline" readonly />
					<label class='resourceDetailPhoneLabelWidth'>Fax</label><input type="text" class='resourceDetailPhoneWidth'  id="resourceDetailFax" readonly /><br />
					<label class='resourceDetailNormalLabelWidth'>Email</label><input type="text" class='resourceDetailNormalWidth'  id="resourceDetailInternet" readonly /><br />
					<label class='resourceDetailNormalLabelWidth'>Website</label><div class='resourceDetailNormalWidth'  id="resourceDetailWWWEB"></div><br />
					<label class='resourceDetailNormalLabelWidth'>Website 2</label><div class='resourceDetailNormalWidth'  id="resourceDetailWWWEB2"></div><br />
					<label class='resourceDetailNormalLabelWidth'>Website 3</label><div class='resourceDetailNormalWidth'  id="resourceDetailWWWEB3"></div><br /><br />
					<label class='resourceDetailNormalLabelWidth'>Categories</label><input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType1" readonly/>
					<input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType2" readonly /><br />
					<label class='resourceDetailNormalLabelWidth'></label><input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType3" readonly />
					<input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType4" readonly /><br />
					<label class='resourceDetailNormalLabelWidth'></label><input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType5" readonly/>
					<input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType6" readonly /><br />
					<label class='resourceDetailNormalLabelWidth'></label><input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType7" readonly />
					<input type="text" class='resourceDetailTypeWidth'  id="resourceDetailType8" readonly /><br /><br />
					
					<span id='resourceDetailLabelDescription'>Description</span><div id="resourceDetailDescription" readonly></div><br />							
					<span id='resourceDetailLabelNote' >Note</span><span id='resourceDetailLabelStreetview'>Streetview</span><br />
					<textarea class='resourceDetailNote'  id="resourceDetailNote" readonly></textarea>
					<div id='resourceDetailStreetview'></div>
				</form>
			</div>			
		</div>

		<div id='newSearchPaneControls'>
			<input type="button" id="mainNewSearchButton" class="defaultButton" value="RESOURCE SEARCH" />
		</div>
		<div id="resourceDetailControl" class=''>
			<input type="button" id="resourceDetailFirstButton" class='smallButton' value="<<-" />
			<input type="button" id="resourceDetailPreviousButton" class='smallButton' value="<-" />
			<input type="button" id="resourceDetailNextButton" class='smallButton' value="->" />
			<input type="button" id="resourceShowListButton" class="defaultButton" value="LIST" />
		</div>


	</body>
</html>
