<?php

require_once('../../../private_html/db_login.php');
session_start();
$startTime = microtime(true);


$Range = $_REQUEST["Range"];
$ZipCode = $_REQUEST["ZipCode"];

// Get the Coordinates of the Requested Zip Code
if ($ZipCode != "") {
    // Use prepared statement via dataQuery to prevent SQL injection
    $query = "SELECT latitude, longitude, City, State, locationText 
              FROM ZipCodesPostal 
              WHERE Zip = ? AND locationtype = 'PRIMARY' 
              LIMIT 1";
    
    $result = dataQuery($query, [$ZipCode]);
    
    if (!$result) {
        die("INVALID");
    }
    
    // Since we used LIMIT 1, we can safely get the first result
    $locationData = $result[0];
    $Latitude = $locationData->latitude;
    $Longitude = $locationData->longitude;
    $SearchCity = $locationData->City;
    $SearchState = $locationData->State;
    $Place = $locationData->locationText;
    
    // Calculate the Coordinates
    $x = cos(deg2rad($Longitude)) * cos(deg2rad($Latitude));
    $y = sin(deg2rad($Longitude)) * cos(deg2rad($Latitude));
    $z = sin(deg2rad($Latitude));
    
    // Build the distance calculation formula
    $Distance = "ROUND(
        ACOS(
            ROUND(
                x * COS(RADIANS(?)) * COS(RADIANS(?))
                + y * SIN(RADIANS(?)) * COS(RADIANS(?))
                + z * SIN(RADIANS(?))
            , 15)
        ) * 180 * 60 / PI() * 1852000 / 1609344, 0)";
    
    $WhereString = "WHERE " . $Distance . " <= ?";
    
    // Main resource query with distance calculation
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
                closed
              FROM resource
              {$WhereString}
              ORDER BY Distance, city, state, linkableZip, address1
              LIMIT 500";
    
    // Parameters for the distance calculation and range
    $params = [
        $Longitude, $Latitude,  // For first COS/COS
        $Longitude, $Latitude,  // For SIN/COS
        $Latitude,             // For final SIN
        $Range                 // For the WHERE clause
    ];
    
    $resources = dataQuery($query, $params);
    
    // Store search parameters in session
    $_SESSION['SearchType'] = 'ZipCode';
    $_SESSION['Place'] = $Place;
    $_SESSION['initialQuery'] = $query;
    $_SESSION['recentQuery'] = $WhereString;
    $_SESSION['SearchCity'] = isset($County) ? $County . " County" : $SearchCity;
    $_SESSION['Searchstate'] = $SearchState;
    $_SESSION['SearchZip'] = trim($ZipCode);
    $_SESSION['SearchRange'] = trim($Range);
    $_SESSION['Latitude'] = $Latitude;
    $_SESSION['Longitude'] = $Longitude;
    
    // Set response headers
    header("Content-Type: text/xml");
    header("Expires: Sun, 19 Nov 1978 05:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    // Generate XML response
    echo "<?xml version=\"1.0\" encoding=\"utf-8\"?><responses>";
    echo "<search>";
    echo "<place>" . htmlspecialchars($Place) . "</place>";
    echo "<zipcode>" . htmlspecialchars($ZipCode) . "</zipcode>";
    echo "<range>" . htmlspecialchars($Range) . "</range>";
    echo "<category>" . (isset($Category) ? htmlspecialchars($Category) : '') . "</category>";
    echo "</search>";
    
    if ($resources) {
        foreach ($resources as $resource) {
            echo "<item>";
            echo "<idnum>" . htmlspecialchars($resource->idnum) . "</idnum>";
            echo "<name><![CDATA[" . utf8_encode(html_entity_decode($resource->name, ENT_QUOTES | ENT_XML1, 'UTF-8')) . "]]></name>";
            echo "<type1><![CDATA[" . htmlspecialchars($resource->type1) . "- ]]></type1>";
            echo "<type2><![CDATA[" . htmlspecialchars($resource->type2) . "- ]]></type2>";
            echo "<type3><![CDATA[" . htmlspecialchars($resource->type3) . "- ]]></type3>";
            echo "<type4><![CDATA[" . htmlspecialchars($resource->type4) . "- ]]></type4>";
            echo "<location><![CDATA[" . htmlspecialchars($resource->city . ", " . $resource->state) . "]]></location>";
            echo "<zip><![CDATA[" . htmlspecialchars($resource->zip) . "]]></zip>";
            echo "<distance>" . round($resource->Distance) . "</distance>";
            echo "<closed>" . htmlspecialchars($resource->closed) . "</closed>";
            echo "</item>";
        }
    }
	$endTime = microtime(true);
	$executionTime = round(($endTime - $startTime) * 1000, 2); // in milliseconds
	
	echo "<debug>";
	echo "<executionTime>{$executionTime} ms</executionTime>";
	echo "</debug>";    
    
    echo "</responses>";
}
?>
