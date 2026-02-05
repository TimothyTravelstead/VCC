<?php
// Include db_login.php FIRST (sets session configuration)
require_once '../../../private_html/db_login.php';

session_start();

// Check authentication
if ($_SESSION['auth'] != 'yes') {
    die("Unauthorized");
}

// Check if user is admin
$isAdmin = (isset($_SESSION['AdminUser']) && $_SESSION['AdminUser'] === 'true');

// Read session variables
$volunteerID = $_SESSION['UserID'] ?? '';
$fullName = $_SESSION['FullName'] ?? '';
$message = $_SESSION["message"] ?? '';
$type = $_SESSION['userType'] ?? null;

// Release session lock
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

include 'historicalCalendarUpdate.php';

$now = new DateTime();
$mins = $now->getOffset() / 60;
$mins = abs($mins);

?>
<html lang="en">
	<head>
		<meta http-equiv='Pragma' content='no-cache'>
		<meta http-equiv='Expires' content='Mon, 22 Jul 2002 11:12:01 GMT'>
		<meta NAME='ROBOTS' CONTENT='NONE'>    
		<meta NAME='GOOGLEBOT' CONTENT='NOARCHIVE'>
		<title>GLBT National Help Center Administrator Calendar</title>
	    <link type="text/css" rel="stylesheet" href="adminCalendar.css?t=<%= DateTime.Now.Ticks %>" media="screen" ">
	    <link type="text/css" rel="stylesheet" href="Editor/css/widgEditor.css">
		<script src="adminCalendar.js" type="text/javascript"></script>
		<script src="Editor/scripts/widgEditor.js" type="text/javascript"></script>
		<script src="jstz-1.0.4.min.js" type="text/javascript"></script>
    <script src="../../LibraryScripts/Ajax.js" type="text/javascript"></script>
    <script src="../../LibraryScripts/Dates.js" type="text/javascript"></script>
    <script src="../../LibraryScripts/domAndString.js" type="text/javascript"></script>
	</head>
	<body>
		<div id='calendarTopArea'>
			<div id='screenTitle'>
				<h1 id='moduleTitle'>Administrative Calendar</h1>
				<p id='timeZone'></p>
			</div>
			<div id='shiftColorLegend'>
				<table id='colorLegend'>
					<tr>
						<td id='officeBlockLegend' class='userInOffice userBlock'>XX</td>
						<td id='officeBlockLegendText' >Office Shift</td>
						<td class='tableGap display'> </td>
						<td class='userBuddyShift userBlock'>XX</td><td>Buddy Shift</td>
						<td class='tableGap display'> </td>
						<td id='endingBlockLegend' class='userInOffce endingUserBlock userBlock'>XX</td><td id='endingBlockLegendText'>Ending Shift</td>
					</tr>
					<tr>
						<td id='remoteBlockLegend' class='userRemote userBlock'>XX</td>
						<td id='remoteBlockLegendText'>Remote Shift</td><td class='tableGap display'> </td>
						<td id='resourceOnlyLegend' class='userResourceOnly userBlock'>XX</td>
						<td id='resourceOnlyLegendText' >Resource Only Shift</td>
						<td id='volunteerShiftLegend' class='userResourceOnly userBlock'>XX</td>
						<td id='volunteerShiftLegendText' >Volunteer Shift</td>
						<td class='tableGap display'> </td>
						<td id='futureBlockLegend' class='userRemote futureUserBlockLegend userBlock'>XX</td><td id='futureBlockLegendText'>Future Shift</td>
					</tr>
					<tr>
						<td id='tableSpacer'> </td>
						<td id='volunteerCurrentUserLegend' class='selectedUserBlock userBlock'>XX</td>
						<td id='volunteerCurrentUserLegendKey'></td>
					</tr>
				</table>
				<table id='blockColorLegend'>
					<tr><td id='emptyShiftLegend' class='blockElement emptyShift'>Empty Shift</td></tr><tr><td id='fullShiftLegend' class='blockElement fullShift' >Full Shift</td></tr>
				</table>
			</div>
			<form name='calendarModeForm' id='calendarModeForm' style="<?php echo !$isAdmin ? 'display: none;' : ''; ?>">
				<span><input type="radio" name="calendarMode" value="schedule" /> Master Schedule</span><br />
				<span><input type="radio" name="calendarMode" value="calendar" checked='checked' /> Monthly Calendar</span>
			</form>
			<div id='messageArea'>
				<?php
					echo $message;
				?>
			</div>

			<div id='calendarTitle'></div>
			<div id='priorMonthButton' type='button'><<</div> 
			<div id='nextMonthButton' type='button'>>></div> 
			<div id='userListBlock' style="<?php echo !$isAdmin ? 'display: none;' : ''; ?>">
					<select id='userList'></select><br />
					<form name = 'userTypesForm' id='userTypes'>
						<span id='volunteerStatus'><input id='volunteerStatusRadio' type="radio" name="userTypeRadioButton" value="Volunteer" /> Volunteer</span>
						<span id='resourceOnlyStatus'><input id='resourceOnlyStatusRadio' type="radio" name="userTypeRadioButton" value="ResourceOnly" /> Resources Only</span>
						<span id='trainerStatus'><input id='trainerStatusRadio' type="radio" name="userTypeRadioButton" value="Trainer" /> Trainer</span>
					</form>
					<form name = 'userLocationSelect' id='userLocationSelectTypes'>
						<span id='volunteerLocationOfficeSelect'><input id='volunteerOfficeRadio' type="radio" name="userLocationSelectRadio" value="SF" /> Office</span><br />
						<span id='volunteerLocationRemoteSelect'><input id='volunteerRemoteRadio' type="radio" name="userLocationSelectRadio" value="RM" /> Remote</span>
					</form>
					<form name = 'userShiftDatesForm' id='userShiftDatesForm'>
						<table>
							<tr><th>Start: </th><td><input id='shiftStartDate' type="date" name="shiftStartDate" value="" /></td></tr>
							<tr><th>End:  </th><td><input id='shiftEndDate' type="date" name="shiftEndDate" value=""  /></td></tr>
						</table>
					</form>
			</div>
		</div>
		<div id='calendarMonthHeader'>
			<div class='calendarDayHeader Sunday' id='dayHeader0'>Sunday</div>
			<div class='calendarDayHeader Monday' id='dayHeader1'>Monday</div>
			<div class='calendarDayHeader Tuesday' id='dayHeader2'>Tuesday</div>
			<div class='calendarDayHeader Wednesday' id='dayHeader3'>Wednesday</div>
			<div class='calendarDayHeader Thursday' id='dayHeader4'>Thursday</div>
			<div class='calendarDayHeader Friday' id='dayHeader5'>Friday</div>
			<div class='calendarDayHeader Saturday' id='dayHeader6'>Saturday</div>
		</div>
		<div id='calendar'> 
		</div>
		<div id='weeklyShifts'>
		</div>
		<form id='notesForm' name='notesForm' onsubmit='notesSubmit()' style="<?php echo !$isAdmin ? 'display: none;' : ''; ?>">
			<div id='notes'>
				<div class='notes' id='notes1' contenteditable="true">
					<?php
						if ($isAdmin) {
							echo file_get_contents("CalendarNotes1" , "r");
						}
					?>
				</div>
				<div class="notes" id='notes2' contenteditable="true">
					<?php
						if ($isAdmin) {
							echo file_get_contents("CalendarNotes2" , "r");
						}
					?>
				</div>
				<div class="notes" id='notes3' contenteditable="true">
					<?php
						if ($isAdmin) {
							echo file_get_contents("CalendarNotes3" , "r");
						}
					?>
				</div>
			</div>
			<input id='calendarNotesSaveButton' type='button' value='Save Notes' />
			<input id='openShiftsButton' type='button' value='Open Shifts Text' />
			<div id='openShiftsResults'></div>
		</form>
		<?php
			echo "<input type='hidden' id='volunteerID' value='".$volunteerID."'/>";
			echo "<input type='hidden' id='volunteerFullName' value='".$fullName."'/>";
			echo "<input type='hidden' id='volunteerType' value='".$type."'/>";
			echo "<input type='hidden' id='isAdmin' value='".($isAdmin ? 'true' : 'false')."'/>";
			echo "<input type='hidden' id='serverOffsetMinutes' value = '".$mins."'>";
		?>
		<div id='deleteConfirmDialog'>
			<h3>Delete Shift</h3><p>Entire Shift or a single half-hour block?</p>
			<input type='button' id='addFutureShift' value='Edit Dates' />
			<input type='button' id='deleteBlockButton' value='Single Block' />
			<input type='button' id='deleteShiftButton' value='Full Shift' />
		</div>
		<div id="ExitButton" class="redGradient">
			<span>EXIT PROGRAM</span>
		</div>

	</body>
</html>
