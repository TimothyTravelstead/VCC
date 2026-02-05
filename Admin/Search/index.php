<?php
	// Include database connection FIRST to set session configuration before session_start()
	// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
	require_once("../../../private_html/db_login.php");
	require_once("../../../private_html/csrf_protection.php");

	// Now start the session with the correct configuration
	session_start();

	if (@$_SESSION["auth"] != "yes") {
		die("Unauthorized");
	} 
?>

<!DOCTYPE html>
<html itemscope="" itemtype="http://schema.org/WebPage" lang="en">
	<head>
		<meta charset="UTF-8">
		<meta NAME="ROBOTS" CONTENT="NONE" />    
		<meta NAME="GOOGLEBOT" CONTENT="NOARCHIVE" />
		<title>LGBT National Help Center Search</title>
		<link type='text/css' rel='stylesheet' href='detailPane.css' />
		<script src="../../LibraryScripts/ConsoleCapture.js"></script>
		<script src="../../LibraryScripts/ErrorModal.js"></script>
		<script src="../../LibraryScripts/Ajax.js"></script>
		<script src="index.js"></script>
		<script src="../../LibraryScripts/domAndString.js"></script>
		<script src="../../LibraryScripts/Dates.js"></script>
		<script src="https://cdn.apple-mapkit.com/mk/5.x.x/mapkit.js"></script>
		<?php echo getCSRFJavaScript(); ?>
		<script>
		/**
		 * CSRF Token Auto-Refresh System
		 *
		 * Automatically refreshes the CSRF token every 50 minutes to prevent
		 * 403 errors during extended Resource Admin work sessions.
		 *
		 * The token normally expires after 1 hour, but we refresh at 50 minutes
		 * to provide a 10-minute safety buffer.
		 */
		(function() {
			const REFRESH_INTERVAL = 50 * 60 * 1000; // 50 minutes in milliseconds

			function refreshCSRFToken() {
				fetch('refreshToken.php', {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Accept': 'application/json'
					}
				})
				.then(function(response) {
					if (!response.ok) {
						throw new Error('HTTP error ' + response.status);
					}
					return response.json();
				})
				.then(function(data) {
					if (data.status === 'success' && data.token) {
						window.csrfToken = data.token;
						console.log('CSRF token refreshed successfully at ' + new Date().toLocaleTimeString());
					} else {
						console.warn('CSRF token refresh returned unexpected data:', data);
					}
				})
				.catch(function(error) {
					console.warn('CSRF token refresh failed:', error.message);
					console.warn('Your current token will remain valid for up to 1 hour. Please reload the page if you encounter errors.');
				});
			}

			// Start the refresh interval (first refresh in 50 minutes)
			setInterval(refreshCSRFToken, REFRESH_INTERVAL);

			console.log('CSRF auto-refresh initialized. Token will refresh every 50 minutes.');
		})();
		</script>
	</head>
	<body>
		<div id="titleBar" class='redGradient' >
			<h1>VCC Resource Editing System</h1>
			<div id="searchParameters"></div>
		</div>
		
			<?php 
				include('searchBox.inc');		
			?>
		
		<div id="resourceList" class='redGradient'>

			<?php
				include("categories.inc");
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
		<div id="detailPane">
			<div id="detailInfo">
				<div id="detailScreenCategory"></div>
				<div id="detailScreenCount"></div>
			</div>
			<form class="data1" name="detailForm" id="detailForm" action="javascript:validate()" method="post">
				<div class="Detail Block1">
					<input id="ListOrderNumber" name="ListOrderNumber" value="0" type="hidden" />    
					<label class="label1">Name:</label><input class='Name' id='dName' name='Name' type="text" tabindex="1" maxlength="50" value=''/>
					<input id="dName2" name="Name2" type="text" tabindex="2"  maxlength="50"/>
					<label class="label1">Address:</label><input id="dAddress1" name="Address1" type="text" tabindex="3" maxlength="70" value=''/>
					<input id="dAddress2" name="Address2" type="text" tabindex="4"  maxlength="70" value = ""/>	
					<input id="dCity" name="City" type="text" tabindex="6"  maxlength="25" />
					<span class="inlineItems">
						<input class="inline" id="dState"  name="State" type="text" tabindex="7"  maxlength="2" />	
						<input class="inline" id="dZip" name="Zip" type="text" tabindex="5"  maxlength="10" />
					</span><br />
					<label class="label1">Country:</label>
						<div>
							<select name="countries" id="dCountry">
								<option value=""></option>
							</select>
							<input type="button" id="addNewCountry" value="Add Country" /></div>
					<label class="label1">Contact:</label><input id="dContact" name="Contact" type="text" tabindex="8.5"  maxlength="100" />					
				</div>
				<div class="Detail Block2">
					<label class="label1">Phone:</label>
						<input name="Phone" id="dPhone" type="text" tabindex="9" maxlength="12" />
					<span class="inlineItems">
						<label class="label1">Ext.:</label><input class="inline" id="dExt" name="Ext" type="text" tabindex="10" maxlength="12" />
					</span><br />
					<label class="label1">Toll Free:</label>
						<input id="dHotline" name="Hotline" type="text" tabindex="11" maxlength="12" />
					<label class="label1">Fax:</label>
						<input id="dFax" name="Fax" type="text" tabindex="12" maxlength="12" /><br />
					<label id="websiteLabel" class="label1">Website:</label>
						<input id="dWwweb" name="Wwweb" type="text" tabindex="13"  maxlength="100" />
					<label id="website2Label" class="label1">Website 2:</label>
					<input id="dWwweb2" name="Wwweb2" type="text" tabindex="14"  maxlength="100" />
					<label id="website3Label" class="label1">Website 3:</label>
						<input id="dWwweb3" name="Wwweb3" type="text" tabindex="15"  maxlength="100" />
					<label id="emailLabel" class="label1">Email:</label>
						<input id="dInternet" name="Internet" type="text" tabindex="16"  maxlength="50" />
					<label class="label1">Web Mail:</label>
						<input id="dMailpage" name="Mailpage" type="text" tabindex="17"  maxlength="110" />
				</div>
				<div class="Detail checkboxes">				
					<label class="label1 small">Hide Email:</label><input class="small" id="dShowmail"  name="showmail" type="checkbox" tabindex="18" value="Y" />
					<label class="label2 small">National:</label><input  class="small" id="dCnational"  name="cNational" type="checkbox" tabindex="19" />
					<label class="label3 small">Closed:</label><input class="small" id="dClosed" name="Closed" type="checkbox" tabindex="20" value="Y" /><br />
					<label class="label1 small">Hide Address:</label><input  class="small" id="dGive_addr" name="HideAddress" type="checkbox" tabindex="21" value="Y" />
					<label class="label2 small">Local:</label><input class="small" id="dLocal" name="Local" type="checkbox" tabindex="22" value="Y" 	/>
					<label class="label3 small">Publish:</label><input class="small" id="dWebSite" name="WebSite" type="checkbox" tabindex="23" value="Y" checked /><br />
					<label class="label1 small">Non-LGBT:</label><input class="small" id="dNonlgbt" name="nonlgbt" type="checkbox" tabindex="24" value="Y" />
					<input class="small" type="checkbox" style="visibility: hidden;" />
					<label class="label3 small" style="margin-left: 7px;">Youth Block:</label><input class="small" id="dYouthblock" name="youthblock" type="checkbox" tabindex="25" value="Y" />
				</div>
	
				<div id='block3detailPane' class="Detail Block3">
					<span id="lStreetview">Show Map View</span>
					<span id='dIdNumLabel'>Id No:</span><input type='text' class="IdNum" id="dIdnum" name="IdNum" readonly='true'>
					<span id='dEdateLabel'>Last Edit:</span><input type='text' class="edate" id="dEdate" name="eDate" readonly='true'><br />
					<label class="label1">Description: </label><label class='label4 small'>Mark as Updated:</label><input type="checkbox" name="updateEdate" id="updateEdate" value="Y" checked /><br />
					<textarea class="ddescription" id="dDescript" name="Descript" tabindex="21" ></textarea>
					<label class="label1">Notes: </label><br /><textarea class="dnotes" id="dNote" name="Note" tabindex="22"></textarea>
					<label class="label1">Categories:</label><br />
					<select id="dType1" name="Type1SelectMenu" tabindex="23">
					</select>
					<select id="dType2" class="nextCategory" name="Type2SelectMenu" tabindex="24">
					</select>
					<select id="dType3" class="nextCategory" name="Type3SelectMenu" tabindex="25">
					</select>
					<select id="dType4" class="nextCategory" name="Type4SelectMenu" tabindex="26">
					</select><br />
					<select id="dType5" class="nextCategory" name="Type5SelectMenu" tabindex="27">
					</select>
					<select id="dType6" class="nextCategory" name="Type6SelectMenu" tabindex="28">
					</select>
					<select id="dType7" class="nextCategory" name="Type7SelectMenu" tabindex="29">
					</select>
					<select id="dType8" class="nextCategory" name="Type8SelectMenu" tabindex="30">
					</select>
				</div>
				<div id="StreetviewPane" class="Block3 BottomPane" >
					<span id="ldetailview">Show Detail View</span>
					<span id="lGeoInfo" title="View geolocation details">Geo Info</span>
					<div id="dStreetview"></div>
					<div id="geoAccuracyOverlay"></div>
				</div>
			</form>
			</div>
		<div id="resourceDetailControl" class=''>
			<input type="button" id="resourceDetailBulkEmailButton" class='smallButton' value="Bulk Email" />
			<input type="button" id="resourceDetailFirstButton" class='smallButton' value="<<-First" />
			<input type="button" id="resourceDetailPreviousButton" class='smallButton' value="<-Previous" />
			<input type="button" id="resourceDetailNextButton" class='smallButton' value="Next->" />
			<input type="button" id="resourceShowListButton" class="smallButton" value="LIST" />
			<input type="button" id="duplicateCurrentRecordButton" value="COPY" class='smallButton' >
			<input type="button" id="addNewRecordButton" value="New Record" class='smallButton' >
			<input type="button" id="NewSearch" value="New Search" class='smallButton' ">
			<input type="button" id="deleteButton" value="DELETE" class='smallButton' >
			<input type="button" id="CancelNewRecordButton" value="Cancel" class='smallButton'>
			<input type="button" id="saveRecord" class="BrightButtons" value="SAVE">
		</div>

		<!-- Geolocation Info Modal -->
		<div id="geoInfoModal" class="geo-modal-overlay">
			<div class="geo-modal-content">
				<div class="geo-modal-header">
					<span class="geo-modal-icon">&#x1F4CD;</span>
					<span class="geo-modal-title">Geolocation Information</span>
					<span class="geo-modal-close" id="geoModalClose">&times;</span>
				</div>
				<div class="geo-modal-body" id="geoModalBody">
					<!-- Content populated by JavaScript -->
				</div>
			</div>
		</div>
	</body>
</html>
