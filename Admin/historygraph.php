<?php


require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

function DrawGraph($Shift, $LineNumber, $Type = null) {
    $Date = $_REQUEST['Date'];
    
    // Common parts of both queries
    $baseQuery = "SELECT 
        RIGHT(CallerHistory.AnsweredLine,1) as Desk,
        CallerHistory.Hotline,
        CallerHistory.Time,
        CallerHistory.Length,
        DATE_FORMAT(CallerHistory.Date,'%w') + 1 as CallDayofWeek,
        Hours.shift,
        Hours.start,
        Hours.end,
        HOUR(SUBTIME(TIME(Time),start)) * 60 + MINUTE(SUBTIME(TIME(Time),start)) as StartMinutes,
        ROUND(HOUR(CallerHistory.Length) * 60 + Minute(CallerHistory.Length) + SECOND(CallerHistory.Length) / 60 , 1) as CallLength 
    FROM CallerHistory 
    INNER JOIN Hours ON (DATE_FORMAT(CallerHistory.Date,'%w') + 1 = hours.DayOfWeek) 
    WHERE CallerHistory.Date = :date 
    AND RIGHT(CallerHistory.AnsweredLine, 1) = :line_number 
    AND Hours.Shift = :shift";

    // Regular calls query
    $query = $baseQuery . " AND CallerHistory.Time >= start AND CallerHistory.Time <= End";

    // Overlapping shifts query
    $query2 = $baseQuery . " AND CallerHistory.Time >= start AND CallerHistory.Time <= End 
                            AND AddTime(CallerHistory.Time, Time(Length)) > End";

    $params = [
        ':date' => $Date,
        ':line_number' => $LineNumber,
        ':shift' => $Shift
    ];

    $Line = [];
    $Program = [];
    $StartTime = [];
    $Length = [];
    $StartMinute = [];
    $Count = 1;

    // Process overlapping shifts for shifts after the first
    if ($Shift > 1) {
        $result2 = dataQuery($query2, $params);
        if ($result2) {
            foreach ($result2 as $row) {
                $Line[$Count] = $row->Desk;
                $Program[$Count] = normalizeProgram($row->Hotline);
                $StartTime[$Count] = $row->Time;
                $TimeLength[$Count] = $row->Length;
                $StartMinute[$Count] = 0;
                $Length[$Count] = $row->CallLength * 10;
                
                // Adjust program name and length for short calls
                list($Program[$Count], $Length[$Count]) = adjustCallProperties($Program[$Count], $Length[$Count]);
                
                $Count++;
            }
        }
    }

    // Process regular calls
    $result = dataQuery($query, $params);
    if ($result) {
        foreach ($result as $row) {
            $Line[$Count] = $row->Desk;
            $Program[$Count] = normalizeProgram($row->Hotline);
            $StartTime[$Count] = $row->Time;
            $TimeLength[$Count] = $row->Length;
            $StartMinute[$Count] = $row->StartMinutes * 10;
            $Length[$Count] = $row->CallLength * 10;
            
            // Adjust program name and length for short calls
            list($Program[$Count], $Length[$Count]) = adjustCallProperties($Program[$Count], $Length[$Count]);
            
            // Ensure call doesn't exceed timeline
            if ($StartMinute[$Count] + $Length[$Count] > 1200) {
                $Length[$Count] = 1200 - $StartMinute[$Count];
            }
            
            $Count++;
        }
    }

    // Generate HTML for calls
    $callLine = "";
    for ($i = 1; $i < $Count; $i++) {
        $callLine .= sprintf(
            "<div style=\"left:%dpx;width:%dpx\" class=\"call %s\"></div>\n",
            $StartMinute[$i],
            $Length[$i],
            $Program[$i]
        );
    }
    
    return $callLine;
}

// Helper function to normalize program names
function normalizeProgram($hotline) {
    $programMap = [
        'NY GLNH' => 'GLNH',
        'SF GLNH' => 'GLNH',
        'Youth Talkline' => 'YOUTH',
        'Chat SF' => 'CHAT',
        'CHAT NY' => 'CHAT'
    ];
    
    return $programMap[$hotline] ?? $hotline;
}

// Helper function to adjust call properties based on length
function adjustCallProperties($program, $length) {
    if ($length < 15) {
        if ($program === 'GLNH') {
            $program = 'GLNH-HANGUP';
        } elseif ($program === 'YOUTH') {
            $program = 'YOUTH-HANGUP';
        }
    }
    
    if ($length < 5) {
        $length = 5;
    }
    
    return [$program, $length];
}
?>
<!DOCTYPE HTML>
<html>
<head>
    <title>Call Timeline Visualization</title>
    <style>
        .shift {
            background-color: silver;
            border: solid 3px maroon;
            height: 100px;
            width: 1202px;
        }

        #s1 { position: absolute; top: 20px; left: 20px; }
        #s2 { position: absolute; top: 130px; left: 20px; }
        #s3 { position: absolute; top: 240px; left: 20px; }
        #s4 { position: absolute; top: 350px; left: 20px; }
    
        .line {
            position: relative;
            top: 0px;
            left: 0px;
            height: 15px;
            width: 1200px;
            border: solid 1px gray;
            background-color: white;
            margin-bottom: 3px;
        }
        
        .call {
            position: absolute;
            height: 15px;
        }
                        
        .GLNH { background-color: #00AA00; }
        .YOUTH { background-color: #11FF11; }
        .CHAT { background-color: #999900; }
        .DOUBLECHAT { background-color: #666611; }
        .GLNH-HANGUP { background-color: #DD0000; }
        .YOUTH-HANGUP { background-color: #FF6666; }
    </style>
</head>
<body>
    <?php
    for ($shift = 1; $shift <= 4; $shift++) {
        echo "<div class=\"shift\" id=\"s{$shift}\">\n";
        for ($line = 5; $line >= 1; $line--) {
            echo "<div class=\"line\">\n";
            echo DrawGraph($shift, $line);
            echo "</div>\n";
        }
        echo "</div>\n";
    }
    ?>
</body>
</html>
