<?php
// Include database connection FIRST to set session configuration
require_once('../../private_html/db_login.php');

session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately - this file doesn't need session data
session_write_close();

$ListType = $_REQUEST["ListType"];

// Determine order by clause based on list type
if ($ListType == 'Admin') {
    $OrderBy = "t1.Type, 
                length(t1.PhoneNumber), 
                SUBSTRING(PhoneNumber,3,3),
                SUBSTRING(t1.PhoneNumber,6,3),
                SUBSTRING(t1.PhoneNumber,9,4), 
                t1.Date desc";
} else {
    $OrderBy = "t1.Type, 
                t1.Date desc, 
                length(t1.PhoneNumber), 
                SUBSTRING(PhoneNumber,3,3),
                SUBSTRING(t1.PhoneNumber,6,3),
                SUBSTRING(t1.PhoneNumber,9,4)";
}

// Prepare the query with named parameter
$query = "SELECT 
            CONCAT('(',SUBSTRING(t1.PhoneNumber,3,3),') ',
                  SUBSTRING(t1.PhoneNumber,6,3), '-', 
                  SUBSTRING(t1.PhoneNumber,9,4)) as CallerID, 
            DATE(t1.Date) as Date, 
            t1.Type, 
            CONCAT(t2.FirstName, ' ', t2.LastName) as User, 
            t1.Message, 
            t1.InternetNumber 
          FROM BlockList as t1 
          LEFT JOIN volunteers as t2 ON (t1.UserName = t2.UserName) 
          WHERE t1.Type = :listType 
          ORDER BY " . $OrderBy;

// Execute query with parameter
$result = dataQuery($query, ['listType' => $ListType]);

$callHistory = [];

if ($result) {
    foreach ($result as $index => $row) {
        $callHistory[$index] = [
            'callerID' => $row->CallerID,
            'date' => $row->Date,
            'type' => $row->Type,
            'user' => $row->User,
            'message' => $row->Message,
            'internetNumber' => $row->InternetNumber
        ];
    }
}

// Output JSON result
echo json_encode($callHistory);
?>
