<?php


require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$CallerID = $_REQUEST["CallerID"];

function utf8_urldecode($str) { 
    $str = preg_replace("/%u([0-9a-f]{3,4})/i", "&#x\\1;", urldecode($str)); 
    return html_entity_decode($str, null, 'UTF-8'); 
}

// Check if caller is blocked
$queryBlocked = "SELECT Blocked 
                 FROM CallerHistory 
                 WHERE CallerID = :callerID 
                 AND Date = CURDATE() 
                 AND Blocked = 1";

$resultBlocked = dataQuery($queryBlocked, ['callerID' => $CallerID]);

if ($resultBlocked && count($resultBlocked) > 0) {
    echo "Blocked";
    return;
}

// Main query for caller history
$query = "SELECT 
            CONCAT(DATE_FORMAT(Date, '%m'), '/', DATE_FORMAT(Date, '%d'), '/', DATE_FORMAT(Date, '%y')) as CallDate,
            TIME_FORMAT(Time, '%r') as CallTime,
            Hotline,
            Length,
            Category,
            Location,
            (SELECT CallLogNotes FROM CallLog WHERE CallLog.CallSid = CallerHistory.CALLSID) as Notes,
            (SELECT GENDER FROM CallLog WHERE CallLog.CallSid = CallerHistory.CALLSID) as Gender,
            (SELECT Age FROM CallLog WHERE CallLog.CallSid = CallerHistory.CALLSID) as Age
          FROM CallerHistory 
          WHERE CallerID = :callerID 
          AND (Category = 'Conversation' OR Category LIKE 'Hang%' OR Category LIKE 'Block%')
          ORDER BY date DESC, time DESC, hotline ASC";

$result = dataQuery($query, ['callerID' => $CallerID]);

// Initialize arrays to store the results
$calls = [];
$Count = 1;

if ($result) {
    foreach ($result as $row) {
        $calls[$Count] = [
            'Date' => $row->CallDate,
            'Time' => $row->CallTime,
            'Hotline' => $row->Hotline,
            'Length' => $row->Length,
            'Category' => $row->Category,
            'Location' => $row->Location ?: 'Unknown',
            'CallNotes' => htmlspecialchars(urldecode($row->Notes ?? '')),
            'Gender' => htmlspecialchars(urldecode($row->Gender ?? '')),
            'Age' => htmlspecialchars(urldecode($row->Age ?? ''))
        ];

        // Adjust category display
        if ($calls[$Count]['Category'] == "Hang Up on Volunteer" || 
            $calls[$Count]['Category'] == "Hang Up While Ringing") {
            $calls[$Count]['Category'] = "-";
        } else if ($calls[$Count]['Category'] == "Block-Admin-Internet Cal") {
            $calls[$Count]['Category'] = "Block-Internet";
        }

        // Build title for tooltip
        if (!empty($calls[$Count]['CallNotes'])) {
            $calls[$Count]['Title'] = $calls[$Count]['Gender'] . ", " . 
                                    $calls[$Count]['Age'] . "\n\n" . 
                                    $calls[$Count]['CallNotes'];
        } else {
            $calls[$Count]['Title'] = "";
        }

        $Count++;
    }
}

$FoundCount = $Count;
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf8" />
    <title>CALLER HISTORY</title>
    <style>
        tr[title] { }
        body {
            background-color: silver;
            color: maroon;
            text-align: center;
            width: 750px;
            overflow: scroll;
        }
        table {
            background-color: maroon;
            color: black;
            border: 2px solid silver;
        }
        th {
            color: silver;
        }
        .Date {
            left: 2px;
            margin-left: 0px;
            width: 75px;
            padding-left: 5px;
            padding-right: 3px;
            text-align: center;
        }
        .Time {
            width: 150px;
            text-align: center;
            padding-right: 5px;
        }
        .Hotline {
            width: 200px;
            text-align: center;
        }
        .Length {
            width: 98px;
            padding-right: 5px;
            text-align: center;
        }
        .Category {
            text-align: center;
            width: 122px;
        }
        .One { background-color: white; }
        .Two { background-color: silver; }
    </style>
</head>
<body>
    <?php
    echo "<h1>" . htmlspecialchars($CallerID) . "</h1>";
    
    if (isset($calls[1]['Location'])) {
        echo "<h2>" . htmlspecialchars($calls[1]['Location']) . "</h2>";
    }
    
    echo "<table>";
    
    if ($FoundCount > 1) {
        echo "<tr>
                <th>Date</th>
                <th>Time</th>
                <th>Hotline</th>
                <th>Length</th>
                <th>Category</th>
              </tr>";
    }
    
    $background = "One";
    for ($Count = 1; $Count < $FoundCount; $Count++) {
        $call = $calls[$Count];
        echo "<tr class='" . $background . "'";
        if (!empty($call['Title'])) {
            echo " title=\"" . htmlspecialchars($call['Title']) . "\"";
        }
        echo ">";
        echo "<td class='Date'>" . htmlspecialchars($call['Date']) . "</td>";
        echo "<td class='Time'>" . htmlspecialchars($call['Time']) . "</td>";
        echo "<td class='Hotline'>" . htmlspecialchars($call['Hotline']) . "</td>";
        echo "<td class='Length'>" . htmlspecialchars($call['Length']) . "</td>";
        echo "<td class='Category'>" . htmlspecialchars($call['Category']) . "</td>";
        echo "</tr>";
        
        $background = ($background == "One") ? "Two" : "One";
    }
    
    echo "</table>";
    
    if ($FoundCount == 1) {
        echo "<h1>No Call History</h1>";
    }
    ?>
</body>
</html><?php
// No need for mysqli_free_result or mysqli_close as dataQuery handles cleanup
?>
