<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours
require_once '../../private_html/db_login.php';

// Now start the session with the correct configuration
session_start();

// Authentication check
if ($_SESSION['auth'] != 'yes') {
    // die("Unauthorized");
}

// Release session lock after reading session data
session_write_close();

// Get and validate date parameter
$Date = $_REQUEST['date'] ?? date("Y-m-d");

// Initialize response arrays
$Hours = [];
$Users = [];
$CallData = [];
$Calls = [];

// Get hours data
function getHours($date) {
    // Extract just the date portion (YYYY-MM-DD) in case datetime string is passed
    $dateOnly = substr($date, 0, 10);
    $query = "SELECT * FROM Hours WHERE Hours.DayofWeek = DATE_FORMAT(?, '%w') + 1";
    $result = dataQuery($query, [$dateOnly]);

    if (!$result || empty($result)) {
        // No hours found for this day (e.g., helpline closed on Sundays)
        return [
            'dayOfWeek' => null,
            'shift' => null,
            'openTime' => null,
            'closeTime' => null
        ];
    }

    return [
        'dayOfWeek' => $result[0]->DayofWeek ?? null,
        'shift' => $result[0]->Shift ?? null,
        'openTime' => $result[0]->Start ?? null,
        'closeTime' => $result[0]->End ?? null
    ];
}

// Get users data
function getUsers($date) {
    $query = "SELECT 
        UserName,
        firstName,
        lastName,
        (SELECT MIN(EventTime) 
         FROM Volunteerlog 
         WHERE Volunteerlog.UserID = Volunteers.UserName 
         AND DATE(EventTime) = ? 
         AND (Volunteerlog.LoggedOnStatus IN (1, 4, 5))) AS LoggedOnTime,
        
        CASE 
            WHEN (SELECT MIN(EventTime) 
                  FROM Volunteerlog 
                  WHERE Volunteerlog.UserID = Volunteers.UserName 
                  AND DATE(EventTime) = ? 
                  AND LoggedOnStatus IN (1, 4, 5)) > 
                 (SELECT MAX(EventTime) 
                  FROM Volunteerlog 
                  WHERE Volunteerlog.UserID = Volunteers.UserName 
                  AND DATE(EventTime) = ? 
                  AND LoggedOnStatus = 0)
            THEN NULL
            ELSE (SELECT MAX(EventTime) 
                  FROM Volunteerlog 
                  WHERE Volunteerlog.UserID = Volunteers.UserName 
                  AND DATE(EventTime) = ? 
                  AND LoggedOnStatus = 0)
        END AS LoggedOffTime,
        
        (SELECT ChatOnly 
         FROM Volunteerlog 
         WHERE Volunteerlog.UserID = Volunteers.UserName 
         AND DATE(EventTime) = ? 
         AND LoggedOnStatus != 0 
         ORDER BY EventTime ASC 
         LIMIT 1) AS ChatOnly
         
    FROM Volunteers
    WHERE UserName IS NOT NULL 
    AND EXISTS (
        SELECT 1 
        FROM Volunteerlog 
        WHERE Volunteerlog.UserID = Volunteers.UserName 
        AND DATE(EventTime) = ? 
        AND LoggedOnStatus IN (1, 4, 5)
    )";
    
    $result = dataQuery($query, [$date, $date, $date, $date, $date, $date]);
    
    if (!$result) {
        return [];
    }
    
    $users = [];
    if ($result && is_array($result)) {
        foreach ($result as $row) {
            $users[$row->UserName] = [
                'UserID' => $row->UserName,
                'firstName' => $row->firstName,
                'lastName' => $row->lastName,
                'loggedOnTime' => $row->LoggedOnTime,
                'loggedOffTime' => $row->LoggedOffTime,
                'chatOnly' => $row->ChatOnly
            ];
        }
    }
    
    return $users;
}

