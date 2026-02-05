<?php

require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

ini_set("memory_limit","1024M");

// Get and sanitize parameters
$Start = $_REQUEST['Start'];
$End = $_REQUEST['End'];
$Type = $_REQUEST['Type'];

// Set headers for CSV download
header("Content-Type: text/csv;charset=utf-8");
header("Content-Disposition: attachment; filename=calllog_" . date('Y-m-d') . ".csv"); 

// Define CSV headers based on type
$baseHeaders = [
    "RECORD ID", "DESK", "FIRST NAME", "LAST NAME", "VOLUNTEER", "DATE", 
    "START TIME", "END TIME", "TIME", "GENDER", "AGE", "VOLUNTEER RATING", 
    "INFO", "HOTLINE", "BISEXUAL", "COMMUNITY", "SUPPORT", "SOCIAL", "YOUTH", 
    "BUSINESS", "RELIGION", "HEALTH", "AIDS", "MEDIA", "BOOKSTORE", "CRISIS", 
    "LEGAL", "RECOVERY", "SPORTS", "STUDENT", "BARS", "RESTAURANT", "ALUMNI", 
    "CULTURAL", "COUNSELING", "PROFESSIONAL", "POLITICAL", "FUNDRAISE", "HOTEL", 
    "TERMINATE", "GOT_NUMBER", "NOTE", "AREA", "STATE", "CITY", "ZIP", "COMEOUT", 
    "RELATION", "SUICIDE", "RUNAWAY", "VIOLENCE", "PARENT", "AIDS_HIV", "SELF_EST", 
    "SYSTIME", "OTHER", "GLBTNHC PROGRAM", "TRANSGENDER", "SEXINFO", "TGENDER", 
    "LESBIAN", "END STATUS", "COUNTRY", "NOTES", "CALLSID", "ETHNICITY",
    "SENIOR-HOUSING", "SENIOR-MEALS", "SENIOR-MEDICAL", "SENIOR-LEGAL", 
    "SENIOR-TRANSPORTATION", "SENIOR-SOCIAL", "SENIOR-SUPPORT", "SENIOR-OTHER", 
    "SENIOR-NONE", "INTERNET_GOOGLE", "INTERNET_FACEBOOK", "INTERNET_TWITTER", 
    "INTERNET_INSTAGRAM", "INTERNET_OTHER", "INTERNET_UNKNOWN"
];

if ($Type == "All") {
    $headers = array_merge($baseHeaders, ["PHONE NUMBER", "LOCATION"]);
} else {
    $headers = array_merge($baseHeaders, ["LOCATION"]);
}

// Output CSV header
echo '"' . implode('","', $headers) . '"' . "\r\n";

// Build query with memory-efficient approach
$baseQuery = "SELECT 
    RecordID, Desk, FirstName, LastName, VOLUNTEER, DATE, StartTime, EndTime, 
    TIME, GENDER, AGE, VolunteerRating, INFO, HOTLINE, BISEXUAL, COMMUNITY, 
    SUPPORT, SOCIAL, YOUTH, BUSINESS, RELIGION, HEALTH, AIDS, MEDIA, BOOKSTORE, 
    CRISIS, LEGAL, RECOVERY, SPORTS, STUDENT, BARS, RESTAURANT, ALUMNI, CULTURAL, 
    COUNSELING, PROFESSIONAL, POLITICAL, FUNDRAISE, HOTEL, TERMINATE, GOT_NUMBER, 
    NOTE, AREA, STATE, CITY, Zip, COMEOUT, RELATION, SUICIDE, RUNAWAY, VIOLENCE, 
    PARENT, AIDS_HIV, SELF_EST, SYSTIME, OTHER, GLBTNHC_Program, TRANSGENDER, 
    SEXINFO, TGENDER, LESBIAN, endStatus, callHistoryCountry, 
    TRIM(REPLACE(REPLACE(REPLACE(CallLogNotes, '\n', ' '), '\r', ' '), '\t', ' ')) as CallLogNotes,
    CallSid, Ethnicity, 
    Senior_Housing, Senior_Meals, Senior_Medical, Senior_Legal, Senior_Transportation, 
    Senior_Social, Senior_Support, Senior_None,
    Internet_Google, Internet_Facebook, Internet_Twitter, Internet_Instagram, 
    Internet_Other, Internet_Unknown";

// Add lookups based on type
if ($Type == "All") {
    $baseQuery .= ", (select CallerID from CallerHistory where CallerHistory.CallSid = CallLog.CallSid) as PhoneNumber";
}
$baseQuery .= ", (select Location from CallerHistory where CallerHistory.CallSid = CallLog.CallSid) as Location";
$baseQuery .= " FROM CallLog WHERE Date >= :start_date AND Date <= :end_date ORDER BY RecordID";

$params = [
    ':start_date' => $Start,
    ':end_date' => $End
];

try {
    // Calculate date range to determine if chunking is needed
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
            $chunkResult = dataQuery($baseQuery, $chunkParams);
            
            if ($chunkResult && is_array($chunkResult)) {
                foreach ($chunkResult as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        // Clean the value for CSV output
                        $cleanValue = str_replace(["\r\n", "\r", "\n", '"'], ['', '', '', '""'], (string)$value);
                        $values[] = $cleanValue;
                    }
                    
                    // Output the row
                    echo '"' . implode('","', $values) . '"' . "\r\n";
                    
                    // Free memory after each row
                    unset($values);
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
            echo '"No data found for the specified date range"' . "\r\n";
        }
        
    } else {
        // For smaller date ranges (under 1 year), process normally
        $result = dataQuery($baseQuery, $params);

        if ($result && is_array($result)) {
            foreach ($result as $row) {
                $values = [];
                foreach ($row as $value) {
                    // Clean the value for CSV output
                    $cleanValue = str_replace(["\r\n", "\r", "\n", '"'], ['', '', '', '""'], (string)$value);
                    $values[] = $cleanValue;
                }
                
                // Output the row
                echo '"' . implode('","', $values) . '"' . "\r\n";
                
                // Free memory after each row
                unset($values);
            }
        } else {
            echo '"No data found for the specified date range"' . "\r\n";
        }
    }
    
} catch (Exception $e) {
    echo '"Error retrieving data","' . str_replace('"', '""', $e->getMessage()) . '"' . "\r\n";
}
?>