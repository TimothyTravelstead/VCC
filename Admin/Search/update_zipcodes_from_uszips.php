<?php
/**
 * Script to update ZipCodesPostal table with more accurate lat/lon data from uszips table
 * and recalculate x, y, z coordinates
 */

$_SERVER['HTTP_HOST'] = 'localhost';
require_once('../../../private_html/db_login.php');

echo "=== Updating ZipCodesPostal with USZips Data ===" . PHP_EOL;
echo "Starting at: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// First, check if uszips table exists
$checkTable = "SHOW TABLES LIKE 'uszips'";
$tableExists = dataQuery($checkTable);

if (!$tableExists) {
    echo "ERROR: uszips table does not exist. Please import uszips.sql first." . PHP_EOL;
    echo "Run: mysql -u dgqtkqjasj -p'CVO4t-vkC4' dgqtkqjasj < /home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html/uszips.sql" . PHP_EOL;
    exit;
}

// Get statistics before update
$statsQuery = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN x IS NOT NULL AND y IS NOT NULL AND z IS NOT NULL THEN 1 END) as with_xyz
    FROM ZipCodesPostal";
$statsBefore = dataQuery($statsQuery);
echo "Before Update:" . PHP_EOL;
echo "  Total ZipCodes: " . $statsBefore[0]->total . PHP_EOL;
echo "  With X,Y,Z coordinates: " . $statsBefore[0]->with_xyz . PHP_EOL . PHP_EOL;

// Find matching zip codes between tables
$matchQuery = "SELECT 
    z.Zip,
    z.latitude as old_lat,
    z.longitude as old_lon,
    z.x as old_x,
    z.y as old_y,
    z.z as old_z,
    u.lat as new_lat,
    u.lng as new_lon,
    u.city,
    u.state_id
FROM ZipCodesPostal z
INNER JOIN uszips u ON z.Zip = u.zip
WHERE u.lat IS NOT NULL AND u.lng IS NOT NULL";

$matches = dataQuery($matchQuery);

if (!$matches) {
    echo "No matching zip codes found between tables." . PHP_EOL;
    exit;
}

$totalMatches = count($matches);
echo "Found {$totalMatches} matching zip codes to update." . PHP_EOL . PHP_EOL;

$updated = 0;
$skipped = 0;
$failed = 0;
$batchSize = 100;
$batch = [];

echo "Processing updates..." . PHP_EOL;

foreach ($matches as $index => $match) {
    // Convert new latitude and longitude to float
    $newLat = floatval($match->new_lat);
    $newLon = floatval($match->new_lon);
    
    // Check if update is needed (lat/lon changed)
    $oldLat = floatval($match->old_lat);
    $oldLon = floatval($match->old_lon);
    
    if (abs($oldLat - $newLat) < 0.00001 && abs($oldLon - $newLon) < 0.00001) {
        $skipped++;
        continue;
    }
    
    // Calculate new x, y, z coordinates
    $latRad = deg2rad($newLat);
    $lonRad = deg2rad($newLon);
    
    $x = cos($latRad) * cos($lonRad);
    $y = cos($latRad) * sin($lonRad);
    $z = sin($latRad);
    
    // Add to batch
    $batch[] = [
        'zip' => $match->Zip,
        'lat' => $newLat,
        'lon' => $newLon,
        'x' => $x,
        'y' => $y,
        'z' => $z
    ];
    
    // Process batch when it reaches the size limit or at the end
    if (count($batch) >= $batchSize || $index === $totalMatches - 1) {
        $updateCount = processBatch($batch);
        $updated += $updateCount;
        
        // Progress indicator
        $progress = round(($index + 1) / $totalMatches * 100, 1);
        echo "\rProgress: {$progress}% ({$updated} updated, {$skipped} skipped)";
        
        $batch = [];
    }
}

echo PHP_EOL . PHP_EOL;

/**
 * Process a batch of updates
 */
function processBatch($batch) {
    if (empty($batch)) {
        return 0;
    }
    
    $updated = 0;
    foreach ($batch as $item) {
        $updateQuery = "UPDATE ZipCodesPostal 
                        SET latitude = ?, 
                            longitude = ?,
                            x = ?,
                            y = ?,
                            z = ?
                        WHERE Zip = ?";
        
        $params = [
            $item['lat'],
            $item['lon'],
            $item['x'],
            $item['y'],
            $item['z'],
            $item['zip']
        ];
        
        $result = dataQuery($updateQuery, $params);
        if ($result !== false) {
            $updated++;
        }
    }
    
    return $updated;
}

// Get statistics after update
$statsAfter = dataQuery($statsQuery);

echo "=== Summary ===" . PHP_EOL;
echo "Total zip codes processed: {$totalMatches}" . PHP_EOL;
echo "Successfully updated: {$updated}" . PHP_EOL;
echo "Skipped (no change needed): {$skipped}" . PHP_EOL;
echo "Failed updates: " . ($totalMatches - $updated - $skipped) . PHP_EOL . PHP_EOL;

echo "After Update:" . PHP_EOL;
echo "  Total ZipCodes: " . $statsAfter[0]->total . PHP_EOL;
echo "  With X,Y,Z coordinates: " . $statsAfter[0]->with_xyz . PHP_EOL . PHP_EOL;

// Verify a few sample updates
echo "=== Sample Updated Records ===" . PHP_EOL;
$sampleQuery = "SELECT z.Zip, z.latitude, z.longitude, z.x, z.y, z.z, u.city, u.state_id
                FROM ZipCodesPostal z
                INNER JOIN uszips u ON z.Zip = u.zip
                WHERE z.x IS NOT NULL
                ORDER BY RAND()
                LIMIT 5";

$samples = dataQuery($sampleQuery);
if ($samples) {
    foreach ($samples as $sample) {
        echo "Zip: {$sample->Zip} ({$sample->city}, {$sample->state_id})" . PHP_EOL;
        echo "  Lat: {$sample->latitude}, Lon: {$sample->longitude}" . PHP_EOL;
        echo "  X: {$sample->x}, Y: {$sample->y}, Z: {$sample->z}" . PHP_EOL;
    }
}

echo PHP_EOL . "Completed at: " . date('Y-m-d H:i:s') . PHP_EOL;
?>