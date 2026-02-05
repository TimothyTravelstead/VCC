<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours
require_once '../../private_html/db_login.php';

// Now start the session with the correct configuration
session_start();

// Release session lock immediately (no session data used in this script)
session_write_close();

// Initialize variables
$startDate = $_REQUEST['startDate'] ?? date("m/d/y");
$endDate = $_REQUEST['endDate'] ?? null;
$Statistics = array();
$statData = array();

$phpStartDate = strtotime($startDate);
$phpEndDate = strtotime($endDate);
$dayOfWeekName = array('', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');

// Redirect if no end date
if (!$endDate) {
    header("Location: http://vcctest.org/Stats/newStats.php?date=" . $startDate);
    exit;
}

// Get web address
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$WebAddress = $protocol . '://' . $host;

// Output HTML header
?>
<!DOCTYPE html>
<html>
<head>
    <title>VCC Average Daily Statistics</title>
    <script>
        function toMysqlDate(currentDate) {
            var currentDate = new Date(currentDate);
            var twoDigitMonth = (currentDate.getMonth() + 1 < 10 ? '0' : '') + (currentDate.getMonth() + 1);
            var twoDigitDate = (currentDate.getDate() < 10 ? '0' : '') + currentDate.getDate();
            var mysqlDate = currentDate.getFullYear() + "-" + twoDigitMonth + "-" + twoDigitDate;
            return mysqlDate;
        }

        function refresh() {
            var refresh = document.getElementById("refresh");
            refresh.click();
        }

        Date.prototype.getNextDay = function(days) {
            if(!days) {
                var days = 1;
            }
            var d = new Date(this);
            var dayOfMonth = d.getDate();
            d.setDate(dayOfMonth + days);
            return d;
        }

        window.onload = function() {
            var refresh = document.getElementById("refresh");
            var dateElement = document.getElementById("startDate");
            var printButton = document.getElementById("printButton");
            var singleDateButton = document.getElementById("singleDateButton");

            var date = document.getElementById("startDate").value;
            if(!dateElement.value) {
                dateElement.value = new Date().toLocaleDateString();
            }

            refresh.onclick = function() {
                var startDate = new Date(document.getElementById("startDate").value);
                var endDate = new Date(document.getElementById("endDate").value);
                if(endDate == "") {
                    enddate = startDate;
                }
                var aLink = document.createElement("a");
                aLink.href = "newStatsAverages.php?startDate=" + toMysqlDate(startDate) + "&endDate=" + toMysqlDate(endDate);
                var body = document.getElementsByTagName("body")[0];
                body.appendChild(aLink);
                aLink.click();
            }

            printButton.onclick = function() {
                window.print();
            }

            singleDateButton.onclick = function() {
                var date = document.getElementById("startDate").value;
                var aLink = document.createElement("a");
                aLink.href = "newStats.php?date=" + toMysqlDate(date);
                var body = document.getElementsByTagName("body")[0];
                body.appendChild(aLink);
                aLink.click();
            }
        }
    </script>
    <style>
        th, td {
            width: 80px;
            text-align: center;
            font-size: 10pt;
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
        }
        .centered {
            width: 800px;
            text-align: center;
        }
        body { font-size: 10pt; }
    </style>
</head>
<body>
    <h1 class='noprint'>VCC Average Daily Statistics</h1>
    <div id='inputData'>
        <label class='noprint'>Start Date: </label>
        <input class='noprint' id='startDate' type='text' value='<?php echo date('m/d/y', $phpStartDate); ?>' />
        <label class='noprint'>End Date: </label>
        <input class='noprint' id='endDate' type='text' value='<?php echo date('m/d/y', $phpEndDate); ?>' />
        <input class='noprint' id='refresh' type='button' value="Refresh Stats" /><br>
        <input class='noPrint' id='printButton' type='button' value='Print' />
        <input class='noPrint' id='singleDateButton' type='button' value='Go to Single Day' /><br>
    </div>

<?php

$hotlines = array("LGBTQ", "OUT", "Youth", "SENIOR", "GLSB-NY", "%");

$hotlines_count = count($hotlines);
$i = 0;

// Check if this is a single day query
$isSingleDay = ($startDate === $endDate);
if ($isSingleDay) {
    // Get the day of week for the single date (1=Sunday through 7=Saturday, matching our $dayOfWeekName array)
    $singleDayOfWeek = date('w', $phpStartDate) + 1;
}

// Start at 2 (Monday) - hotline is closed on Sundays
$dayOfWeek = 2;

while ($dayOfWeek < 9) {

    // For single day queries, skip days that don't match
    if ($isSingleDay) {
        // Show the specific day, and Weekday section (8) only if it's a weekday (Mon-Fri = 2-6)
        if ($dayOfWeek != $singleDayOfWeek && !($dayOfWeek == 8 && $singleDayOfWeek >= 2 && $singleDayOfWeek <= 6)) {
            $dayOfWeek++;
            continue;
        }
    }

    if($dayOfWeek == 8) {
        $dayName = "Weekday";
        $j = 2;
        $numberOfDays = 0;
        while ($j < 7) {
            $numberOfDays += countDays($startDate, $endDate, $j);
            $j += 1;
        }
    } else {
        $dayName = $dayOfWeekName[$dayOfWeek];
        $numberOfDays = countDays($startDate, $endDate, $dayOfWeek);
    }

    // Skip sections with 0 days (unless single day - then show "1 Day" message)
    if ($numberOfDays == 0 && !$isSingleDay) {
        $dayOfWeek++;
        continue;
    }

    $dayLabel = $numberOfDays == 1 ? "Day" : "Days";
    echo "<p class='centered'><strong>VCC Average Statistics for ".$dayName."s</strong><br>";
    echo date("F j, Y", $phpStartDate)." to ";
    echo date("F j, Y", $phpEndDate)."<br>";
    echo "Averaged Over ".$numberOfDays." ".$dayLabel."</p>";

    while ($i < $hotlines_count) {

        if($dayOfWeek == 8) {
            $phpDayOfWeek = 2;
        } else {
            $phpDayOfWeek = $dayOfWeek;
        }

        $query = "SELECT start FROM Hours WHERE DayofWeek = ? AND Shift = '1'";
        $result = dataQuery($query, [$phpDayOfWeek]);
        $DayStart = null;
        $DayEnd = null;

        if ($result && is_array($result) && count($result) > 0) {
            $DayStart = $result[0]->start;
        }

        $query = "SELECT end FROM Hours WHERE DayofWeek = ? AND Shift = '1'";
        $result = dataQuery($query, [$phpDayOfWeek]);

        if ($result && is_array($result) && count($result) > 0) {
            $DayEnd = $result[0]->end;
        }

        $startTime = $startDate." ".$DayStart ?? null;
        $endTime = $startDate." ".$DayEnd ?? null;
        $startHour = strtotime($startDate." ".$DayStart) ?? null;
        $endDay = strtotime($endTime);

        $hotline = $hotlines[$i];

        if($hotline === "%") {
            echo "<p><strong>TOTALS FOR ALL HOTLINES</strong></p>";
            echo "<table class='pagebreak'><tr><th>Hour</th><th>Callers</th><th>Answered</th><th>%</th><th>Not Answered</th>
                    <th>%</th><th>Conv. Callers</th><th>Conv. Calls</th><th>Chats</th><th>Legit Total</th></tr>";
        } else if ($hotline === "Youth") {
            echo "<p><strong>".$hotline."</strong></p>";
            echo "<table class='pagebreak'><tr><th>Hour</th><th>Callers</th><th>Answered</th><th>%</th><th>Not Answered</th>
                    <th>%</th><th>Conv. Callers</th><th>Legitimate Calls</th></tr>";
        } else {
            echo "<p><strong>".$hotline."</strong></p>";
            echo "<table><tr><th>Hour</th><th>Callers</th><th>Answered</th><th>%</th><th>Not Answered</th>
                    <th>%</th><th>Conv. Callers</th><th>Legitimate Calls</th></tr>";
        }

        while ($startHour < $endDay) {
            $endHour = strtotime("+1 hours", $startHour);
            $type = "Hour";
            $statData = getStats($startDate, $endDate, $startHour, $endHour, $type, $hotline, $dayOfWeek, $dayName, $numberOfDays);
            $startHour = $endHour;
        }

        $startHour = strtotime($startDate." ".$DayStart);
        $endHour = $endDay;

        $type = "Day";
        $statData = getStats($startDate, $endDate, $startHour, $endHour, $type, $hotline, $dayOfWeek, $dayName, $numberOfDays);

        echo "</table><br>";
        $i += 1;
    }
    $i = 0;
    $dayOfWeek = $dayOfWeek + 1;
}



/**
 * Count occurrences of a specific day between dates
 */
function countDays($from, $to, $day) {
    $from = new DateTime($from);
    $to = new DateTime($to);
    $day = $day - 1;

    $wF = $from->format('w');
    $wT = $to->format('w');

    if ($wF < $wT)       $isExtraDay = $day >= $wF && $day <= $wT;
    else if ($wF == $wT) $isExtraDay = $wF == $day;
    else                 $isExtraDay = $day >= $wF || $day <= $wT;

    return floor($from->diff($to)->days / 7) + $isExtraDay;
}

function getStats($startDate, $endDate, $startHour, $endHour, $type, $hotline, $dayOfWeek, $dayName, $numberOfDays) {

    $statData = array();

    if($dayOfWeek !== 8 && $type != "Day") {

        $query = "SELECT Concat(CallerID, Date, StartHourTime) as CallerIDRecord, JSON_ARRAYAGG(Result)

                FROM

                (SELECT CallerID, Date, left(Time, 2) as StartHourTime, case Category
                            WHEN 'Conversation'                         THEN '1'
                            WHEN 'Hang Up On Volunteer'                 THEN '2'
                            WHEN 'Hang Up While Ringing'                 THEN '3'
                            WHEN 'No Volunteers'                         THEN '4'
                            WHEN 'Unanswered Call'                         THEN '5'
                            WHEN 'Block-User'                             THEN '6'
                            WHEN 'Block-Admin'                             THEN '7'
                            WHEN 'Block-Admin-Internet Cal'             THEN '8'
                            ELSE  '9'
                        END as CallerCategory,

                        JSON_OBJECT('CallerCategory',

                case Category
                            WHEN 'Conversation'                         THEN '1'
                            WHEN 'Hang Up On Volunteer'                 THEN '2'
                            WHEN 'Hang Up While Ringing'                 THEN '3'
                            WHEN 'No Volunteers'                         THEN '4'
                            WHEN 'Unanswered Call'                         THEN '5'
                            WHEN 'Block-User'                             THEN '6'
                            WHEN 'Block-Admin'                             THEN '7'
                            WHEN 'Block-Admin-Internet Cal'             THEN '8'
                            ELSE  '9'
                        END




                 , 'Calls', count(*) ) as Result
                FROM CallerHistory

     where     date >= ? and
            date <= ? and
            dayofweek(date) = ? AND
            Time >= ? and
            Time < ? and
            Hotline LIKE ?  AND
            Category NOT LIKE '%Block%' AND
            Category NOT LIKE '%While Ringing%' AND
            Category NOT LIKE '%Closed%'


                AND CallerID != '(415) 355-0003'
                AND CallerID != '(415) 577-0667'
                AND CallerID != '(415) 525-0636'
                AND CallerID != '(666)-966-87'

    GROUP BY CallerID, Date, StartHourTime, Category ORDER BY CallerID, CallerCategory) as T

    GROUP BY CallerIDRecord";

        $params = [$startDate, $endDate, $dayOfWeek, date('H:i:s', $startHour), date('H:i:s', $endHour), $hotline];

    } else if ($dayOfWeek !== 8 && $type === "Day") {


            $query = "SELECT Concat(CallerID, Date) as CallerIDRecord, JSON_ARRAYAGG(Result)

                    FROM

                    (SELECT CallerID, Date, left(Time, 2) as StartHourTime, case Category
                                WHEN 'Conversation'                         THEN '1'
                                WHEN 'Hang Up On Volunteer'                 THEN '2'
                                WHEN 'Hang Up While Ringing'                 THEN '3'
                                WHEN 'No Volunteers'                         THEN '4'
                                WHEN 'Unanswered Call'                         THEN '5'
                                WHEN 'Block-User'                             THEN '6'
                                WHEN 'Block-Admin'                             THEN '7'
                                WHEN 'Block-Admin-Internet Cal'             THEN '8'
                                ELSE  '9'
                            END as CallerCategory,

                            JSON_OBJECT('CallerCategory',

                    case Category
                                WHEN 'Conversation'                         THEN '1'
                                WHEN 'Hang Up On Volunteer'                 THEN '2'
                                WHEN 'Hang Up While Ringing'                 THEN '3'
                                WHEN 'No Volunteers'                         THEN '4'
                                WHEN 'Unanswered Call'                         THEN '5'
                                WHEN 'Block-User'                             THEN '6'
                                WHEN 'Block-Admin'                             THEN '7'
                                WHEN 'Block-Admin-Internet Cal'             THEN '8'
                                ELSE  '9'
                            END




                     , 'Calls', count(*) ) as Result
                    FROM CallerHistory

         where     date >= ? and
                date <= ? and
                dayofweek(date) = ? AND
                Time >= ? and
                Time < ? and
                Hotline LIKE ?  AND
                Category NOT LIKE '%Block%' AND
                Category NOT LIKE '%While Ringing%' AND
                Category NOT LIKE '%Closed%'


                    AND CallerID != '(415) 355-0003'
                    AND CallerID != '(415) 577-0667'
                    AND CallerID != '(415) 525-0636'
                    AND CallerID != '(666)-966-87'

        GROUP BY CallerID, Date, Category ORDER BY CallerID, CallerCategory) as T

        GROUP BY CallerIDRecord";

        $params = [$startDate, $endDate, $dayOfWeek, date('H:i:s', $startHour), date('H:i:s', $endHour), $hotline];

    } else if ($type === "Day") {

            $query = "SELECT Concat(CallerID, Date) as CallerIDRecord, JSON_ARRAYAGG(Result)

                    FROM

                    (SELECT CallerID, Date, left(Time, 2) as StartHourTime, case Category
                                WHEN 'Conversation'                         THEN '1'
                                WHEN 'Hang Up On Volunteer'                 THEN '2'
                                WHEN 'Hang Up While Ringing'                 THEN '3'
                                WHEN 'No Volunteers'                         THEN '4'
                                WHEN 'Unanswered Call'                         THEN '5'
                                WHEN 'Block-User'                             THEN '6'
                                WHEN 'Block-Admin'                             THEN '7'
                                WHEN 'Block-Admin-Internet Cal'             THEN '8'
                                ELSE  '9'
                            END as CallerCategory,

                            JSON_OBJECT('CallerCategory',

                    case Category
                                WHEN 'Conversation'                         THEN '1'
                                WHEN 'Hang Up On Volunteer'                 THEN '2'
                                WHEN 'Hang Up While Ringing'                 THEN '3'
                                WHEN 'No Volunteers'                         THEN '4'
                                WHEN 'Unanswered Call'                         THEN '5'
                                WHEN 'Block-User'                             THEN '6'
                                WHEN 'Block-Admin'                             THEN '7'
                                WHEN 'Block-Admin-Internet Cal'             THEN '8'
                                ELSE  '9'
                            END




                     , 'Calls', count(*) ) as Result
                    FROM CallerHistory

         where     date >= ? and
                date <= ? and
                dayofweek(date) < '7' AND
                dayofweek(date) > '1' AND
                Time >= ? and
                Time < ? and
                Hotline LIKE ?  AND
                Category NOT LIKE '%Block%' AND
                Category NOT LIKE '%While Ringing%' AND
                Category NOT LIKE '%Closed%'


                    AND CallerID != '(415) 355-0003'
                    AND CallerID != '(415) 577-0667'
                    AND CallerID != '(415) 525-0636'
                    AND CallerID != '(666)-966-87'

        GROUP BY CallerID, Date, Category ORDER BY CallerID, CallerCategory) as T

        GROUP BY CallerIDRecord";

        $params = [$startDate, $endDate, date('H:i:s', $startHour), date('H:i:s', $endHour), $hotline];


    } else {

        $query = "SELECT Concat(CallerID, Date, StartHourTime) as CallerIDRecord, JSON_ARRAYAGG(Result)

                    FROM

                    (SELECT CallerID, Date, left(Time, 2) as StartHourTime, case Category
                                WHEN 'Conversation'                         THEN '1'
                                WHEN 'Hang Up On Volunteer'                 THEN '2'
                                WHEN 'Hang Up While Ringing'                 THEN '3'
                                WHEN 'No Volunteers'                         THEN '4'
                                WHEN 'Unanswered Call'                         THEN '5'
                                WHEN 'Block-User'                             THEN '6'
                                WHEN 'Block-Admin'                             THEN '7'
                                WHEN 'Block-Admin-Internet Cal'             THEN '8'
                                ELSE  '9'
                            END as CallerCategory,

                            JSON_OBJECT('CallerCategory',

                    case Category
                                WHEN 'Conversation'                         THEN '1'
                                WHEN 'Hang Up On Volunteer'                 THEN '2'
                                WHEN 'Hang Up While Ringing'                 THEN '3'
                                WHEN 'No Volunteers'                         THEN '4'
                                WHEN 'Unanswered Call'                         THEN '5'
                                WHEN 'Block-User'                             THEN '6'
                                WHEN 'Block-Admin'                             THEN '7'
                                WHEN 'Block-Admin-Internet Cal'             THEN '8'
                                ELSE  '9'
                            END




                     , 'Calls', count(*) ) as Result
                    FROM CallerHistory

         where     date >= ? and
                date <= ? and
                dayofweek(date) < '7' AND
                dayofweek(date) > '1' AND
                Time >= ? and
                Time < ? and
                Hotline LIKE ?  AND
                Category NOT LIKE '%Block%' AND
                Category NOT LIKE '%While Ringing%' AND
                Category NOT LIKE '%Closed%'


                    AND CallerID != '(415) 355-0003'
                    AND CallerID != '(415) 577-0667'
                    AND CallerID != '(415) 525-0636'
                    AND CallerID != '(666)-966-87'

        GROUP BY CallerID, Date, StartHourTime, Category ORDER BY CallerID, CallerCategory) as T

        GROUP BY CallerIDRecord";

        $params = [$startDate, $endDate, date('H:i:s', $startHour), date('H:i:s', $endHour), $hotline];

    }

    $result = dataQuery($query, $params);
    $Callers = is_array($result) ? count($result) : 0;

    $Caller = "";
    $Calls = "";
    $Subcategory = "";


    $LegitimateCalls = 0;
    $LegitCallers = 0;
    $GrossCallers = 0;
    $Answered = 0;
    $Unanswered = 0;
    $Helped = 0;
    $Not_Helped = 0;
    $PriorCaller = "";
    $CallerDone = false;
    $Categories = array();
    for($i=0; $i < 10; $i++) {
        $Categories[$i] = 0;
    }
    $rowCount = 1;


    if ($result && is_array($result)) {
        foreach ($result as $result_row) {
            $Caller = $result_row->CallerIDRecord ?? '';
            $Results = json_decode($result_row->{'JSON_ARRAYAGG(Result)'} ?? '[]');

            $count = count($Results);
            $current = 0;

            if($Results[0]->CallerCategory == 1) {
                $LegitCallers += 1;
                $Helped += 1;
                $LegitimateCalls += $Results[0]->Calls;

            } elseif ($Results[0]->CallerCategory == 2) {
                $Answered += 1;
                $Helped += 1;
            } elseif ($Results[0]->CallerCategory == 3) {
                $Helped += 1;
            }
        }
    }

    $Not_Helped = $Callers - $Helped;


    if($Callers > 0) {
        $AnsweredPercent = number_format($Answered / $Callers * 100,0);
        $UnansweredPercent = number_format($Unanswered / $Callers * 100,0);
    } else {
        $AnsweredPercent = 0;
        $UnansweredPercent = 0;
    }

    if($Helped > 0) {
        $HelpedPercent = number_format($Helped / $Callers * 100,0);
        $Not_HelpedPercent = number_format($Not_Helped / $Callers * 100,0);
    } else {
        $HelpedPercent = 0;
        $Not_HelpedPercent = 100;
    }




    $chatStartHour = date('H:i:s', $startHour);
    $chatEndHour = date('H:i:s', $endHour);

    if($hotline === "%") {

        if($dayOfWeek < 8) {
            $query2 = "SELECT count(*) as chat_count from CallLog
                    WHERE GLBTNHC_PROGRAM = 'Chat' AND
                    (TERMINATE = 'SAVE' OR Time > '00:03:00') AND
                    Date >= ? and
                    Date <= ? and
                    time(StartTime) >= ? and
                    time(startTime) < ? and
                    dayofweek(date) = ?";

            $params2 = [$startDate, $endDate, $chatStartHour, $chatEndHour, $dayOfWeek];

        } else {
            $query2 = "SELECT count(*) as chat_count from CallLog
                    WHERE GLBTNHC_PROGRAM = 'Chat' AND
                    (TERMINATE = 'SAVE' OR Time > '00:03:00') AND
                    Date >= ? and
                    Date <= ? and
                    time(StartTime) >= ? and
                    time(startTime) < ? and
                    dayofweek(date) < '7' AND
                    dayofweek(date) > '1'";

            $params2 = [$startDate, $endDate, $chatStartHour, $chatEndHour];
        }

        $result2 = dataQuery($query2, $params2);
        $Chats = 0;
        if ($result2 && is_array($result2) && count($result2) > 0) {
            $Chats = $result2[0]->chat_count;
        }

        $totalLegitimate = $LegitimateCalls + $Chats;

    }

    if($numberOfDays == 0) {
        return false;
    } else {


        $statData['type'] = $type;
        $statData['date'] = date("Y-m-d", $startHour);
        $statData['startTime'] = date("g:ia",$startHour);
        $statData['endTime'] = date("g:ia",$endHour);
        $statData['Helped'] = $Helped/$numberOfDays;
        $statData['HelpedPercent'] = $HelpedPercent."%";
        $statData['Not_Helped'] = $Not_Helped/$numberOfDays;
        $statData['Not_HelpedPercent'] = $Not_HelpedPercent."%";
        $statData['callers'] = $Callers/$numberOfDays;
        $statData['answered'] = $Answered/$numberOfDays;
        $statData['answeredPercent'] = $AnsweredPercent."%";
        $statData['unanswered'] = $Unanswered/$numberOfDays;
        $statData['unansweredPercent'] = $UnansweredPercent."%";
        $statData['legitimateCallers'] = $LegitCallers/$numberOfDays;
        $statData['legitimateCalls'] = $LegitimateCalls/$numberOfDays;
        $statData['legitimateChats'] = $Chats/$numberOfDays;
        $statData['totalLegitimate'] = $totalLegitimate/$numberOfDays;


        echo "<tr>";

        if($type == "Day") {
            echo "<td><b>TOTAL</b></td>";
        } else {

            echo "<td>".date("g:ia",$startHour)."</td>";
        }
            echo "<td>".round($Callers/$numberOfDays, 0)."</td>";
            echo "<td>".round($Helped/$numberOfDays, 0)."</td>";
            echo "<td>".$HelpedPercent."%"."</td>";
            echo "<td>".round($Not_Helped/$numberOfDays, 0)."</td>";
            echo "<td>".$Not_HelpedPercent."%"."</td>";
            echo "<td>".round($LegitCallers/$numberOfDays, 0)."</td>";
            echo "<td>".round($LegitimateCalls/$numberOfDays, 0)."</td>";
            if($hotline === "%") {
                echo "<td>".round($Chats/$numberOfDays, 1)."</td>";

                if($type == "Day") {
                    echo "<td><b>".round($totalLegitimate/$numberOfDays, 0)."</b></td>";
                } else {
                    echo "<td>".round($totalLegitimate/$numberOfDays, 0)."</td>";
                }
            }

        echo "</tr>";
    }

    return $statData;
}
?>
