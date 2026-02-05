<?php

// Get parameters from request

require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$Date = $_REQUEST['Date'];
$LineNumber = $_REQUEST['Line'];

// Prepare and execute query with parameters
$query = "SELECT 
            Desk, 
            GLBTNHC_Program, 
            StartTime, 
            Time 
          FROM CallLog 
          WHERE Date = :date 
          AND Desk = :lineNumber 
          ORDER BY starttime";

$result = dataQuery($query, [
    'date' => $Date,
    'lineNumber' => $LineNumber
]);

// Set XML header
header("Content-Type: text/xml");
echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
echo "<responses>";

// Process results and generate XML
if ($result) {
    foreach ($result as $row) {
        echo "<item>";
        echo    "<line>" . htmlspecialchars($row->Desk) . "</line>";
        echo    "<program>" . htmlspecialchars($row->GLBTNHC_Program) . "</program>";
        echo    "<starttime>" . htmlspecialchars($row->StartTime) . "</starttime>";
        echo    "<length>" . htmlspecialchars($row->Time) . "</length>";
        echo "</item>";
    }
}

echo "</responses>";
?>
