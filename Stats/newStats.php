<?php


require_once '../../private_html/db_login.php';
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database configuration

// Get web address
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$WebAddress = $protocol . '://' . $host;

// Initialize variables
$Date = $_REQUEST['date'] ?? date("m/d/y");
$printFlag = $_REQUEST['print'] ?? null;
$Statistics = array();
$statData = array();

// Convert date and get day info
$phpdate = strtotime($Date);
$dayOfWeek = date("w", $phpdate) + 1;
$dayOfWeekName = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');

// Get shift hours
$query = "SELECT start, end FROM Hours WHERE DayofWeek = ? AND Shift = '1'";
$result = dataQuery($query, [$dayOfWeek]);

// Initialize to null for closed days
$DayStart = null;
$DayEnd = null;

if ($result && is_array($result) && count($result) > 0) {
    $DayStart = $result[0]->start ?? null;
    $DayEnd = $result[0]->end ?? null;
}

$startTime = $Date . " " . ($DayStart ?? '00:00:00');
$endTime = $Date . " " . ($DayEnd ?? '00:00:00');
$startHour = strtotime($startTime);
$endDay = strtotime($endTime);

// Output HTML header and styles
?>
<!DOCTYPE html>
<html>
<head>
    <title>VCC Daily Statistics</title>
    <script src="newStats.js" type="text/javascript"></script>
    <style>
        th, td {
            width: 80px;
            text-align: center;
            border-bottom: 1px dotted black;
            border-left: 1px dotted black;
        }
        h1, #inputData {
            width: 800px;
            text-align: center;
        }
        @media print {
            .pagebreak { page-break-after: always; }
            .noprint { display: none; }
            body, th, td { font-size: 10pt; }
        }
        .centered {
            width: 800px;
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>VCC Statistics<br><?php echo $dayOfWeekName[$dayOfWeek - 1] . ", " . date("F j, Y", $phpdate); ?></h1>
    <div id='inputData'>
        <label class='noPrint'>Date: </label>
        <input class='noPrint' id='date' type='text' value='<?php echo date('m/d/y', $phpdate); ?>' />
        <input class='noPrint' id='refresh' type='button' value="Refresh Stats" />
        <br class='noPrint'><br class='noPrint'>
        <input class='noPrint' id='backButton' type='button' value='<- Back' />
        <input class='noPrint' id='nextButton' type='button' value='Next ->' />
        <input class='noPrint' id='timeLineButton' type='button' value='Time Line' />
        <input class='noPrint' id='printButton' type='button' value='Print' />
        <input class='noPrint' id='averagesButton' type='button' value='Go to Averages' />
        <input class='noPrint' id='printFlag' type='hidden' value='<?php echo $printFlag; ?>' />
    </div>

<?php
// Define hotlines and process each
$hotlines = ["LGBTQ", "OUT", "Youth", "Senior", "GLSB-NY", "%"];

foreach ($hotlines as $hotline) {
    outputHotlineStats($hotline, $Date, $startHour, $endDay, $DayStart);
}

/**
 * Get statistics for a specific time period
 */
function getStats($startHour, $endHour, $type, $hotline) {
    $Date = date('Y-m-d', $startHour);
    
    // Main query for caller statistics (simplified to avoid GROUP BY issues)
    $query = "SELECT 
        CallerID,
        Category,
        COUNT(*) as call_count
    FROM CallerHistory 
    WHERE date = ? 
        AND Time >= ? 
        AND Time < ? 
        AND Hotline LIKE ? 
        AND Category NOT LIKE '%Block%' 
        AND Category NOT LIKE '%While Ringing%' 
        AND Category NOT LIKE '%Closed%'
        AND CallerID NOT IN (
            '(415) 355-0003',
            '(415) 577-0667',
            '(415) 525-0636',
            '(666)-966-87'
        )
    GROUP BY CallerID, Category
    ORDER BY CallerID";

    $params = [
        $Date,
        date('H:i:s', $startHour),
        date('H:i:s', $endHour),
        $hotline
    ];

    $result = dataQuery($query, $params);

    // Initialize counters
    $stats = initializeStats();
    
    // Process results (reorganize by caller for processing)
    if ($result && is_array($result)) {
        $callerData = [];
        
        // Group results by CallerID
        foreach ($result as $row) {
            if (isset($row->CallerID) && isset($row->Category) && isset($row->call_count)) {
                $callerID = $row->CallerID;
                if (!isset($callerData[$callerID])) {
                    $callerData[$callerID] = [];
                }
                
                // Map category to number
                $categoryNumber = mapCategoryToNumber($row->Category);
                
                $callerData[$callerID][] = [
                    'CallerCategory' => $categoryNumber,
                    'Calls' => (int)$row->call_count
                ];
            }
        }
        
        // Process each caller's data
        foreach ($callerData as $callerID => $results) {
            processCallerResults($results, $stats);
        }
    }

    // Calculate percentages
    calculatePercentages($stats);

    // Get chat statistics if needed
    if ($hotline === "%") {
        $chatQuery = "SELECT COUNT(*) as chat_count 
                     FROM CallLog 
                     WHERE GLBTNHC_PROGRAM = 'Chat' 
                     AND (TERMINATE = 'SAVE' OR Time > '00:03:00')
                     AND Date = ?
                     AND StartTime >= ?
                     AND startTime < ?";
        
        $chatParams = [
            $Date,
            date('Y-m-d H:i:s', $startHour),
            date('Y-m-d H:i:s', $endHour)
        ];

        $chatResult = dataQuery($chatQuery, $chatParams);
        if ($chatResult) {
            $stats['Chats'] = $chatResult[0]->chat_count;
            $stats['totalLegitimate'] = $stats['LegitimateCalls'] + $stats['Chats'];
        }
    }

    // Output the row
    outputStatsRow($stats, $type, $startHour, $hotline);

    return $stats;
}

/**
 * Map category names to numbers
 */
function mapCategoryToNumber($category) {
    switch ($category) {
        case 'Conversation': return '1';
        case 'Hang Up On Volunteer': return '2';
        case 'Hang Up While Ringing': return '3';
        case 'No Volunteers': return '4';
        case 'Unanswered Call': return '5';
        case 'Block-User': return '6';
        case 'Block-Admin': return '7';
        case 'Block-Admin-Internet Cal': return '8';
        default: return '9';
    }
}

/**
 * Initialize statistics counters
 */
function initializeStats() {
    return [
        'Callers' => 0,
        'LegitimateCalls' => 0,
        'LegitCallers' => 0,
        'Answered' => 0,
        'Unanswered' => 0,
        'Helped' => 0,
        'Not_Helped' => 0,
        'Chats' => 0,
        'totalLegitimate' => 0
    ];
}

/**
 * Process results for a single caller
 */
function processCallerResults($results, &$stats) {
    if (!is_array($results)) {
        return;
    }
    
    $stats['Callers']++;
    
    foreach ($results as $result) {
        if (!is_array($result) || !isset($result['CallerCategory']) || !isset($result['Calls'])) {
            continue;
        }
        
        $category = $result['CallerCategory'];
        $calls = (int)$result['Calls'];

        switch ($category) {
            case '1': // Conversation
                $stats['LegitCallers']++;
                $stats['Helped']++;
                $stats['LegitimateCalls'] += $calls;
                break;
            case '2': // Hang Up On Volunteer
                $stats['Answered']++;
                $stats['Helped']++;
                break;
            case '3': // Hang Up While Ringing
                $stats['Helped']++;
                break;
            case '4': // No Volunteers
                $stats['Unanswered'] += $calls;
                break;
        }
    }
}

/**
 * Calculate percentages for statistics
 */
function calculatePercentages(&$stats) {
    // Initialize percentage fields
    $stats['AnsweredPercent'] = 0;
    $stats['UnansweredPercent'] = 0;
    $stats['HelpedPercent'] = 0;
    $stats['Not_HelpedPercent'] = 0;
    $stats['Not_Helped'] = 0;
    
    if ($stats['Callers'] > 0) {
        $stats['AnsweredPercent'] = round(($stats['Answered'] / $stats['Callers']) * 100);
        $stats['UnansweredPercent'] = round(($stats['Unanswered'] / $stats['Callers']) * 100);
        $stats['Not_Helped'] = $stats['Callers'] - $stats['Helped'];
        $stats['HelpedPercent'] = round(($stats['Helped'] / $stats['Callers']) * 100);
        $stats['Not_HelpedPercent'] = round(($stats['Not_Helped'] / $stats['Callers']) * 100);
    }
}

/**
 * Output statistics for a specific hotline
 */
function outputHotlineStats($hotline, $Date, $startHour, $endDay, $DayStart) {
    // Output header for this hotline
    outputHotlineHeader($hotline);

    // Process hourly stats
    $currentHour = $startHour;
    while ($currentHour < $endDay) {
        $nextHour = strtotime("+1 hours", $currentHour);
        $stats = getStats($currentHour, $nextHour, "Hour", $hotline);
        $currentHour = $nextHour;
    }

    // Process daily total
    getStats($startHour, $endDay, "Day", $hotline);

    echo "</table><br><br><br>";
}

/**
 * Output the header for a hotline's statistics table
 */
function outputHotlineHeader($hotline) {
    if ($hotline === "%") {
        echo "<h2>TOTALS FOR ALL HOTLINES</h2>";
        echo "<table><tr><th>Hour</th><th>Callers</th><th>Answered</th><th>%</th>
              <th>Not Answered</th><th>%</th><th>Conv. Callers</th><th>Conv. Calls</th>
              <th>Chats</th><th>Legit Total</th></tr>";
    } else {
        echo "<h2>Hotline: {$hotline}</h2>";
        $tableClass = ($hotline === "Youth") ? "pagebreak" : "";
        echo "<table class='{$tableClass}'><tr><th>Hour</th><th>Callers</th>
              <th>Answered</th><th>%</th><th>Not Answered</th><th>%</th>
              <th>Conv. Callers</th><th>Conv. Calls</th></tr>";
    }
}

/**
 * Output a single row of statistics
 */
function outputStatsRow($stats, $type, $startHour, $hotline) {
    echo "<tr>";
    echo $type == "Day" ? "<td><b>TOTAL</b></td>" : "<td>" . date("g:ia", $startHour) . "</td>";
    echo "<td>{$stats['Callers']}</td>";
    echo "<td>{$stats['Helped']}</td>";
    echo "<td>{$stats['HelpedPercent']}%</td>";
    echo "<td>{$stats['Not_Helped']}</td>";
    echo "<td>{$stats['Not_HelpedPercent']}%</td>";
    echo "<td>{$stats['LegitCallers']}</td>";
    echo "<td>{$stats['LegitimateCalls']}</td>";
    
    if ($hotline === "%") {
        echo "<td>{$stats['Chats']}</td>";
        if ($type == "Day") {
            echo "<td><b>{$stats['totalLegitimate']}</b></td>";
        } else {
            echo "<td>{$stats['totalLegitimate']}</td>";
        }
    }
    
    echo "</tr>";
}
?>
</body>
</html>
