<?php
/**
 * Script to recalculate x, y, z coordinates from latitude/longitude
 * for resources that have lat/lon but missing x, y, z values
 */

$_SERVER['HTTP_HOST'] = 'localhost';
require_once('../../../private_html/db_login.php');

echo "=== Recalculating X,Y,Z Coordinates from Latitude/Longitude ===" . PHP_EOL;
echo "Starting at: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// First, find all resources with lat/lon but missing x,y,z
$query = "SELECT idnum, latitude, longitude, name, city, state 
          FROM resource 
          WHERE latitude IS NOT NULL 
            AND longitude IS NOT NULL 
            AND (x IS NULL OR y IS NULL OR z IS NULL)";

$resources = dataQuery($query);

if (!$resources) {
    echo "No resources found with latitude/longitude but missing x,y,z coordinates." . PHP_EOL;
    exit;
}

$totalCount = count($resources);
echo "Found {$totalCount} resources to update." . PHP_EOL . PHP_EOL;

$updated = 0;
$failed = 0;

foreach ($resources as $resource) {
    // Convert latitude and longitude to radians
    $latRad = deg2rad($resource->latitude);
    $lonRad = deg2rad($resource->longitude);
    
    // Calculate x, y, z coordinates using the same formula as ZipLocateTable.php
    $x = cos($latRad) * cos($lonRad);
    $y = cos($latRad) * sin($lonRad);
    $z = sin($latRad);
    
    // Update the resource with calculated coordinates
    $updateQuery = "UPDATE resource 
                    SET x = ?, y = ?, z = ? 
                    WHERE idnum = ?";
    
    $updateParams = [$x, $y, $z, $resource->idnum];
    
    $result = dataQuery($updateQuery, $updateParams);
    
    if ($result !== false) {
        $updated++;
        echo "Updated: {$resource->name} ({$resource->city}, {$resource->state})" . PHP_EOL;
        echo "  ID: {$resource->idnum}" . PHP_EOL;
        echo "  Lat: {$resource->latitude}, Lon: {$resource->longitude}" . PHP_EOL;
        echo "  X: {$x}, Y: {$y}, Z: {$z}" . PHP_EOL . PHP_EOL;
    } else {
        $failed++;
        echo "FAILED to update: {$resource->name} (ID: {$resource->idnum})" . PHP_EOL;
    }
}

echo "=== Summary ===" . PHP_EOL;
echo "Total resources processed: {$totalCount}" . PHP_EOL;
echo "Successfully updated: {$updated}" . PHP_EOL;
echo "Failed updates: {$failed}" . PHP_EOL;
echo "Completed at: " . date('Y-m-d H:i:s') . PHP_EOL;

// Verify the updates
echo PHP_EOL . "=== Verification ===" . PHP_EOL;
$verifyQuery = "SELECT COUNT(*) as remaining FROM resource 
                WHERE latitude IS NOT NULL 
                  AND longitude IS NOT NULL 
                  AND (x IS NULL OR y IS NULL OR z IS NULL)";
$verifyResult = dataQuery($verifyQuery);
if ($verifyResult) {
    echo "Resources still missing x,y,z coordinates: " . $verifyResult[0]->remaining . PHP_EOL;
}

$totalWithCoords = "SELECT COUNT(*) as total FROM resource 
                    WHERE x IS NOT NULL AND y IS NOT NULL AND z IS NOT NULL";
$totalResult = dataQuery($totalWithCoords);
if ($totalResult) {
    echo "Total resources with x,y,z coordinates: " . $totalResult[0]->total . PHP_EOL;
}
?>