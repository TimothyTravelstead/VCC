<?php


require_once('../../private_html/db_login.php');
session_start();
if ($_SESSION["auth"] != "yes") {
    die("Unauthorized");
}

// Release session lock after authentication check
session_write_close();

$Start = $_REQUEST['Start'];
$End = $_REQUEST['End'];

$newStart = strtotime($Start);
$newEnd = strtotime($End);


$query = "SELECT 
    resourceEditLog.UserName, 
    CONCAT(Volunteers.firstName, ' ', Volunteers.lastName) as Name,
    DATE(actionDate) AS Date,
    ACTION,
    resourceIDNUM 
FROM resourceEditLog 
    INNER JOIN volunteers ON (resourceEditLog.UserName = Volunteers.UserName)
    INNER JOIN resource ON (resourceEditLog.resourceIDNUM = resource.IDNUM) 
WHERE DATE(resourceEditLog.ActionDate) = resource.EDATE  
    AND ActionDate >= :start_date
    AND ActionDate <= :end_date
ORDER BY Name, actionDate, Action";

$params = [
    ':start_date' => $Start,
    ':end_date' => $End
];

$result = dataQuery($query, $params);

$Data = array();

if ($result) {
    foreach ($result as $row) {
        $Record = array(
            'UserName' => $row->UserName,
            'Name' => $row->Name,
            'Date' => strtotime($row->Date),
            'Action' => $row->ACTION,
            'ResourceIDNUM' => $row->resourceIDNUM,
            'RecordCount' => 1
        );

        $idnum = $Record['ResourceIDNUM'];
        if (isset($Data[$idnum])) {
            if ($Data[$idnum]['Action'] !== "New Record") {
                $Data[$idnum] = $Record;
            }
        } else {
            $Data[$idnum] = $Record;
        }
    }
}

?>

<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
  <head>
    <meta http-equiv="content-type" content="text/html;charset=utf-8" />
    <meta HTTP-EQUIV="CACHE-CONTROL" CONTENT="NO-CACHE">
    <style>
        body    {
            width:  1000px;
            font-family: Arial, Helvetica, sans-serif;  
        }
        
        table {
            border-collapse: collapse;
            font-size:           12pt;
            margin-left:         auto; 
            margin-right:        auto;
        }

        .tableHeader {
            border-bottom:       1px solid black;
        }

        td.grands {
            font-size:          110%;
        }

        .NameRow {
            height:             5em;
            vertical-align:     bottom;
            text-align:         left;
            padding-top:        20px;
            white-space: pre-line;
        }
            
        .totals {
            font-weight: bold;
            border:     1px solid black;
        }

        .date {
            width:      180px;
        }
    
        div {
            border: 5px solid black;
            margin: 10px;
        }
        
        td {
            min-width:  90px;
            width:      auto;
            text-align: center;
            height:     1em;
        }
        
        h1, h2, h3 {
            width:      100%;
            text-align: center;
            height:     1em;
        }
        
        @media print {
            table {
                page-break-inside:   auto;
                page-break-after:    always;
                font-size:           12pt;
                margin-left:         auto; 
                margin-right:        auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after:  auto;
            }
            thead {
                display:    table-header-group;
                font-weight: bold;
            }
            tfoot {
                display:    table-footer-group;
            }   
        }
    </style>
  </head>
<body>  

