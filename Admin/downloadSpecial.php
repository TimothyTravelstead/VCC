<?php

// Query with field length validation calculations

require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$query = "SELECT 
    IDNum,
    edate,  
    name, 
    length(name) as name_length, 
    name2, 
    length(name2) as name2_length, 
    address1, 
    length(address1) as address1_length, 
    address2, 
    length(address2) as address2_length, 
    city, 
    length(city) as city_length, 
    state, 
    zip, 
    contact, 
    length(contact) as contact_length, 
    IF(length(name) >= 50, 'Yes', ' ') as name_maxed,
    IF(length(name2) >= 50, 'Yes', ' ') as name2_maxed,
    IF(length(address1) >= 35, 'Yes', ' ') as address1_maxed,
    IF(length(address2) >= 35, 'Yes', ' ') as address2_maxed,
    IF(length(city) >= 25, 'Yes', ' ') as city_maxed,
    IF(length(Contact) >= 25, 'Yes', ' ') as contact_maxed
FROM resource 
WHERE (length(name) >= 50 
    OR length(name2) >= 50 
    OR length(contact) >= 25 
    OR length(address1) >= 35 
    OR length(address2) >= 35 
    OR length(city) >= 25) 
    AND edate BETWEEN '2011-06-01' AND '2011-11-01' 
    AND Closed = 'N'";

$result = dataQuery($query);

// Set headers for CSV download
header("Content-Type: text/csv;filename=Resource_Length_Validation.csv");
header("Content-Disposition: attachment; filename=Resource_Length_Validation.csv"); 

// Define CSV headers
$headers = [
    "ID Number", "Last Update", "Name", "Name Length", "Name2", "Name2 Length",
    "Address1", "Address1 Length", "Address2", "Address2 Length", "City",
    "City Length", "State", "Zip", "Contact", "Contact Length", "Name Maxed",
    "Name2 Maxed", "Address1 Maxed", "Address2 Maxed", "City Maxed", "Contact Maxed"
];

// Output CSV header
echo '"' . implode('","', $headers) . '"' . "\r\n";

// Process and output results
if ($result) {
    foreach ($result as $row) {
        $values = [];
        foreach ((array)$row as $value) {
            $values[] = str_replace('"', '""', $value);
        }
        echo '"' . implode('","', $values) . '"' . "\r\n";
    }
}

// No need to explicitly close connection as PDO handles this automatically
?>
