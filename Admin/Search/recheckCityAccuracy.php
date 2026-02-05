<?php
/**
 * Re-check CITY Accuracy Records
 *
 * Finds records with GEOACCURACY='CITY' that have street addresses
 * and re-geocodes them to see if they now return ROOFTOP or STREET accuracy.
 *
 * Usage:
 *   php recheckCityAccuracy.php              # Process all matching records
 *   php recheckCityAccuracy.php --dry-run    # Show what would be done without updating
 *   php recheckCityAccuracy.php --limit=100  # Process only 100 records
 */

// Ensure running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once(__DIR__ . '/../../../private_html/db_login.php');
require_once(__DIR__ . '/../../lib/AppleMapsGeocoder.php');

// Parse command line arguments
$options = getopt('', ['limit:', 'dry-run', 'help']);

if (isset($options['help'])) {
    echo "Usage: php recheckCityAccuracy.php [options]\n";
    echo "Options:\n";
    echo "  --limit=N    Process only N records\n";
    echo "  --dry-run    Show what would be done without updating\n";
    echo "  --help       Show this help message\n";
    exit(0);
}

$limit = isset($options['limit']) ? (int)$options['limit'] : null;
$dryRun = isset($options['dry-run']);

$logFile = __DIR__ . '/city_recheck_log_' . date('Ymd_His') . '.txt';

function logMessage($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $message\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

function formatTime($seconds) {
    if ($seconds < 60) return round($seconds) . 's';
    if ($seconds < 3600) return round($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
}

logMessage("=== Re-checking CITY Accuracy Records ===", $logFile);
logMessage("Dry run: " . ($dryRun ? "YES" : "NO"), $logFile);

// Query: CITY accuracy records with non-empty street address (exclude PO Boxes)
$query = "SELECT IDNUM, NAME, ADDRESS1, ADDRESS2, CITY, STATE, ZIP,
                 ROUND(LATITUDE, 6) as OldLat, ROUND(LONGITUDE, 6) as OldLng, GEOACCURACY
          FROM resource
          WHERE CLOSED = 'N'
            AND GEOACCURACY = 'CITY'
            AND ADDRESS1 IS NOT NULL
            AND TRIM(ADDRESS1) != ''
            AND LENGTH(TRIM(ADDRESS1)) > 5
            AND UPPER(ADDRESS1) NOT LIKE '%P.O.%'
            AND UPPER(ADDRESS1) NOT LIKE '%PO BOX%'
            AND UPPER(ADDRESS1) NOT LIKE '%P O BOX%'
          ORDER BY IDNUM";

if ($limit) {
    $query .= " LIMIT $limit";
}

$resources = dataQuery($query);
$toProcess = count($resources);

logMessage("Records to check: $toProcess", $logFile);

if ($toProcess === 0) {
    logMessage("No records to process. Exiting.", $logFile);
    exit(0);
}

$processed = 0;
$upgraded = 0;
$unchanged = 0;
$failed = 0;
$startTime = time();

$geocoder = new AppleMapsGeocoder();

// Track upgrades by new accuracy
$upgradeCounts = ['ROOFTOP' => 0, 'STREET' => 0];

foreach ($resources as $resource) {
    $processed++;
    $idnum = $resource->IDNUM;

    // Build address
    $address = trim(
        $resource->ADDRESS1 . ' ' .
        $resource->ADDRESS2 . ', ' .
        $resource->CITY . ' ' .
        $resource->STATE . ' ' .
        $resource->ZIP
    );

    // Progress
    $percent = round(($processed / $toProcess) * 100, 1);
    $elapsed = time() - $startTime;
    $rate = $processed / max(1, $elapsed);
    $remaining = ($toProcess - $processed) / max(0.1, $rate);

    echo "\r[$percent%] Checking $processed/$toProcess (ID: $idnum) - ETA: " . formatTime($remaining) . "    ";

    // Geocode
    $result = $geocoder->geocode($address, $resource->ZIP);

    if ($result['success']) {
        $newAccuracy = $result['accuracy'];

        // Check if accuracy improved (ROOFTOP or STREET is better than CITY)
        if ($newAccuracy === 'ROOFTOP' || $newAccuracy === 'STREET') {
            $newLat = $result['latitude'];
            $newLng = $result['longitude'];

            if (!$dryRun) {
                // Calculate XYZ coordinates
                $xyz = AppleMapsGeocoder::calculateXYZ($newLat, $newLng);

                // Update database
                $updateQuery = "UPDATE resource
                               SET LATITUDE = :lat,
                                   LONGITUDE = :lng,
                                   GEOACCURACY = :accuracy,
                                   x = :x,
                                   y = :y,
                                   z = :z
                               WHERE IDNUM = :idnum";

                $updateResult = dataQuery($updateQuery, [
                    ':lat' => $newLat,
                    ':lng' => $newLng,
                    ':accuracy' => $newAccuracy,
                    ':x' => $xyz['x'],
                    ':y' => $xyz['y'],
                    ':z' => $xyz['z'],
                    ':idnum' => $idnum
                ]);

                if (is_array($updateResult) && isset($updateResult['error'])) {
                    logMessage("DB ERROR for $idnum: " . $updateResult['message'], $logFile);
                    $failed++;
                    continue;
                }
            }

            $upgraded++;
            $upgradeCounts[$newAccuracy]++;

            // Log the upgrade
            $distance = sqrt(pow($newLat - $resource->OldLat, 2) + pow($newLng - $resource->OldLng, 2)) * 111;
            logMessage("UPGRADED $idnum: CITY → $newAccuracy (moved " . round($distance, 1) . "km) - {$resource->NAME}", $logFile);
        } else {
            $unchanged++;
        }
    } else {
        $failed++;
        if ($processed <= 10 || $processed % 100 === 0) {
            logMessage("FAILED $idnum: " . ($result['error'] ?? 'unknown'), $logFile);
        }
    }

    // Rate limiting
    usleep(100000);
}

echo "\n\n";

$elapsed = time() - $startTime;
logMessage("=== Re-check Complete ===", $logFile);
logMessage("Time elapsed: " . formatTime($elapsed), $logFile);
logMessage("Processed: $processed", $logFile);
logMessage("Upgraded to ROOFTOP: " . $upgradeCounts['ROOFTOP'], $logFile);
logMessage("Upgraded to STREET: " . $upgradeCounts['STREET'], $logFile);
logMessage("Total upgraded: $upgraded", $logFile);
logMessage("Unchanged (still CITY): $unchanged", $logFile);
logMessage("Failed: $failed", $logFile);
logMessage("Log file: $logFile", $logFile);
?>