<?php
    $displayData = array();
    $User = "";
    $Date = "";
    $Names = array();
    $NameTotals = array();
    $DateTotals = array();
    
    $GrandTotals['New'] = 0;
    $GrandTotals['Change'] = 0;
    $GrandTotals['Close'] = 0;
    $GrandTotals['Pending'] = 0;
    $GrandTotals['Approved'] = 0;
    $GrandTotals['Total'] = 0;

    foreach ($Data as $Record) {
        $User = $Record['UserName'];
        $Names[$User] = $Record['Name'];
        $Date = $Record['Date'];
        $Name = $Record['Name'];
        
        if(!isset($DateTotals[$Date])) {
            $DateTotals[$Date]['New'] = 0;
            $DateTotals[$Date]['Change'] = 0;
            $DateTotals[$Date]['Close'] = 0;
            $DateTotals[$Date]['Pending'] = 0;
            $DateTotals[$Date]['Approved'] = 0;
            $DateTotals[$Date]['Total'] = 0;
        }

        if(!isset($displayData[$User])) {
            $displayData[$User] = array();
            $displayData[$User][$Date]['New'] = 0;
            $displayData[$User][$Date]['Change'] = 0;
            $displayData[$User][$Date]['Close'] = 0;
            $displayData[$User][$Date]['Pending'] = 0;
            $displayData[$User][$Date]['Approved'] = 0;
            $displayData[$User][$Date]['Total'] = 0;

            $NameTotals[$User]['New'] = 0;
            $NameTotals[$User]['Change'] = 0;
            $NameTotals[$User]['Close'] = 0;
            $NameTotals[$User]['Pending'] = 0;
            $NameTotals[$User]['Approved'] = 0;
            $NameTotals[$User]['Total'] = 0;

        } elseif(!isset($displayData[$User][$Date])) {
            $displayData[$User][$Date]['New'] = 0;
            $displayData[$User][$Date]['Change'] = 0;
            $displayData[$User][$Date]['Close'] = 0;
            $displayData[$User][$Date]['Pending'] = 0;
            $displayData[$User][$Date]['Approved'] = 0;
            $displayData[$User][$Date]['Total'] = 0;
        }

        switch($Record['Action']) {
            case 'New Record':
                $displayData[$User][$Date]['New'] += $Record['RecordCount'];
                $NameTotals[$User]['New'] += $Record['RecordCount'];
                $DateTotals[$Date]['New'] += $Record['RecordCount'];
                $GrandTotals['New'] += $Record['RecordCount'];
                break;
            
            case 'Update':            
                $displayData[$User][$Date]['Change'] += $Record['RecordCount'];
                $NameTotals[$User]['Change'] += $Record['RecordCount'];
                $DateTotals[$Date]['Change'] += $Record['RecordCount'];
                $GrandTotals['Change'] += $Record['RecordCount'];
                break;           
                        
            case 'Closed':
                $displayData[$User][$Date]['Close'] += $Record['RecordCount'];
                $NameTotals[$User]['Close'] += $Record['RecordCount'];
                $DateTotals[$Date]['Close'] += $Record['RecordCount'];
                $GrandTotals['Close'] += $Record['RecordCount'];
                break;
            
            case 'Pre-Update':
                $displayData[$User][$Date]['Pending'] += $Record['RecordCount'];
                $NameTotals[$User]['Pending'] += $Record['RecordCount'];
                $DateTotals[$Date]['Pending'] += $Record['RecordCount'];
                $GrandTotals['Pending'] += $Record['RecordCount'];
                break;           
            
            case 'Approved':
                $displayData[$User][$Date]['Approved'] += $Record['RecordCount'];
                $NameTotals[$User]['Approved'] += $Record['RecordCount'];
                $DateTotals[$Date]['Approved'] += $Record['RecordCount'];
                $GrandTotals['Approved'] += $Record['RecordCount'];
                break;
            
            Default:
                break;
        }
        $displayData[$User][$Date]['Total'] += $Record['RecordCount'];
        $NameTotals[$User]['Total'] += $Record['RecordCount'];
        $DateTotals[$Date]['Total'] += $Record['RecordCount'];
        $GrandTotals['Total'] += $Record['RecordCount'];
    }

    echo "<h1>RESOURCE UPDATES</h1>";
    echo "<h3>".date('F d, Y', $newStart)." to ".date('F d, Y', $newEnd)."</h3><br><br><br>";
    
    echo "<table>";
    echo "<tr><th></th><th class='tableHeader'>New</th><th class='tableHeader'>Change</th><th class='tableHeader'>Close</th><th class='tableHeader'>Pending</th><th class='tableHeader'>Approved</th><th class='tableHeader'>Total</th></tr>";  
    
    ksort($displayData);
    foreach($displayData as $User => $Day) { 
        if(gettype($User) == "array") {
            ksort($User);
        }
        echo "<tr class='NameRow'><th colspan='7'><strong>".$Names[$User].": </strong>\n\n</th></tr></thead>";
        ksort($Day);
        foreach($Day as $Date => $Values) {
            echo "<tr>";
            echo "<td class='date'>".date("D. M. d, Y", $Date)."</td>";
            echo "<td>".$Values['New']."</td>";
            echo "<td>".$Values['Change']."</td>";
            echo "<td>".$Values['Close']."</td>";
            echo "<td>".$Values['Pending']."</td>";
            echo "<td>".$Values['Approved']."</td>";
            echo "<td>".$Values['Total']."</td>";
            echo "</tr>";
        }   
        echo "<tr class='totals'>";
        echo "<td>TOTAL</td>";
        echo "<td>".$NameTotals[$User]['New']."</td>";
        echo "<td>".$NameTotals[$User]['Change']."</td>";
        echo "<td>".$NameTotals[$User]['Close']."</td>";
        echo "<td>".$NameTotals[$User]['Pending']."</td>";
        echo "<td>".$NameTotals[$User]['Approved']."</td>";
        echo "<td>".$NameTotals[$User]['Total']."</td>";
        echo "</tr>";
            
    }
    echo "<tr class='NameRow grands'><th colspan='7'><strong>\n\n\n\n\nGRAND TOTAL:</strong>\n\n</th></tr>";
    ksort($DateTotals);
    foreach($DateTotals as $Date => $Values) {  
        echo "<tr>";
        echo "<td class='date grands'>".date("D. M. d, Y", $Date)."</td>";
        echo "<td class='grands'>".$Values['New']."</td>";
        echo "<td class='grands'>".$Values['Change']."</td>";
        echo "<td class='grands'>".$Values['Close']."</td>";
        echo "<td class='grands'>".$Values['Pending']."</td>";
        echo "<td class='grands'>".$Values['Approved']."</td>";
        echo "<td class='grands'>".$Values['Total']."</td>";
        echo "</tr>";
    }

    echo "<tr class='totals'>";
    echo "<td class='grands'>TOTAL</td>";
    echo "<td class='grands'>".$GrandTotals['New']."</td>";
    echo "<td class='grands'>".$GrandTotals['Change']."</td>";
    echo "<td class='grands'>".$GrandTotals['Close']."</td>";
    echo "<td class='grands'>".$GrandTotals['Pending']."</td>";
    echo "<td class='grands'>".$GrandTotals['Approved']."</td>";
    echo "<td class='grands'>".$GrandTotals['Total']."</td>";
    echo "</tr>";

    echo "</table>";
?>  
<script>
//  window.print();
</script>
</body>
</html>
