<?php

// Determine sort order

require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$SortOrder = $_REQUEST["SortOrder"] ?? "Date";

$finalSortOrder = match($SortOrder) {
    'Date' => "Date desc, Time desc",
    'Time' => "Time desc, Date desc",
    'CallerID' => "CallerID",
    'Hotline' => "Hotline, Date desc, Time desc",
    'Location' => "right(Location, 2), Location, Date desc, Time desc",
    'Category' => "Category, Date desc, Time desc",
    'Length' => "Length, Date desc, Time desc",
    default => "Date desc, Time desc"
};

// Main query
// Note: errorCount excludes SHAKEN/STIR carrier warnings (codes 32020, 32021 or messages containing SHAKEN/STIR/PASSporT)
$query = "SELECT
    CONCAT(DATE_FORMAT(Date, '%m'), '/', DATE_FORMAT(Date, '%d'), '/', DATE_FORMAT(Date, '%y')) as CallDate,
    TIME_FORMAT(Time, '%r') as CallTime,
    CallerID,
    Location,
    Hotline,
    Category,
    Length,
    CALLSID,
    VolunteerID,
    (SELECT CONCAT(FirstName, ' ', LastName) FROM volunteers WHERE UserName = CallerHistory.VolunteerID) as VolunteerName,
    (SELECT gender FROM CallLog WHERE CallLog.CallSid = CallerHistory.CALLSID) as gender,
    (SELECT Age FROM CallLog WHERE CallLog.CallSid = CallerHistory.CALLSID) as age,
    (SELECT callLogNotes FROM CallLog WHERE CallLog.CallSid = CallerHistory.CALLSID) as CallLogNotes,
    (SELECT COUNT(*) FROM TwilioErrorLog
     WHERE JSON_SEARCH(Payload, 'one', CallerHistory.CALLSID) IS NOT NULL
       AND NOT (
           JSON_EXTRACT(Payload, '$.error_code') IN ('32020', '32021')
           OR JSON_EXTRACT(Payload, '$.ErrorCode') IN ('32020', '32021')
           OR Payload LIKE '%SHAKEN%'
           OR Payload LIKE '%STIR%'
           OR Payload LIKE '%PASSporT%'
           OR Payload LIKE '%PPT%'
       )
    ) as errorCount,
    (SELECT COUNT(*) FROM TwilioErrorLog
     WHERE JSON_SEARCH(Payload, 'one', CallerHistory.CALLSID) IS NOT NULL
       AND (
           JSON_EXTRACT(Payload, '$.error_code') IN ('32020', '32021')
           OR JSON_EXTRACT(Payload, '$.ErrorCode') IN ('32020', '32021')
           OR Payload LIKE '%SHAKEN%'
           OR Payload LIKE '%STIR%'
           OR Payload LIKE '%PASSporT%'
           OR Payload LIKE '%PPT%'
       )
    ) as warningCount
FROM CallerHistory
WHERE Date > DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY " . $finalSortOrder;

$result = dataQuery($query);

$callHistory = [];
$Count = 1;

if ($result) {
    foreach ($result as $row) {
        $singleCall = [
            'date' => $row->CallDate,
            'time' => $row->CallTime,
            'callerID' => $row->CallerID,
            'location' => $row->Location ?? '---',
            'hotline' => $row->Hotline,
            'category' => $row->Category,
            'length' => $row->Length,
            'callSid' => $row->CALLSID,
            'hasErrors' => ($row->errorCount > 0),
            'hasWarnings' => ($row->warningCount > 0),
            'gender' => $row->gender,
            'age' => $row->age,
            'callLogNotes' => $row->CallLogNotes,
            'volunteerName' => trim($row->VolunteerName ?? '')
        ];

        // Transform hotline names
        $singleCall['hotline'] = match($singleCall['hotline']) {
            'SF GLNH', 'NY GLNH' => 'GLNH',
            'Youth Talkline' => 'YOUTH',
            default => $singleCall['hotline']
        };

        // Transform category names
        $singleCall['category'] = match($singleCall['category']) {
            'Hang Up on Volunteer' => 'H/U',
            'Conversation' => 'CONV',
            'Block-Admin' => 'BLK-A',
            'Block-Admin-CallerID' => 'BLK-C',
            'Block-Admin-Internet Cal' => 'SKYPE',
            'Block-User' => 'BLK-U',
            'Closed' => 'CLSD',
            'No Answer' => 'N/A',
            'Ringing' => 'RING',
            'No Volunteers' => 'Busy',
            'Hang Up While Ringing' => 'H/U-R',
            default => $singleCall['category']
        };

        $callHistory[$Count] = $singleCall;
        $Count++;
    }
}

// Output JSON
header('Content-Type: application/json');
echo json_encode($callHistory);
?>
