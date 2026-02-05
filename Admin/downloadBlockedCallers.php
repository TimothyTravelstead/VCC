<?php

require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

ini_set("memory_limit","1024M");

// Set headers for CSV download
header("Content-Type: text/csv;filename=BlockedCallList.csv");
header("Content-Disposition: attachment; filename=BlockedCallList.csv"); 

$query = "SELECT 
    DATE(Date) as Date, 
    TIME(Date) as Time, 
    '' as Location,
    COALESCE(Type, '') as Type,
    COALESCE((Select CONCAT(Volunteers.FirstName , ' ' , Volunteers.LastName) 
     FROM Volunteers 
     WHERE Volunteers.UserName = BlockList.UserName), '') as BlockingUser,
    CASE 
        WHEN PhoneNumber LIKE '+1%' AND LENGTH(PhoneNumber) = 12 THEN
            CONCAT('(',SUBSTRING(PhoneNumber,3,3),') ',
                   SUBSTRING(PhoneNumber,6,3), '-' , 
                   SUBSTRING(PhoneNumber,9,4))
        ELSE PhoneNumber
    END as PhoneNumber,
    COALESCE(Message, '') as Message 
FROM BlockList 
ORDER BY Type DESC, Date DESC, BlockingUser, PhoneNumber";

// Output CSV header
echo "\"DATE\",\"TIME\",\"LOCATION\",\"TYPE\",\"VOLUNTEER\",\"PHONE NUMBER\",\"MESSAGE\"\r\n";

try {
    $result = dataQuery($query);

    // Process and output results
    if ($result && is_array($result)) {
        foreach ($result as $row) {
            // Create array of values in the correct order
            $values = array(
                $row->Date ?? '',
                $row->Time ?? '',
                $row->Location ?? '',
                $row->Type ?? '',
                $row->BlockingUser ?? '',
                $row->PhoneNumber ?? '',
                $row->Message ?? ''
            );
            
            // Output each value wrapped in quotes and separated by commas
            echo implode(',', array_map(function($value) {
                return '"' . str_replace('"', '""', (string)$value) . '"';
            }, $values)) . "\r\n";
        }
    } else {
        echo '"No blocked callers found"' . "\r\n";
    }
    
} catch (Exception $e) {
    echo '"Error retrieving blocked callers data","' . str_replace('"', '""', $e->getMessage()) . '"' . "\r\n";
}

// No need to explicitly close connection as PDO handles this automatically
?>
