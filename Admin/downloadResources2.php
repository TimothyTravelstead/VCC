<?php

// Complex query with subqueries for resource data

require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$query = "SELECT 
    idnum,
    edate,
    name,
    name2,
    address1,
    address2,
    city,
    state,
    linkableZip,
    closed,
    type1,
    type2,
    type3,
    type4,
    type5,
    type6,
    type7,
    type8,
    phone,
    Fax,
    hotline,
    internet,
    mailpage,
    wwweb,
    wwweb2,
    wwweb3,
    nonlgbt,
    (SELECT Volunteers.lastName 
     FROM Volunteers 
     JOIN resourceEditLog ON (Volunteers.UserName = resourceEditLog.UserName) 
     WHERE resourceEditLog.UserName = Volunteers.UserName 
     AND resource.IDNUM = resourceEditLog.resourceIDNUM 
     ORDER BY actionDate DESC 
     LIMIT 1) as UpdateName,
    (SELECT action 
     FROM resourceEditLog 
     WHERE resourceEditLog.resourceIDNUM = resource.IDNUM 
     ORDER BY actionDate DESC 
     LIMIT 1) as UpdateType 
FROM resource 
ORDER BY edate DESC";

$result = dataQuery($query);

// Set headers for CSV download
header("Content-Type: text/csv;filename=Resources.csv");
header("Content-Disposition: attachment; filename=Resources.csv"); 

// Define CSV headers
$headers = [
    "IDNUM", "EDATE", "DATE", "NAME2", "ADDRESS1", "ADDRESS2", "CITY", "STATE",
    "ZIP", "CLOSED", "TYPE1", "TYPE2", "TYPE3", "TYPE4", "TYPE5", "TYPE6",
    "TYPE7", "TYPE8", "PHONE", "FAX", "HOTLINE", "INTERNET", "MAILPAGE",
    "WWWEB", "WWWEB2", "WWWEB3", "NonLGBT", "Updater", "Action"
];

// Output CSV header
echo '"' . implode('","', $headers) . '"' . "\r\n";

// Process and output results
if ($result) {
    foreach ($result as $row) {
        $values = array_values((array)$row); // Convert object to array
        
        // Output each value with special handling for ZIP code (index 8)
        for ($i = 0; $i < count($values); $i++) {
            if ($i > 0) {
                echo ',';
            }
            
            // Special handling for ZIP code column
            if ($i === 8) {
                echo '="' . $values[$i] . '"';
            } else {
                echo '"' . str_replace('"', '""', $values[$i]) . '"';
            }
        }
        echo "\r\n";
    }
}

// No need to explicitly close connection as PDO handles this automatically
?>
