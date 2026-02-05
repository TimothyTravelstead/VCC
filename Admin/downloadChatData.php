<?php

require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

ini_set("memory_limit","1024M");

// Get and sanitize date parameters
$Start = $_REQUEST['Start'] ?? '';
$End = $_REQUEST['End'] ?? '';

// Set headers for CSV download
header("Content-Type: text/csv;filename=ChatHistory.csv");
header("Content-Disposition: attachment; filename=ChatHistory_" . date('Y-m-d') . ".csv"); 

// Query with parameter binding for security (matching working old system exactly)
$query = "SELECT 
    chatStatus.Date,
    chatStatus.chatStatus,
    CallLog.FirstName,
    CallLog.LastName,
    CallLog.Time,
    CallLog.volunteer,
    chatStatus.callerID,
    chatStatus.UniqueCallerID,
    chatStatus.ReferringSite
FROM `chatStatus` 
LEFT JOIN (`CallLog`) ON (CallLog.callsid = chatStatus.callerID)
WHERE chatStatus.Date >= :start_date 
AND chatStatus.Date <= :end_date 
ORDER BY chatStatus.Date, CallLog.Time";

$params = [
    ':start_date' => $Start,
    ':end_date' => $End
];

// Output CSV header
echo "\"Date\",\"Status\",\"Volunteer First Name\",\"Volunteer Last Name\"," .
     "\"Length\",\"Volunteer UserName\",\"ChatID\",\"Chatter Unique ID\"," .
     "\"Referring Site\"\r\n";

try {
    // Calculate date range to determine if chunking is needed
    if (empty($Start) || empty($End)) {
        echo '"Error: Start and End date parameters are required"' . "\r\n";
        exit;
    }
    
    $startDate = new DateTime($Start);
    $endDate = new DateTime($End);
    $daysDiff = $startDate->diff($endDate)->days;
    
    // If more than 365 days (1 year), process in 6-month chunks
    if ($daysDiff > 365) {
        $chunkMonths = 6;
        $currentStart = clone $startDate;
        $totalRows = 0;
        
        while ($currentStart <= $endDate) {
            // Calculate chunk end date (6 months from current start, or end date if sooner)
            $currentEnd = clone $currentStart;
            $currentEnd->add(new DateInterval('P' . $chunkMonths . 'M'));
            if ($currentEnd > $endDate) {
                $currentEnd = $endDate;
            }
            
            // Update query parameters for this chunk
            $chunkParams = [
                ':start_date' => $currentStart->format('Y-m-d'),
                ':end_date' => $currentEnd->format('Y-m-d')
            ];
            
            // Execute query for this chunk
            $chunkResult = dataQuery($query, $chunkParams);
            
            if ($chunkResult && is_array($chunkResult)) {
                foreach ($chunkResult as $row) {
                    // Create array of values in the correct order
                    $values = array(
                        $row->Date ?? '',
                        $row->chatStatus ?? '',
                        $row->FirstName ?? '',
                        $row->LastName ?? '',
                        $row->Time ?? '',
                        $row->volunteer ?? '',
                        $row->callerID ?? '',
                        $row->UniqueCallerID ?? '',
                        $row->ReferringSite ?? ''
                    );
                    
                    // Output each value wrapped in quotes and separated by commas
                    echo implode(',', array_map(function($value) {
                        return '"' . str_replace('"', '""', (string)$value) . '"';
                    }, $values)) . "\r\n";
                    
                    $totalRows++;
                }
            }
            
            // Free chunk result memory
            unset($chunkResult);
            
            // Force garbage collection after each chunk
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            // Move to next chunk
            $currentStart = clone $currentEnd;
            $currentStart->add(new DateInterval('P1D')); // Start next chunk the day after current end
            
            // Flush output to browser
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }
        
        if ($totalRows == 0) {
            echo '"No chat data found for the specified date range"' . "\r\n";
        }
        
    } else {
        // For smaller date ranges (under 1 year), process normally
        $result = dataQuery($query, $params);
        
        if ($result && is_array($result)) {
            foreach ($result as $row) {
                // Create array of values in the correct order
                $values = array(
                    $row->Date ?? '',
                    $row->chatStatus ?? '',
                    $row->FirstName ?? '',
                    $row->LastName ?? '',
                    $row->Time ?? '',
                    $row->volunteer ?? '',
                    $row->callerID ?? '',
                    $row->UniqueCallerID ?? '',
                    $row->ReferringSite ?? ''
                );
                
                // Output each value wrapped in quotes and separated by commas
                echo implode(',', array_map(function($value) {
                    return '"' . str_replace('"', '""', (string)$value) . '"';
                }, $values)) . "\r\n";
            }
        } else {
            echo '"No chat data found for the specified date range"' . "\r\n";
        }
    }
    
} catch (Exception $e) {
    echo '"Error retrieving chat data","' . str_replace('"', '""', $e->getMessage()) . '"' . "\r\n";
}

// No need to explicitly close connection as PDO handles this automatically
?>
