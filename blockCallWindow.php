<?php
require_once('../private_html/db_login.php');
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

$PhoneNumber = $_REQUEST['PhoneNumber'] ?? null;
$Location = $_REQUEST['Location'] ?? null;
$VolunteerID = $_SESSION['UserID'] ?? null;

// Release session lock after reading session data
session_write_close();

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" 
   "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
  <head>
	<meta HTTP-EQUIV="CACHE-CONTROL" CONTENT="NO-CACHE">
	<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="Mon, 22 Jul 2002 11:12:01 GMT" />
	<meta NAME="ROBOTS" CONTENT="NONE">    
	<meta NAME="GOOGLEBOT" CONTENT="NOARCHIVE">
	<style type="text/css">
		body {
			background-color:		#FF9900;
			line-height:			120%;
			font-size:				130%;
		}

		textarea {
			margin-left:			10px;	
			height:					120px;
			width:					350px;
			line-height:			100%;
			overflow:				auto;
			padding:				2px;
			font-weight:			normal;
			font-size:				100%;
		}

		input {
			margin-left:			265px;
			margin-top:				10px;
		}
		
		em {
			font-weight:			bold;
			text-decoration:		underline;
		}
		
		#Instructions {
			margin-left:			10px;	
			height:					80px;
			width:					450px;
			line-height:			100%;
			overflow:				auto;
			padding:				2px;
			font-weight:			bold;
		}
		
	</style>
	<script>
		window.onkeypress = onEnter;
		
		function onEnter( e ) {
			if (!e) var e = window.event
			if (e.keyCode) code = e.keyCode;
			else if (e.which) code = e.which;
		    if(code == 13) {
		        document.BlockCallForm.submit();
    		}
		}
	</script>

    <title>
   		<?php
	  		echo "Block Caller";
		  ?>
	</title>
  	</head>
  	<body>
  	<div id="Instructions"><p>Why do you want to block the caller?</p>
  	</div> 	
	<div id="IMText">
		<form name="BlockCallForm" action="BlockCallSubmit.php" method="POST">
		<?php
			echo "<input type='hidden' name='PhoneNumber' value='".$PhoneNumber."'></input>";
			echo "<input type='hidden' name='VolunteerID' value='".$VolunteerID."'></input>";
			echo "<input type='hidden' name='Type' value='User'></input>";
		?>
			<textarea name="Message" cols="100" rows="10"></textarea>
			<input type="submit" name="mysubmit" value="Submit Request" />
		</form>
	</div>
	<script>
		document.BlockCallForm.Message.focus();
	</script>
</body>
</html>
