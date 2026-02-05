<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../private_html/db_login.php');
include('../../private_html/csrf_protection.php');

// Now start the session with the correct configuration
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, private");
header("Pragma: no-cache");
header("Expires: 0");

if (@$_SESSION["auth"] != "yes") {
    die("Unauthorized");
}
include('../chat/firstChatAvailableLevel.php');
include('../chat/secondChatAvailableLevel.php');

$UserID = $_SESSION["UserID"];
$_SESSION["SortOrder"] = 'Date';
$_SESSION["groupChatModerator"] = 2;

// Check database for AdminMini status instead of relying on session variables
// Admin Mini users have LoggedOn=7 when they log in with Admin type 7
// Full Admin users have LoggedOn=2 when they log in with Admin type 3
$adminMiniQuery = "SELECT LoggedOn FROM volunteers WHERE UserName = ?";
$adminMiniResult = dataQuery($adminMiniQuery, [$UserID]);
$AdminMiniUser = (!empty($adminMiniResult) && $adminMiniResult[0]->LoggedOn == 7) ? 1 : null;

// DEBUG: Log complete session data when Admin/index.php loads
try {
    $sessionDebug = "\n" . date('Y-m-d H:i:s') . " - ===== ADMIN/INDEX.PHP SESSION DATA =====\n";
    $sessionDebug .= "Session ID: " . session_id() . "\n";
    $sessionDebug .= "Complete SESSION array:\n";
    $sessionDebug .= print_r($_SESSION, true);
    $sessionDebug .= "================================\n";
    file_put_contents('../session_debug.txt', $sessionDebug, FILE_APPEND | LOCK_EX);
} catch (Exception $e) {
    // Ignore debug logging errors
}

// Release session lock after reading/writing all session data
session_write_close();

// First main query refactored to use dataQuery
$query = "SELECT UserID, firstname, lastname, shift, Volunteers.office, Volunteers.desk,
          oncall, Active1, Active2
          FROM Volunteers
          WHERE LoggedOn = 1";
$result = dataQuery($query);

$UserIDNum = array();
$FirstName = array();
$LastName = array();
$Shift = array();
$Office = array();
$Desk = array();
$OnCall = array();
$Chat1 = array();
$Chat2 = array();
$Chat = array();
$Count = 0;

if ($result) {
    foreach ($result as $row) {
        $UserIDNum[$Count] = $row->UserID;
        $FirstName[$Count] = $row->firstname;
        $LastName[$Count] = $row->lastname;
        $Shift[$Count] = $row->shift;
        $Office[$Count] = $row->office;
        $Desk[$Count] = $row->desk;
        $OnCall[$Count] = $row->oncall;
        $Chat1[$Count] = $row->Active1;
        $Chat2[$Count] = $row->Active2;

        if ($Shift[$Count] === null) {
            $Shift[$Count] = 0;
        }

        switch($Shift[$Count]) {
            case 0: $Shift[$Count] = "Closed"; break;
            case 1: $Shift[$Count] = "NY 1st"; break;
            case 2: $Shift[$Count] = "NY 2nd"; break;
            case 3: $Shift[$Count] = "SF 1st"; break;
            case 4: $Shift[$Count] = "SF 2nd"; break;
        }

        if ($Desk[$Count] == 0) {
            $Desk[$Count] = "NY";
        }

        if ($OnCall[$Count] == 1) {
            $OnCall[$Count] = "<span class='bold'>YES</span>";
        } else {
            $OnCall[$Count] = "-";
        }

        if ($Chat1[$Count] !== null) {
            $Chat[$Count] = "<span class='bold'>1</span>";
        } else if ($Chat2[$Count] !== null) {
            $Chat[$Count] = "<span class='bold'>1</span>";
        } else {
            $Chat[$Count] = "-";
        }

        if ($Chat1[$Count] !== null && $Chat2[$Count] !== null) {
            $Chat[$Count] = "<span class='bold'>2</span>";
        }

        $Count++;
    }
}

$TotalCount = $Count;