// Get call history data
function getCallHistory($date) {
    $query = "SELECT 
        CallerID,
        (SELECT Volunteer
         FROM callrouting
         WHERE callrouting.CallSid = CallerHistory.CALLSID
         LIMIT 1) as Volunteer,
        Time,
        Length,
        callStart,
        callEnd,
        CASE Category
            WHEN 'Conversation' THEN '1'
            WHEN 'Hang Up On Volunteer' THEN '2'
            WHEN 'Hang Up While Ringing' THEN
                CASE Hotline
                    WHEN 'GLNH' THEN IF(Length < '00:00:38', '3', '4')
                    WHEN 'Youth' THEN IF(Length < '00:00:25', '3', '4')
                    WHEN 'GLSB-NY' THEN IF(Length < '00:00:25', '3', '4')
                    ELSE '3'
                END
            WHEN 'No Volunteers' THEN '5'
            WHEN 'Block-User' THEN '6'
            WHEN 'Block-Admin' THEN '7'
            WHEN 'Block-Admin-Internet Cal' THEN '8'
            WHEN 'In Progress' THEN '9'
            WHEN 'Unanswered Call' THEN '11'
            ELSE '10'
        END AS CallerCategory
    FROM CallerHistory
    JOIN Hours ON Hours.DayofWeek = DATE_FORMAT(CallerHistory.Date, '%w') + 1
    WHERE Date = ?
    AND Time >= Hours.Start
    AND Time < Hours.End 
    AND Hotline NOT IN ('local', 'SF-Local')
    AND Category NOT LIKE '%Block%'
    AND CallerID NOT IN (
        '(415) 355-0003',
        '(415) 577-0667',
        '(415) 525-0636',
        '(666)-966-87'
    )
    ORDER BY Time, CallerCategory, Volunteer";

    return dataQuery($query, [$date]);
}

// Get chat log data
function getChatLogs($date) {
    $query = "SELECT
        volunteer,
        startTime,
        EndTime,
        time
    FROM CallLog
    JOIN Hours ON Hours.DayofWeek = DATE_FORMAT(?, '%w') + 1
    WHERE GLBTNHC_Program LIKE 'Chat'
    AND Date = ?
    AND TIME(startTime) >= Hours.Start
    AND TIME(startTime) < Hours.End";

    return dataQuery($query, [$date, $date]);
}

// Get active chat status
function getActiveChatStatus($date) {
    $query = "SELECT 
        Volunteers.UserName as volunteer,
        TIME(chatStatus.Date) as time,
        chatStatus.Date as callStart,
        NOW() as callEnd,
        TIMEDIFF(NOW(), chatStatus.Date) as length
    FROM chatStatus 
    JOIN volunteers ON (
        Volunteers.Active1 = chatStatus.callerID 
        OR Volunteers.Active2 = chatStatus.callerID
    )
    WHERE (
        Volunteers.active1 IS NOT NULL 
        OR Volunteers.active2 IS NOT NULL
    )
    AND DATE(chatStatus.date) = ?";
    
    return dataQuery($query, [$date]);
}

// Get all data
$Hours = getHours($Date);
$Users = getUsers($Date);

// Build shift times array
$ShiftTimes = [
    'hours' => $Hours,
    'users' => $Users
];
array_push($Calls, $ShiftTimes);

// Process call history
$callHistoryResult = getCallHistory($Date);
if ($callHistoryResult && is_array($callHistoryResult)) {
    foreach ($callHistoryResult as $row) {
        $CallData[] = [
            "caller" => $row->CallerID,
            "volunteer" => $row->Volunteer,
            "time" => $row->Time,
            "length" => $row->Length,
            "callStart" => $row->callStart,
            "callEnd" => $row->callEnd,
            "callerCategory" => $row->CallerCategory
        ];
    }
}

// Process chat logs
$chatLogResult = getChatLogs($Date);
if ($chatLogResult && is_array($chatLogResult)) {
    foreach ($chatLogResult as $row) {
        $CallData[] = [
            "caller" => "Chat",
            "volunteer" => $row->volunteer,
            "time" => $row->startTime,
            "callStart" => $row->startTime,
            "callEnd" => $row->EndTime,
            "length" => $row->time,
            "callerCategory" => '0'
        ];
    }
}

// Process active chats
$activeChatResult = getActiveChatStatus($Date);
if ($activeChatResult && is_array($activeChatResult)) {
    foreach ($activeChatResult as $row) {
        $CallData[] = [
            "caller" => "Chat",
            "volunteer" => $row->volunteer,
            "time" => $row->time,
            "callStart" => $row->callStart,
            "callEnd" => $row->callEnd,
            "length" => $row->length,
            "callerCategory" => '0'
        ];
    }
}

// Build final response
$CallInformation['callData'] = $CallData;
array_push($Calls, $CallInformation);

// Set JSON content type header
header('Content-Type: application/json');

// Output JSON response
echo json_encode($Calls);
?>

