<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

if (@$_SESSION["auth"] != "yes") {
	die("Unauthorized");
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



$UserID = $_SESSION["UserID"];
$_SESSION["SortOrder"] = 'Date';
$token = null;

// Release session lock after reading/writing all session data
session_write_close();

?>


<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
  <head>
	<meta http-equiv="content-type" content="text/html;charset=utf-8" />
    <title>LGBT NHC Volunteer Comm. Center Resource Administration</title>
    <link type="text/css" rel="stylesheet" href="resourceAdmin.css?v=<?php echo time(); ?>" /> 
    <script src="resourceAdmin.js?v=<?php echo time(); ?>" type="text/javascript"></script>
    <script src="../LibraryScripts/Ajax.js" type="text/javascript"></script>
    <script src="../sha1.js" type="text/javascript"></script>
  </head>
  <body>  
  	<div id="Title">
  		VOLUNTEER COMMUNICATION CENTER RESOURCE ADMINISTRATION 
	</div>
	<div id="WorkPane">
			<div id="ResourceSearch" class="PaneSelected">
				<iframe src="Search/" width="100%" height="100%">Is this Working?</iframe>
			</div>
			<div id="Monitor">
				<div id='volunteerList'>
					<table id='volunteerListTable'>
						<tr id="userListHeader"><th>Name</th><th>Shift</th><th>Call</th><th>Chat</th><th>One Chat</th><th>Logoff</th></tr>
					</table>
				</div>
			</div>
		</div>

		<div id="Exit">
			<input type='button' id="ExitButton" value = "EXIT">
		</div>
	</div>
	<?php
		echo "<input type='hidden' id='token' value = '".$token."'>";
		echo "<input type='hidden' id='AdministratorID' value='".$UserID."' />";
		echo "<audio id='IMSound' volume=0.5 src='../Audio/Gabe_IM.mp3' autobuffer='true'></audio>" ;
		echo "<input type='hidden' id='volunteerID' value='".$UserID."' />";
	?>
</body>
</html>
