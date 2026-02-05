<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours
require_once '../../private_html/db_login.php';

// Now start the session with the correct configuration
session_start();

if ($_SESSION['auth'] != 'yes') {
	die("Unauthorized");
}

// Set Timezone for Date Information
	define('TIMEZONE', 'America/Los_Angeles');
	date_default_timezone_set(TIMEZONE);
	$now = new DateTime();
	$mins = $now->getOffset() / 60;
	$mins = abs($mins);

$volunteerID = $_SESSION['UserID'] ?? null;;
$fullName = $_SESSION['FullName'] ?? null;;
$type = $SESSION['userType'] ?? null;;

// Release session lock after reading all session data
session_write_close();
$date = $_REQUEST['date'] ?? null;;
$pixelsPerMinute = $_REQUEST['pixelsPerMinute'] ?? null;;

if(!$date) {
	$date = date("Y-m-d");
}

if(!$pixelsPerMinute) {
	$pixelsPerMinute = 6;
}

$phpdate = strtotime( $date );
$dayOfWeek = date('w',$phpdate);
$dayOfWeekName = array('Sunday' , 'Monday' , 'Tuesday', 'Wednesday', 'Thursday' , 'Friday', 'Saturday');

?>
<html>
	<head>
		<meta http-equiv='Pragma' content='no-cache'>
		<meta http-equiv='Expires' content='Mon, 22 Jul 2002 11:12:01 GMT'>
		<meta NAME='ROBOTS' CONTENT='NONE'>    
		<meta NAME='GOOGLEBOT' CONTENT='NOARCHIVE'>
		<title>GLBT National Help Center Timeline Report</title>
	    <link type="text/css" rel="stylesheet" href="timeline.css">
		<script src="../LibraryScripts/ErrorModal.js" type="text/javascript"></script>
		<script src="timeline.js" type="text/javascript"></script>
		<script src="../LibraryScripts/Ajax.js" type="text/javascript"></script>
		<script src="../LibraryScripts/Dates.js" type="text/javascript"></script>
		<script src="../LibraryScripts/domAndString.js" type="text/javascript"></script>
	</head>
	<body>
		<h1> <?php echo date("F j, Y" , $phpdate); echo "<br>"; echo $dayOfWeekName[$dayOfWeek];?></h1>
		<p class='subTitle'>
			<input class='noPrint' id='priorDay' type='button' value='Previous Day' />  <input class='noPrint' id='nextDay' type='button' value='Next Day' /><br>
			<input class='noPrint' id='printButton' type='button' value='Print' />

		</p>
		<div class='subTitle noprint'>SCROLL TO SEE ENTIRE DAY</div><br>
		<input id='timeLineDate' type='hidden' value='<?php echo $date; ?>' />
<?php	echo "<input type='hidden' id='serverOffsetMinutes' value = '".$mins."'>"; 
?>
	<div>
		<table id='legend'>
			<tr><th colspan='5' class='legendHeader'>LEGEND</th><th></th><th></th><th></th><th></th></tr>
			<tr><td><div class='unansweredCall'></div></td><td>Unanswered Call</td><td></td>
			<tr><td><div class='repeatUnansweredCall'></div></td><td>Repeat Unanswered</td><td></td>
			<td class='chat'></td><td>Single Chat</td></tr>
			<tr><td class='answeredCall'></td><td>Answered Call</td><td></td>
			<td class='doubleChat'></td><td>Two Chats</td><td></td></tr>			
			<tr><td><div class='hangUp'></div></td><td>Hang Up</td><td></td><td></td><td></td></tr>

		</table>
		<table id='volunteers'>
			<thead>
				<tr><th class="volunteerName">Volunteer</th><th class="volunteerTime">Start</th><th class="volunteerTime">End</th><th class="volunteerTime">Chat Only</tr>
			</thead>
			<tbody id='volunteerTable'>
			</tbody>
		</table>
	</div>
	<div id='timelineGraph'></div>
<?php
	echo "<input type='hidden' id='pixelsPerMinute' value='".$pixelsPerMinute."' />";
	echo "<input type='hidden' id='displayedDate' value='".$date."' />";
?>
	</body>
</html>
