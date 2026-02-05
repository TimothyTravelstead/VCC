<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Get request parameters and set defaults
$FromDate = $_REQUEST["From"];
$ToDate = $_REQUEST["To"];
$Place = "";
$ZipCode = "Last Updated Between";
$Range = "";
$Category = "All";

// Check if this is an admin search (must have both the flag AND be an admin user)
// AdminUser is for pure admins, ResourceAdmin is a different type
$isAdminSearch = isset($_REQUEST['isAdminSearch']) && 
                 $_REQUEST['isAdminSearch'] === 'true' && 
                 isset($_SESSION['AdminUser']) && 
                 $_SESSION['AdminUser'] === 'true';

// Set the closed filter based on admin status
$closedFilter = $isAdminSearch ? "" : " AND Closed = 'N'";

// Build WHERE clause with parameter binding
$WhereString = "WHERE edate BETWEEN :from_date AND :to_date" . $closedFilter;
$params = [
    ':from_date' => $FromDate,
    ':to_date' => $ToDate
];

// Main query for resource data
$query = "SELECT 
    TRIM(idnum) as idnum,
    CONCAT(TRIM(name), ' ', TRIM(name2)) as name,
    TRIM(address1) as address1,
    TRIM(address2) as address2,
    TRIM(city) as city,
    state,
    TRIM(linkableZip) as zip,
    TRIM(type1) as type1,
    TRIM(type2) as type2,
    TRIM(type3) as type3,
    TRIM(type4) as type4,
    'N/A' as Distance,
    closed
FROM resource 
{$WhereString} 
ORDER BY edate, idnum 
LIMIT 0, 500";

// Count query
$query2 = "SELECT COUNT(idnum) as total FROM resource {$WhereString}";

// Execute queries using dataQuery
$results = dataQuery($query, $params);
$countResult = dataQuery($query2, $params);

// Get total count
$totalResponsive = $countResult ? $countResult[0]->total : 0;

// Store search parameters in session
$_SESSION['SearchType'] = 'DateRange';
$_SESSION['Place'] = " ";
$_SESSION['initialQuery'] = $query;
$_SESSION['recentQuery'] = $WhereString;
$_SESSION['SearchCity'] = " ";
$_SESSION['Searchstate'] = " ";
$_SESSION['SearchZip'] = "Last Updated Between ";
$_SESSION['SearchRange'] = $Range;
$_SESSION['Latitude'] = 0;
$_SESSION['Longitude'] = 0;
$_SESSION['SearchFromDate'] = $FromDate;
$_SESSION['SearchToDate'] = $ToDate;

// Release session lock to prevent blocking concurrent requests
session_write_close();

// Set headers for XML response
header("Content-Type: text/xml");
header("Expires: Sun, 19 Nov 1978 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// Begin XML output
echo '<?xml version="1.0" encoding="utf-8"?>';
echo '<responses>';

// Output search metadata
echo '<search>';
echo '<place>' . htmlspecialchars($Place) . ' </place>';
echo '<zipcode>' . htmlspecialchars($ZipCode) . ' </zipcode>';
echo '<range>' . htmlspecialchars($Range) . ' </range>';
echo '<category>' . htmlspecialchars($Category) . ' </category>';
echo '<totalResponsive>' . htmlspecialchars($totalResponsive) . ' </totalResponsive>';
echo '</search>';

// Output resource items
if ($results) {
    foreach ($results as $row) {
        echo '<item>';
        echo '<idnum>' . htmlspecialchars($row->idnum) . '</idnum>';
        echo '<name><![CDATA[' . utf8_encode($row->name) . ']]></name>';
        echo '<type1><![CDATA[' . $row->type1 . '- ]]> </type1>';
        echo '<type2><![CDATA[' . $row->type2 . '- ]]> </type2>';
        echo '<type3><![CDATA[' . $row->type3 . '- ]]> </type3>';
        echo '<type4><![CDATA[' . $row->type4 . '- ]]> </type4>';
        echo '<location><![CDATA[' . $row->city . ', ' . $row->state . ']]></location>';
        echo '<zip><![CDATA[' . $row->zip . ']]></zip>';
        echo '<distance>' . htmlspecialchars($row->Distance) . '</distance>';
        echo '<closed>' . htmlspecialchars($row->closed) . '</closed>';
        echo '</item>';
    }
}

echo '</responses>';
?>
