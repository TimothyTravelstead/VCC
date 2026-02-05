<?php

// Complex query with subqueries for resource data

require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$query = "SELECT
  r.idnum, r.edate, r.name, r.name2, r.address1, r.address2, r.city, r.state,
  r.linkableZip, r.closed, r.type1, r.type2, r.type3, r.type4, r.type5, r.type6,
  r.type7, r.type8, r.phone, r.Fax, r.hotline, r.internet, r.mailpage,
  r.wwweb, r.wwweb2, r.wwweb3, r.nonlgbt,
  u.UpdateName, u.UpdateType
FROM resource AS r
LEFT JOIN (
  SELECT resourceIDNUM,
         CONCAT(v.firstName,' ',v.lastName) AS UpdateName,
         rel.Action AS UpdateType
  FROM (
    SELECT rel.*,
           ROW_NUMBER() OVER (
             PARTITION BY rel.resourceIDNUM
             ORDER BY rel.actionDate DESC, rel.id DESC   -- use your PK in place of rel.id
           ) AS rn
    FROM resourceEditLog rel
    WHERE rel.Action IN ('Update','New Record','Closed')
  ) rel
  JOIN Volunteers v ON v.UserName = rel.UserName
  WHERE rel.rn = 1
) AS u
  ON u.resourceIDNUM = r.IDNUM
ORDER BY r.edate DESC";  
  
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
