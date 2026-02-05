<?php
// Include db_login.php FIRST (sets session configuration)
require_once '../../private_html/db_login.php';

session_start();

// Enhanced cache control headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); // HTTP/1.1
header("Pragma: no-cache"); // HTTP/1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header("Vary: *"); // Indicate the response is varied based on the request

if ($_SESSION['auth'] != 'yes') {
	die("Unauthorized");
} 

// Set Timezone for Date Information
	define('TIMEZONE', 'America/Los_Angeles');
	date_default_timezone_set(TIMEZONE);
	$now = new DateTime();
	$mins = $now->getOffset() / 60;
	$mins = abs($mins);

$volunteerID = $_SESSION['UserID'] ?? '';
$fullName = $_SESSION['FullName'] ?? '';
$type = $_SESSION['userType'] ?? '';
$message = $_SESSION['message'] ?? '';
session_write_close();

// Set Calendar Only status (LoggedOn=10) if user isn't logged in elsewhere
// This tracks that the user is active on the calendar without taking calls/chats
if ($volunteerID) {
    $query = "SELECT LoggedOn FROM Volunteers WHERE UserName = ?";
    $result = dataQuery($query, [$volunteerID]);
    if ($result && count($result) > 0) {
        $currentLoggedOn = $result[0]->LoggedOn;
        // Only set to Calendar Only (10) if currently logged out (0)
        if ($currentLoggedOn == 0) {
            $updateQuery = "UPDATE Volunteers SET LoggedOn = 10 WHERE UserName = ?";
            dataQuery($updateQuery, [$volunteerID]);
        }
    }
}

?>
<!DOCTYPE html>
	<head>
		<meta http-equiv='Pragma' content='no-cache'>
		<meta http-equiv='Expires' content='Mon, 22 Jul 2002 11:12:01 GMT'>
		<meta NAME='ROBOTS' CONTENT='NONE'>    
		<meta NAME='GOOGLEBOT' CONTENT='NOARCHIVE'>
		<title>GLBT National Help Center Calendar</title>
	    <link type="text/css" rel="stylesheet" href="calendar.css">
		<script src="../LibraryScripts/ErrorModal.js" type="text/javascript"></script>
		<script src="calendar.js" type="text/javascript"></script>
	</head>
	<body>
		<div>
			<?php
				echo "<input id='volunteerID' type='hidden' value='".$volunteerID."'>";
				echo "<input id='fullName' type='hidden' value='".$fullName."'>";
				echo "<input id='userType' type='hidden' value='".$type."'>";
			?>

			<input type='hidden' id='calendarStartDate'>
			<input type='button' id='newCalendarButton' value='Submit'><br />

			
			<input type='button' id='exitCalendarPage' value = 'Log Off'>
		</div>
		<div id='messageArea'><?php echo $message;?></div>
		<div id='monthTitleArea'>
			<input type='button' id='previousMonthButton' value='<< Previous'>
			<h1 id='calendarTitle'></h1>
			<input type='button' id='nextMonthButton' value='Next >>'>
		</div>
		<div class='calendarHeader' id='calendarMonthHeader'>
			<div class='calendarDay calendarDayHeader' id='dayHeader0'>Sunday</div>
			<div class='calendarDay calendarDayHeader' id='dayHeader1'>Monday</div>
			<div class='calendarDay calendarDayHeader' id='dayHeader2'>Tuesday</div>
			<div class='calendarDay calendarDayHeader' id='dayHeader3'>Wednesday</div>
			<div class='calendarDay calendarDayHeader' id='dayHeader4'>Thursday</div>
			<div class='calendarDay calendarDayHeader' id='dayHeader5'>Friday</div>
			<div class='calendarDay calendarDayHeader' id='dayHeader6'>Saturday</div>
		</div>
		<div id='calendar'></div>
	</body>
</html>