// Get Admin Blocked Caller List
$BlockedCallers = array();
$Count = 0;

$query2 = "SELECT CONCAT('(',SUBSTRING(PhoneNumber,3,3),') ',SUBSTRING(PhoneNumber,6,3), '-' , 
           SUBSTRING(PhoneNumber,9,4)) as formatted_number 
           FROM BlockList 
           WHERE Type = 'Admin' 
           ORDER BY length(PhoneNumber), SUBSTRING(PhoneNumber,3,3),
           SUBSTRING(PhoneNumber,6,3),SUBSTRING(PhoneNumber,9,4)";
$result2 = dataQuery($query2);

if ($result2) {
    foreach ($result2 as $row) {
        $BlockedCallers[$Count] = $row->formatted_number;
        $Count++;
    }
}

if ($AdminMiniUser != 1) {
    // Get user Blocked Caller List
    $UserBlockedCallers = array();
    $UserBlockedCallersDate = array();
    $UserBlockedCallersMessage = array();
    $UserBlockedCallersUser = array();
    $UserBlockedCallersInternetNumber = array();
    $Count = 0;

    $query2 = "SELECT 
               CONCAT('(',SUBSTRING(t1.PhoneNumber,3,3),') ',SUBSTRING(t1.PhoneNumber,6,3), '-' , 
               SUBSTRING(t1.PhoneNumber,9,4)) as Number, 
               DATE(t1.Date) as Date, 
               CONCAT(t2.FirstName, ' ' , t2.LastName) as User, 
               t1.Message, 
               InternetNumber 
               FROM BlockList as t1, volunteers as t2 
               WHERE t1.Type = 'User' and t1.UserName = t2.UserName 
               ORDER BY t1.Date DESC, length(t1.PhoneNumber), 
               SUBSTRING(PhoneNumber,3,3),SUBSTRING(t1.PhoneNumber,6,3),
               SUBSTRING(t1.PhoneNumber,9,4)";
    $result2 = dataQuery($query2);

    if ($result2) {
        foreach ($result2 as $row) {
            $UserBlockedCallers[$Count] = $row->Number;
            $UserBlockedCallersDate[$Count] = $row->Date;
            $UserBlockedCallersUser[$Count] = $row->User;
            $UserBlockedCallersMessage[$Count] = $row->Message;
            $UserBlockedCallersInternetNumber[$Count] = $row->InternetNumber;
            $Count++;
        }
    }
}

// Info Center List code remains the same as it's file system related, not database
$InfoCenterArray = Array();
$InfoCenterButtons = "";

