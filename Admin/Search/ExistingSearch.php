<?php


// Get search parameters from request and session

require_once('../../../private_html/db_login.php');
session_start();
$Category = $_REQUEST["Category"];
$initialQuery = $_SESSION["initialQuery"];
$recentQuery = $_SESSION["recentQuery"];
$Longitude = $_SESSION["Longitude"];
$Latitude = $_SESSION["Latitude"];
$ZipCode = $_SESSION['SearchZip'];
$Range = $_SESSION['SearchRange'];
$SearchType = $_SESSION['SearchType'];
$Place = $_SESSION['Place'];
$ToDate = $_SESSION['SearchToDate'];
$FromDate = $_SESSION['SearchFromDate'];

// Calculate distance based on search type
$Distance = ($_SESSION['SearchType'] != 'ZipCode') ? 
    "'N/A'" : 
    "round(
        acos(
            round(
                x * Cos(Radians(:longitude)) * Cos(Radians(:latitude))
                + y * Sin(Radians(:longitude)) * Cos(Radians(:latitude))
                + z * Sin(Radians(:latitude))
            , 15)
        ) * 180 * 60 / Pi() * 1852000 / 1609344
    , 0)";

// Determine order based on search type
if ($SearchType != "ZipCode" && $SearchType != "DateRange") {
    $Order = "Name, City, State, Zip, Address1";
    $Range = " ";
} else if ($SearchType != "DateRange") {
    $Order = "Distance, City, State, Zip, Address1";
} else {
    $Order = "edate, idnum";
}

// Start with base WHERE clause
$WhereString = $recentQuery;
$params = [
    ':longitude' => $Longitude,
    ':latitude' => $Latitude
];

// Add category filtering if needed
if ($Category != "All") {
    if (strpos($Category, "-") !== false) {
        $WhereString .= " AND (type1 = :category OR type2 = :category OR type3 = :category OR type4 = :category)";
        $params[':category'] = $Category;
    } else {
        $WhereString .= " AND (type1 LIKE :category_like OR type2 LIKE :category_like 
                          OR type3 LIKE :category_like OR type4 LIKE :category_like)";
        $params[':category_like'] = $Category . '%';
    }
}

// Build and execute main query
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
    {$Distance} as Distance,
    Closed
FROM resource 
{$WhereString} 
ORDER BY {$Order} 
LIMIT 0, 500";

$results = dataQuery($query, $params);

// Update session
$_SESSION['initialQuery'] = $initialQuery;
$_SESSION['recentQuery'] = $recentQuery;

// Set headers for XML response
header("Content-Type: text/xml");
header("Expires: Sun, 19 Nov 1978 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Begin XML output
echo '<?xml version="1.0" encoding="utf-8"?>';
echo '<responses>';
echo '<search>';
echo '<place>' . htmlspecialchars($Place) . '</place>';
echo '<zipcode>' . htmlspecialchars($ZipCode) . ' </zipcode>';
echo '<range>' . htmlspecialchars($Range) . ' </range>';
echo '<category>' . htmlspecialchars($Category) . '</category>';
echo '</search>';

// Output resource items
if ($results) {
    foreach ($results as $row) {
        echo '<item>';
        echo '<idnum>' . htmlspecialchars($row->idnum) . '</idnum>';
        echo '<name><![CDATA[' . utf8_encode($row->name) . ']]></name>';
        echo '<type1>' . htmlspecialchars($row->type1) . ' </type1>';
        echo '<type2>' . htmlspecialchars($row->type2) . ' - </type2>';
        echo '<type3>' . htmlspecialchars($row->type3) . ' - </type3>';
        echo '<type4>' . htmlspecialchars($row->type4) . ' - </type4>';
        echo '<location><![CDATA[' . $row->city . ', ' . $row->state . ']]></location>';
        echo '<zip>' . htmlspecialchars($row->zip) . '</zip>';
        echo '<distance>' . htmlspecialchars($row->Distance) . '</distance>';
        echo '<closed>' . htmlspecialchars($row->Closed) . '</closed>';
        echo '</item>';
    }
}

echo '</responses>';
?>
