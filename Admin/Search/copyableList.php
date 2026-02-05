<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../../private_html/db_login.php');

session_start();

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Get search parameters
$Category = $_REQUEST["Category"]; // We'll use parameter binding instead of mysqli_real_escape_string
$initialQuery = $_SESSION["initialQuery"];
$recentQuery = $_SESSION["recentQuery"];
$Longitude = $_SESSION["Longitude"];
$Latitude = $_SESSION["Latitude"];
$ZipCode = $_SESSION['SearchZip'];
$Range = $_SESSION['SearchRange'];
$SearchType = $_SESSION['SearchType'];
$Place = $_SESSION['Place'];

// Release session lock immediately after reading session data
// This prevents blocking other concurrent requests from the same user
session_write_close();

// Build distance calculation
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

// Set order based on search type
$Order = ($SearchType != "ZipCode") ? 
    "Name, City, State, Zip, Address1" : 
    "Distance, city, state, Zip, address1";

// Build WHERE clause
$WhereString = $recentQuery;
$params = [
    ':longitude' => $Longitude,
    ':latitude' => $Latitude
];

if ($Category != "All") {
    if (strpos($Category, "-") !== false) {
        $WhereString .= " AND (type1 = :category OR type2 = :category OR type3 = :category OR type4 = :category)";
    } else {
        $WhereString .= " AND (type1 LIKE :category_like OR type2 LIKE :category_like OR type3 LIKE :category_like OR type4 LIKE :category_like)";
        $params[':category_like'] = $Category . '%';
    }
    $params[':category'] = $Category;
}

// Build and execute main query
$query = "SELECT 
    TRIM(idnum) as idnum,
    CONCAT(TRIM(name), ' ', TRIM(name2)) as name,
    IF(GIVE_ADDR = 'N', TRIM(address1), ' ') as address1,
    IF(GIVE_ADDR = 'N', TRIM(address2), ' ') as address2,
    TRIM(city) as city,
    state,
    TRIM(linkableZip) as zip,
    TRIM(type1) as type1,
    TRIM(type2) as type2,
    TRIM(type3) as type3,
    TRIM(type4) as type4,
    {$Distance} as Distance,
    phone,
    fax,
    IF(SEMAIL = 'Y', ' ', TRIM(internet)) as email,
    wwweb as web,
    descript
FROM resource 
{$WhereString} 
ORDER BY {$Order} 
LIMIT 0, 500";

$results = dataQuery($query, $params);

// Start HTML output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Resources</title>
    <meta charset='utf-8'>
</head>
<body>";

if ($results) {
    foreach ($results as $row) {
        // Format distance display
        $distanceText = $row->Distance;
        if ($Order == "Name") {
            $distanceText = "N/A";
        } else {
            $distanceText = round($row->Distance);
        }
        
        // Output resource information with proper HTML escaping
        echo "<u>" . htmlspecialchars($distanceText);
        echo ($distanceText == 1) ? " Mile</u><br />" : " Miles</u><br />";
        
        echo htmlspecialchars($row->name) . "<br />";
        
        if ($row->address1) {
            echo htmlspecialchars($row->address1) . "<br />";
        }
        if ($row->address2) {
            echo htmlspecialchars($row->address2) . "<br />";
        }
        
        echo htmlspecialchars($row->city) . ", ";
        echo htmlspecialchars($row->state) . "  ";
        echo htmlspecialchars($row->zip) . "<br />";
        echo "Phone: " . htmlspecialchars($row->phone) . "<br />";
        echo "Fax: &nbsp;&nbsp;&nbsp;&nbsp;" . htmlspecialchars($row->fax) . "<br />";
        echo "Email: " . htmlspecialchars($row->email) . "<br />";
        echo "Website: " . htmlspecialchars($row->web) . "<br />";
        echo "Categories:&nbsp;&nbsp;" . htmlspecialchars($row->type1) . "<br />";
        
        // Output additional categories if they exist
        if ($row->type2) {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" 
                . htmlspecialchars($row->type2) . "<br />";
        }
        if ($row->type3) {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" 
                . htmlspecialchars($row->type3) . "<br />";
        }
        if ($row->type4) {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" 
                . htmlspecialchars($row->type4) . "<br />";
        }
        
        echo htmlspecialchars($row->descript) . "<br /><br />";
    }
}

echo "</body></html>";
?>