if ($handle = opendir('../InfoCenter/')) {
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
?>

<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
  <head>
    <meta http-equiv="content-type" content="text/html;charset=utf-8" />
    <title>LGBT NHC Volunteer Comm. Center Administration</title>
    <link type="text/css" rel="stylesheet" href="index.css?v=<?php echo time(); ?>" /> 
    <script src="index.js" type="text/javascript"></script>
    <script src="../LibraryScripts/Ajax.js" type="text/javascript"></script>
    <script src="../sha1.js" type="text/javascript"></script>
    <?php echo getCSRFJavaScript(); ?>
  </head>
  <body>  
    <div id="Title">
        VOLUNTEER COMMUNICATION CENTER ADMINISTRATION<?php echo "--".$_SESSION["SortOrder"]; ?> 
    </div>
    <div id="WorkPane">
        <div id="WorkTabs">
            <span id="Tab1" class="NotSelected">Users</span>
            <span id="Tab2" class="NotSelected">Data</span>
            <span id="Tab3" class="NotSelected">Info Center</span>
            <span id="Tab5" class="Selected">Resources</span>
<?php if ($AdminMiniUser != 1): ?>
            <span id="Tab6" class="NotSelected">Blocked</span>
<?php endif; ?>
            <span id="Tab7" class="NotSelected">Calendar</span>
            <span id="Tab8" class="NotSelected">Stats</span>
<?php if ($AdminMiniUser != 1): ?>
            <span id="Tab9" class="NotSelected">Group Chat</span>
            <span id="Tab10" class="NotSelected">Widget</span>
<?php endif; ?>
        </div>
        <div id="DataPane">
            <div id="CallLog" class="PaneSelected">
                <div class="dataPane-container">
                    <div class="dataPane-header">
                        <h2>Data Management Center</h2>
                    </div>
                    
                    <div class="dataPane-section">
                        <h3>Download Reports</h3>
                        <div class="date-range-container">
                            <div class="date-input-group">
                                <label for="CallLogStart">Start Date</label>
                                <input type="date" id="CallLogStart" value="2001-01-01">
                            </div>
                            <div class="date-input-group">
                                <label for="CallLogEnd">End Date</label>
                                <input type="date" id="CallLogEnd" value="2099-12-31">
                            </div>
                        </div>
                        
                        <div class="download-controls">
                            <select id="downloadTypeSelect" class="modern-select">
                                <option value="">Select Report Type</option>
                                <option value="CallLog">Call Log</option>
<?php if ($AdminMiniUser != 1): ?>
                                <option value="CallHistory">Caller History</option>
                                <option value="BlockedCallers">Blocked Callers Log</option>
<?php endif; ?>
                                <option value="VolunteerLog">Volunteer Log</option>
<?php if ($AdminMiniUser != 1): ?>
                                <option value="ChatHistory">Chat History</option>
                                <option value="Resources">Resources</option>
                                <option value="ResourceUpdate">Resources Update Data</option>
<?php endif; ?>
                            </select>
                            <button id="downloadButton" class="modern-button primary-button">Download</button>
                        </div>
                        
                        <!-- Hidden buttons for backward compatibility -->
                        <div style="display: none;">
                            <input type='button' id='CallLogButton' value='Download Call Log' />
<?php if ($AdminMiniUser != 1): ?>
                            <input type='button' id='CallHistoryButton' value='Download Caller History' />
                            <input type='button' id='BlockedCallersButton' value='Download Blocked Callers Log' />
<?php endif; ?>
                            <input type='button' id='VolunteerLogButton' value='Download Volunteer Log' />
<?php if ($AdminMiniUser != 1): ?>
                            <input type='button' id='DownloadChatHistoryButton' value='Download Chat History' />
                            <input type='button' id='DownloadResourcesButton' value='Download Resources' />
                            <input type='button' id='resourceUpdateHistoryButton' value='Resources Update Data' />
<?php endif; ?>
                        </div>
                    </div>
                    
<?php if ($AdminMiniUser != 1): ?>
                    <div class="dataPane-section">
                        <h3>File Uploads</h3>
                        
                        <div class="upload-section" id='pridePathUploadDiv'>
                            <h4>PridePath Spreadsheets</h4>
                            <form id="pridepath_upload_form1" method="post" enctype="multipart/form-data" action="pridePathUpload.php" onsubmit="uploadPridePathSpreadsheet(event, 'type1')" class="modern-form">
                                <div class="form-group">
                                    <label>State Law Spreadsheet</label>
                                    <p class="form-description">Select an Excel or CSV file to upload to the PridePath folder.</p>
                                    <div class="file-input-wrapper">
                                        <input type='file' id='pridePathType1' name='pridePathSpreadsheet' accept=".xlsx,.xls,.csv" class="modern-file-input" />
                                        <input type="hidden" name="spreadsheetType" value="type1" />
                                        <button type="submit" name="action" value="Upload State Law" class="modern-button secondary-button">Upload State Law</button>
                                    </div>
                                    <?php outputCSRFTokenField(); ?>
                                </div>
                            </form>
                            
                            <form id="pridepath_upload_form2" method="post" enctype="multipart/form-data" action="pridePathUpload.php" onsubmit="uploadPridePathSpreadsheet(event, 'type2')" class="modern-form">
                                <div class="form-group">
                                    <label>Local Law Spreadsheet</label>
                                    <p class="form-description">Select an Excel or CSV file to upload to the PridePath folder.</p>
                                    <div class="file-input-wrapper">
                                        <input type='file' id='pridePathType2' name='pridePathSpreadsheet' accept=".xlsx,.xls,.csv" class="modern-file-input" />
                                        <input type="hidden" name="spreadsheetType" value="type2" />
                                        <button type="submit" name="action" value="Upload Local Law" class="modern-button secondary-button">Upload Local Law</button>
                                    </div>
                                    <?php outputCSRFTokenField(); ?>
                                </div>
                            </form>
                        </div>
                        
                        <div class="upload-section" id='newVolunteerVideoFileDiv'>
                            <h4>Volunteer Training Video</h4>
                            <form id="file_upload_form" method="post" enctype="multipart/form-data" action="videoUpload.php" class="modern-form">
                                <div class="form-group">
                                    <p class="form-description">Upload a new volunteer training video file.</p>
                                    <div class="file-input-wrapper">
                                        <input type='file' id='newVolunteerVideo' name='newVolunteerVideo' class="modern-file-input" />
                                        <button type="submit" name="action" value="Upload" class="modern-button secondary-button">Upload Video</button>
                                    </div>
                                    <?php outputCSRFTokenField(); ?>
                                </div>
                            </form>
                        </div>
                    </div>
<?php endif; ?>
                </div>
            </div>
            <div id="CallLogFile">
            </div>
            <div id="Users">
                <p id="Columns" class="List"><span class='UserFirstName List'>NAME</span><span class='UserName List'>UserID</span><span class='UserOffice List'>Pronouns</span></p>
                <div id="CurrentUsers">
                <?php
                    // START OF REFACTORED PHP SECTION
                    echo "<input type='hidden' id='AdministratorID' value='".$UserID."' />";
                    echo "<input type='hidden' id='AdminMiniUser' value='".$AdminMiniUser."' />";

                    $query = "SELECT UserId, firstname, lastname, skypeID, UserName,
                             (SELECT max(EventTime) FROM Volunteerlog 
                              WHERE UserID = UserName AND LoggedOnStatus > 0) as LastLogOn,
                             Office 
                             FROM Volunteers 
                             ORDER BY lastname, firstname, office";

                    $result = dataQuery($query);

                    $UsersID = array();
                    $UsersFirstName = array();
                    $UsersLastName = array();
                    $UsersSkype = array();
                    $UsersOdd = array();
                    $UserName = array();
                    $LastLogOn = array();
                    $userLocation = array();
                    $LocationColor = array();

                    $Count = 0;
                    $RowCount = 0;

                    if ($result) {
                        foreach ($result as $row) {
                            $UsersID[$Count] = $row->UserId;
                            $UsersFirstName[$Count] = $row->firstname;
                            $UsersLastName[$Count] = $row->lastname;
                            $UsersSkype[$Count] = $row->skypeID;
                            $UserName[$Count] = $row->UserName;
                            $LastLogOn[$Count] = $row->LastLogOn;
                            $userLocation[$Count] = $row->Office;

                            if ($userLocation[$Count] == "SF") {
                                $LocationColor[$Count] = "userInOffice";
                            } else {
                                $LocationColor[$Count] = "userRemote";
                            }

                            if ($RowCount == 0) {
                                $UsersOdd[$Count] = "UserOdd";
                                $RowCount = 1;
                            } else {
                                $UsersOdd[$Count] = "UserEven";
                                $RowCount = 0;
                            }
                            $Count++;
                        }
                    }

                    $UserCount = $Count;
                    $Count = 0;

					while ($Count < $UserCount) {
						// Safely handle null by casting to string and providing proper flags
						$lastLogOnEscaped = htmlspecialchars(
							(string)$LastLogOn[$Count],
							ENT_QUOTES | ENT_SUBSTITUTE,
							'UTF-8'
						);
						$usersIDEscaped = htmlspecialchars(
							(string)$UsersID[$Count],
							ENT_QUOTES | ENT_SUBSTITUTE,
							'UTF-8'
						);
						$usersOddEscaped = htmlspecialchars(
							(string)$UsersOdd[$Count],
							ENT_QUOTES | ENT_SUBSTITUTE,
							'UTF-8'
						);
						$locationColorEscaped = htmlspecialchars(
							(string)$LocationColor[$Count],
							ENT_QUOTES | ENT_SUBSTITUTE,
							'UTF-8'
						);
						$firstNameEscaped = htmlspecialchars(
							(string)$UsersFirstName[$Count],
							ENT_QUOTES | ENT_SUBSTITUTE,
							'UTF-8'
						);
						$lastNameEscaped = htmlspecialchars(
							(string)$UsersLastName[$Count],
							ENT_QUOTES | ENT_SUBSTITUTE,
							'UTF-8'
						);
						$userNameEscaped = htmlspecialchars(
							(string)$UserName[$Count],
							ENT_QUOTES | ENT_SUBSTITUTE,
							'UTF-8'
						);
						$usersSkypeEscaped = htmlspecialchars(
							(string)$UsersSkype[$Count],
							ENT_QUOTES | ENT_SUBSTITUTE,
							'UTF-8'
						);
					
						echo "<span title='Last Logon: {$lastLogOnEscaped}' 
								  id='{$usersIDEscaped}' 
								  class='List {$usersOddEscaped} Record {$locationColorEscaped}'>
								<span class='UserFirstName List'>{$firstNameEscaped} {$lastNameEscaped}</span>
								<span class='UserName List'>{$userNameEscaped}</span>
								<span class='UserOffice List'>{$usersSkypeEscaped}</span>
							  </span>";
						
						$Count++;
					}
                    // END OF REFACTORED PHP SECTION
                ?>
                </div>
                <div id="UserDetail">
                    <strong>USER DETAIL</strong>
                    <p id="UserMessage"></p>
                    <form name="DetailUserForm" id="DetailUserForm" action="javascript:ValidateForm()" method="post">
                        <input type="hidden" id="DetailIdNum" />
                        <label>First Name: </label><input type='text' id="DetailFirstName" name="DetailFirstName" value="" /><br />
                        <label>Last Name: </label><input type='text' id="DetailLastName" name="DetailLastName" value="" /><br />
                        <label>Pronouns: </label><input type='text' id="DetailskypeID" name="DetailskypeID" value="" /><br />
                        <label>UserID: </label><input type='text' id="DetailUserID" name="DetailUserID" value="" /><br /><br />
                        <label>Location: </label>
                            <span><input id='userLocationOffice' type="radio" name="userLocation" value="Office" /> Office</span></br />
                            <span><input id='userLocationHome' type="radio" name="userLocation" value="Home" /> Remote</span><br /><br />
                        <label>Preferred Hotline: </label>
                            <span><input id='userPreferredHotlineYouth' type="radio" name="userPreferredHotline" value="Youth" /> Youth</span></br />
                            <span><input id='userPreferredHotlineSAGE' type="radio" name="userPreferredHotline" value="SENIOR" /> SENIOR</span><br />
                            <span><input id='userPreferredHotlineNone' type="radio" name="userPreferredHotline" value="None"/> None</span><br /><br />
                        <label>New Password: </label><input type='password' id="DetailPassword" name="DetailPassword" value="" /><br />
                        <label>Confirm Password: </label><input type='password' id="DetailConfirmPassword" name="DetailConfirmPassword" value="" /><br /><br />
                        <label>Caller Types:</label>
                            <span><select id='callerTypeSelectMenu'>
                                <option id='callerTypeBoth' value='0'>Both</option>
                                <option id='callerTypeChat' value='1'>Chat Only</option>
                                <option id='callerTypeCall' value='2'>Calls Only</option>
                            </select></span><br /><br />
                        <label>Types:</label>
                            <span><input type="checkbox" id='userTypesVolunteer' name="userTypesVolunteer" value="1">Volunteer<br>
                            <input type="checkbox" id='userTypesResourcesOnly' name="userTypesResourcesOnly" value="1">Resource Only<br>
                            <input type="checkbox" id='userTypesAdmin' name="userTypesAdmin" value="1">Administrator<br>
                            <input type="checkbox" id='userTypesAdminResources' name="userTypesAdminResources" value="1">Admin Mini<br>
                            <input type="checkbox" id='userTypesResourceAdmin' name="userTypesResourceAdmin" value="1">Resource Mini<br>
                            <input type="checkbox" id='userTypesTrainer' name="userTypesTrainer" value="1">Trainer<br>
                            <input type="checkbox" id='userTypesMonitor' name="userTypesMonitor" value="1">Monitor<br>
                            <input type="checkbox" id='userTypesTrainee' name="userTypesTrainee" value="1" title="Trainee Cannot Have Any Other Volunteer Types Checked.">Trainee<br>
                            <input type="checkbox" id='userTypesGroupChatModerator' name="userTypesGroupChatModerator" value="1" title="Moderates Group Chat Sessions.">Group Chat<br></span><br><br>
                        <input type="button" id="NewUser" value="New User" />
                        <input id="CancelButton" type="button" value="Cancel" />
                        <input id="SubmitButton" type="submit" value="Save" />
                        <?php outputCSRFTokenField(); ?>
                    </form>
                </div>
            </div>
            <div id="InfoCenter">
                <h1>Information Center</h1>
                <div id='infoCenterButtons'>
                    <?php echo $InfoCenterButtons; ?>
                </div>
                <div id="infoCenterText"></div>
<?php if ($AdminMiniUser != 1): ?>
                <input id='infoCenterDeleteCurrentItem' type='button' value='Delete Displayed Item' /><br>
                <div id='newInfoCenterFileDiv'>
                    <label>Upload New InfoCenter Item: </label><input type='file' id='newInfoCenterFile' value='Upload' />
                    <p>To upload a new InfoCenter item, create a file in Word with the page URL and save it as a text file, but with an .html extension.</br>
                    Refuse Word's suggestion to replace the extension with .txt.</br>
                    Click on the "Choose File" button and select that file.</br>
                    The filename must end in ".html" to display properly.</p>
                </div>
<?php endif; ?>
            </div>			
            <div id="ResourceSearch" class="PaneSelected">
<?php if ($AdminMiniUser != 1): ?>
                <iframe src="Search/index.php" width="100%" height="100%">Is this Working?</iframe>
<?php endif; ?>
<?php if ($AdminMiniUser == 1): ?>
                <iframe src="SearchMini.php" width="100%" height="100%">Is this Working?</iframe>
<?php endif; ?>
            </div>

<?php if ($AdminMiniUser != 1): ?>
            <div id="callBlocking">
                <div id="adminBlocked">
                </div>
                <div id="userBlocked">
                </div>
                <div id='addBlockedNumber'>
                    <span>Block New Number</span><br /><br />
                    <table>
                        <tr><th>Number:</th><td><input type='text' id="addBlockedNumberData" name="addBlockedNumberData" value="" /></td></tr>
                        <tr><th>Reason:</th><td><input type='text' id="addBlockedNumberReason" name="addBlockedNumberReason" value="" /></td></tr>
                    </table><br />
                    <input id='cancelBlockedNumberButton' type="button" value="Cancel" />
                    <input id='addBlockedNumberButton' type="button" value="Submit" />
                </div>
                <div id='updateUserBlockedList'>
                    <input id='updateUserBlockedListButton' type="button" value="Update User Blocked List" />
                </div>
                <div id='numberHistoryLookup'>
                    <span>Lookup Number History</span><br />
                    <input id='numberHistoryLookupNumber' type="text" />
                    <input id='numberHistoryLookupNumberButton' type="button" value="Lookup History" />
                </div>
                <div id='groupChatBlocked'>
                    <span>Group Chat Block List</span><br />
                </div>
            </div>
<?php endif; ?>

            <div id="Calendar">
                <iframe src="../Calendar/Admin/" width="100%" height="100%"></iframe>
            </div>
            <div id="Stats">
                <iframe id="statsFrame" name="statsFrame" src="../Stats/newStats.php?cb=" + new Date().getTime() width="100%" height="100%"></iframe>
            </div>	
<?php if ($AdminMiniUser != 1): ?>
            <div id="groupChat">
                <iframe id="groupChatFrame" src="../GroupChat/Admin/index.php" width="100%" height="100%"></iframe>
            </div>
            <div id="Widget">
                <iframe id="widgetFrame" src="https://lgbt-widget.vercel.app/admin?embedded=true&apiKey=admin_29653642509f280329f5600684846fa708ad63967a706440" width="100%" height="900" frameborder="0"></iframe>
            </div>
<?php endif; ?>
            <div id="Monitor">
                <div id='volunteerList'>
                    <table id='volunteerListTable'>
                        <tr id="userListHeader"><th>Name</th><th>Shift</th><th>Call</th><th>Chat</th><th>One Chat</th><th>Logoff</th></tr>
                    </table>
                </div>
            </div>
        </div>

<?php if ($AdminMiniUser != 1): ?>
        <div id="CallHistoryList">
            <h1>Call Monitor</h1>
            <table>
                <tr>
                    <th class='Date' title="Sort by Date">Date</th>
                    <th class='Time' title="Sort by Time">Time</th>
                    <th class='CallerID' title="Sort by Phone Number">Phone</th>
                    <th class='Location' title="Sort by State and then City">  Location</th>
                    <th class='Hotline' title="Sort by Hotline">Hotline</th>
                    <th class='Category' title="Sort by Type">Type</th>
                    <th class='Length' title="Sort by Call Length">Length</th>
                </tr>
            </table>
            <div id="callHistoryScrollList" class="scrollableTable">
            </div>
        </div>
<?php endif; ?>

        <div id="setChatAvailableLevels">
            <h3>Chat Availability Levels</h3>
            <form name="setChatAvailableLevelsForm" id="setChatAvailableLevelsForm" action="javascript:setChatAvailableLevels()" method="post">
                <label>One Volunteer Gets Chats: </label><input type='text' id="setChatAvailableLevelsFormLevel1" name="setChatAvailableLevelsFormLevel1" value="<?php echo $chatAvailableLevel1; ?>" /><br /><br />
                <label>Two Volunteers Get Chats: </label><input type='text' id="setChatAvailableLevelsFormLevel2" name="setChatAvailableLevelsFormLevel2" value="<?php echo $chatAvailableLevel2; ?>" /><br /><br />
                <input id="setChatAvailableLevelsSubmitButton" type="submit" value="Save" />
                <?php outputCSRFTokenField(); ?>
            </form>
        </div>
        <div id="Exit">
            <input type='button' id="ExitButton" value = "EXIT">
            <span id="IMAll">IM All</span>
        </div>
    </div>
    <?php
        echo "<input type='hidden' id='token' value = '".$token."'>";
        echo "<audio id='IMSound' volume=0.5 src='../Audio/Gabe_IM.mp3' autobuffer='true'></audio>" ;
    ?>
	<div id="generalModal" class="modal-overlay">
		<div class="modal-content">
			<div class="modal-header" id="modalHeader">
				<span class="modal-icon" id="modalIcon"></span>
				<span class="modal-title" id="modalTitle">Default Title</span>
				<span class="close-btn" id="modalClose">&times;</span>
			</div>
			<div class="modal-body" id="modalBody">
				Default message content.
			</div>
		</div>
	</div>

	<!-- Full Call Details Modal -->
	<div id="fullDetailsModal" class="modal-overlay full-details">
		<div class="modal-content full-details-content">
			<div class="modal-header info" id="fullDetailsHeader">
				<span class="modal-icon" id="fullDetailsIcon">i</span>
				<span class="modal-title" id="fullDetailsTitle">Full Call Details</span>
				<span class="close-btn" id="fullDetailsClose">&times;</span>
			</div>
			<div class="modal-body full-details-body" id="fullDetailsBody">
				Loading...
			</div>
		</div>
	</div>
  </body>
</html>
