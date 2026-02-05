<?php
require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$UserID = $_REQUEST['UserID'];
$Name = $_REQUEST['Name'];
$Type = $_REQUEST['Type'];
$Message =	$_REQUEST["Message"];
$Sender = $_REQUEST['Sender'];

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
			height:					80px;
			width:					268px;
			line-height:			100%;
			overflow:				auto;
			padding:				2px;
			width:					280px;
			font-weight:			bold;
			font-size:				100%;
		}

		input {
			margin-top:				10px;
			margin-right:			10px;
			float:					right;
		}
		
		em {
			font-weight:			bold;
			text-decoration:		underline;
		}
		
		#IncomingMessage {
			top:					0px;
			margin-left:			10px;	
			height:					80px;
			width:					268px;
			line-height:			100%;
			overflow:				auto;
			padding:				2px;
			width:					280px;
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
		    	document.IMForm.FinalMessage.value = document.IMForm.Message.value;
		        document.IMForm.submit();
    		}
		}
	</script>

    <title>
   		<?php
	  		if ($Type == "Send") {
		  		echo "Msg. To: ".$Name;
		  	} else {
		  		echo "Msg. From: ".$Name;
		  	}
		  ?>
	</title>
  	</head>
  	<body>
  	<div id="VolunteerName">
	  	<?php
	  		if ($Type != "Send") {
		  		echo "<p id='IncomingMessage'>".stripslashes($Message)."</p>";
		  	}
		?>
  	</div> 	
	<div id="IMText">
		<form name="IMForm" action="VolunteerSubmitIM.php" method="POST">
		<?php
			echo "<input type='hidden' name='UserID' value='".$UserID."'></input>";
			echo "<input type='hidden' name='Sender' value='".$Sender."'></input>";
			echo "<input type='hidden' name='FinalMessage' value='".$FinalMessage."'></input>";
		?>
			<textarea name="Message" cols="22" rows="5"></textarea>
		</form>
	</div>
	<script>
		document.IMForm.Message.focus();
	</script>
</body>
</html>