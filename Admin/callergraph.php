<?php


require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

function DrawGraph($Shift, $LineNumber, $Type) {
    $Date = $_REQUEST['Date'];
    $PriorShift = $Shift - 1;
    
    // Base query for both current and prior shift
    $baseQuery = "SELECT 
        CallLog.Desk, 
        CallLog.GLBTNHC_Program, 
        CallLog.StartTime, 
        CallLog.Time, 
        DATE_FORMAT(CallLog.Date,'%w') + 1 as CallDayofWeek, 
        Hours.shift, 
        Hours.start, 
        Hours.end, 
        HOUR(SUBTIME(TIME(StartTime),start)) * 60 + MINUTE(SUBTIME(TIME(StartTime),start)) as StartMinutes, 
        ROUND(HOUR(CallLog.Time) * 60 + Minute(CallLog.Time) + SECOND(CallLog.Time) / 60 , 1) as CallLength 
        FROM CallLog 
        INNER JOIN Hours ON (DATE_FORMAT(CallLog.Date,'%w') + 1 = Hours.DayofWeek) 
        WHERE CallLog.Date = :date 
        AND CallLog.Desk = :lineNumber";

    // Current shift query
    $query = $baseQuery . " AND Time(StartTime) >= start 
        AND Time(StartTime) <= End 
        AND Hours.Shift = :shift";

    // Prior shift query for calls that extend into current shift
    $query2 = $baseQuery . " AND Time(StartTime) >= start 
        AND Time(StartTime) <= End 
        AND AddTime(Time(StartTime), Time(Time)) > End 
        AND Hours.Shift = :priorShift";

    $Line = array();
    $Program = array();
    $StartTime = array();
    $TimeLength = array();
    $DayOfWeek = array();
    $ShiftArray = array();
    $ShiftStart = array();
    $ShiftEnd = array();
    $StartMinute = array();
    $Length = array();
    
    $Count = 1;

    // Process prior shift calls if not first shift
    if ($Shift > 1) {
        $result2 = dataQuery($query2, [
            'date' => $Date,
            'lineNumber' => $LineNumber,
            'priorShift' => $PriorShift
        ]);

        if ($result2) {
            foreach ($result2 as $row) {
                $Line[$Count] = $row->Desk;
                $Program[$Count] = $row->GLBTNHC_Program;
                
                // Program name standardization
                $Program[$Count] = standardizeProgram($Program[$Count]);
                
                $StartTime[$Count] = $row->StartTime;
                $TimeLength[$Count] = $row->Time;
                $DayOfWeek[$Count] = $row->CallDayofWeek;
                $ShiftArray[$Count] = $row->shift;
                $ShiftStart[$Count] = $row->start;
                $ShiftEnd[$Count] = $row->end;
                $StartMinute[$Count] = 0; // For prior shift calls
                $Length[$Count] = $row->CallLength;
                
                // Process lengths and program types
                processCallLength($Length[$Count], $Program[$Count]);
                
                $StartMinute[$Count] *= 10;
                $Length[$Count] *= 10;
                
                $Count++;
            }
        }
    }

    // Process current shift calls
    $result = dataQuery($query, [
        'date' => $Date,
        'lineNumber' => $LineNumber,
        'shift' => $Shift
    ]);

    if ($result) {
        foreach ($result as $row) {
            $Line[$Count] = $row->Desk;
            $Program[$Count] = $row->GLBTNHC_Program;
            
            // Program name standardization
            $Program[$Count] = standardizeProgram($Program[$Count]);
            
            $StartTime[$Count] = $row->StartTime;
            $TimeLength[$Count] = $row->Time;
            $DayOfWeek[$Count] = $row->CallDayofWeek;
            $ShiftArray[$Count] = $row->shift;
            $ShiftStart[$Count] = $row->start;
            $ShiftEnd[$Count] = $row->end;
            $StartMinute[$Count] = $row->StartMinutes;
            $Length[$Count] = $row->CallLength;
            
            // Process lengths and program types
            processCallLength($Length[$Count], $Program[$Count]);
            
            $StartMinute[$Count] *= 10;
            $Length[$Count] *= 10;
            
            // Adjust length if call extends beyond shift
            if ($StartMinute[$Count] + $Length[$Count] > 1200) {
                $Length[$Count] = 1200 - $StartMinute[$Count];
            }
            
            $Count++;
        }
    }

    $FoundCount = $Count;
    $Count = 1;
    $callLine = null;

    // Generate HTML output
    while ($Count < $FoundCount) {
        $callLine .= "<div style=\"left:" . $StartMinute[$Count] . "px;width:" . $Length[$Count] . 
                     "px\" class=\"call " . $Program[$Count] . "\"></div>\n";
        $Count++;
    }

    return $callLine;
}

// Helper function to standardize program names
function standardizeProgram($program) {
    switch($program) {
        case "NY GLNH":
        case "SF GLNH":
            return 'GLNH';
        case "Youth Talkline":
            return 'YOUTH';
        case "Chat SF":
        case "CHAT NY":
            return 'CHAT';
        default:
            return $program;
    }
}

// Helper function to process call length and adjust program type if needed
function processCallLength(&$length, &$program) {
    if ($length < 15) {
        switch($program) {
            case "GLNH":
                $program = 'GLNH-HANGUP';
                break;
            case "YOUTH":
                $program = 'YOUTH-HANGUP';
                break;
        }
    }
    if ($length < 5) {
        $length = 5;
    }
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title>Tim's Bar Graph Test</title>
	<style>
		.shift {
			background-color:	silver;
			border:				solid 3px maroon;
			height:				100px;
			width:				1202px;
		}

		#s1 {
			position:			absolute;
			top:				20px;
			left:				20px;
			background-color:	silver;
			}
	
		#s2 {
			position:			absolute;
			top:				130px;
			left:				20px;
			background-color:	silver;
			}
		#s3 {
			position:			absolute;
			top:				240px;
			left:				20px;
			background-color:	silver;
			}
		#s4 {
			position:			absolute;
			top:				350px;
			left:				20px;
			background-color:	silver;
			}
	
		.line {
			position:			relative;
			top:				0px;
			left:				0px;
			height:				15px;
			width:				1200px;
			border:				solid 1px gray;
			background-color:	#white;
			margin-bottom:		3px;
		}
		
		.call {
			position:			absolute;
			height:				15px;
			}
							
		.GLNH {
			background-color:	#00AA00;
			}

		.YOUTH {
			background-color:	#11FF11;
			}

		.CHAT {
			background-color:	#999900;
			}

		.DOUBLECHAT {
			background-color:	#666611;
			}

		.GLNH-HANGUP {
			background-color:	#DD0000;
			}
			
		.YOUTH-HANGUP {
			background-color:	#FF6666;
			}

			
	</style>
</head>
<body>
	<div class="shift" id="s1">
		<div class="line">
			<?php
				echo DrawGraph(1, 5, 1); 
				//echo DrawGraph(1, 5, 2); 
				//echo DrawGraph(1, 5, 3); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(1, 4); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(1, 3); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(1, 2); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(1, 1); 
			?>
		</div>
	</div>
	<div class="shift" id="s2">
		<div class="line">
			<?php
				echo DrawGraph(2, 5); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(2, 4); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(2, 3); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(2, 2); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(2, 1); 
			?>
		</div>
	</div>
		<div class="shift" id="s3">
		<div class="line">
			<?php
				echo DrawGraph(3, 5); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(3, 4); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(3, 3); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(3, 2); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(3, 1); 
			?>
		</div>
	</div>

	<div class="shift" id="s4">
		<div class="line">
			<?php
				echo DrawGraph(4, 5); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(4, 4); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(4, 3); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(4, 2); 
			?>
		</div>
		<div class="line">
			<?php
				echo DrawGraph(4, 1); 
			?>
		</div>
	</div>
</body>
</html>
