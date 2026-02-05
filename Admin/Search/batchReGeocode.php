<?php
/**
 * Batch Re-Geocoding Script
 *
 * Re-geocodes all open resources using the Apple Maps Server API.
 * Designed to be run from command line.
 *
 * Usage:
 *   php batchReGeocode.php              # Process all open resources
 *   php batchReGeocode.php --limit=100  # Process only 100 resources
 *   php batchReGeocode.php --start=500  # Start from offset 500
 *   php batchReGeocode.php --dry-run    # Show what would be done without updating
 *
 * Features:
 *   - Progress display with ETA
 *   - Automatic rate limiting (100ms between requests)
 *   - Logs results to file
 *   - Can be resumed using --start offset
 */

// Ensure running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Set unlimited execution time for batch processing
set_time_limit(0);
ini_set('memory_limit', '512M');

// Include required files
require_once(__DIR__ . '/../../../private_html/db_login.php');
require_once(__DIR__ . '/../../lib/AppleMapsGeocoder.php');

// Parse command line arguments
$options = getopt('', ['limit:', 'start:', 'dry-run', 'help']);

if (isset($options['help'])) {
    echo "Usage: php batchReGeocode.php [options]\n";
    echo "Options:\n";
    echo "  --limit=N    Process only N resources\n";
    echo "  --start=N    Start from offset N (for resuming)\n";
    echo "  --dry-run    Show what would be done without updating\n";
    echo "  --help       Show this help message\n";
    exit(0);
}

$limit = isset($options['limit']) ? (int)$options['limit'] : null;
$startOffset = isset($options['start']) ? (int)$options['start'] : 0;
$dryRun = isset($options['dry-run']);

// Log file
$logFile = __DIR__ . '/geocode_log_' . date('Ymd_His') . '.txt';

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

// Start
logMessage("=== Batch Re-Geocoding Started ===", $logFile);
logMessage("Dry run: " . ($dryRun ? "YES" : "NO"), $logFile);
logMessage("Start offset: $startOffset", $logFile);
logMessage("Limit: " . ($limit ?? "ALL"), $logFile);

// Count total resources to process
$countQuery = "SELECT COUNT(*) as cnt FROM resource WHERE CLOSED = 'N'";
$countResult = dataQuery($countQuery);
$totalResources = $countResult[0]->cnt;

logMessage("Total open resources: $totalResources", $logFile);

// Build query to get resources
$query = "SELECT IDNUM, NAME, ADDRESS1, ADDRESS2, CITY, STATE, ZIP,
                 ROUND(LATITUDE, 6) as OldLat, ROUND(LONGITUDE, 6) as OldLng, GeoAccuracy
          FROM resource
          WHERE CLOSED = 'N'
          ORDER BY IDNUM
          LIMIT " . ($limit ?? 999999) . " OFFSET $startOffset";

$resources = dataQuery($query);
$toProcess = count($resources);

logMessage("Resources to process in this run: $toProcess", $logFile);

if ($toProcess === 0) {
    logMessage("No resources to process. Exiting.", $logFile);
    exit(0);
}

// Initialize counters
$processed = 0;
$successful = 0;
$failed = 0;
$unchanged = 0;
$startTime = time();

// Initialize geocoder
$geocoder = new AppleMapsGeocoder();

// Process each resource
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

    // Show progress
    $percent = round(($processed / $toProcess) * 100, 1);
    $elapsed = time() - $startTime;
    $rate = $processed / max(1, $elapsed);
    $remaining = ($toProcess - $processed) / max(0.1, $rate);

    echo "\r[$percent%] Processing $processed/$toProcess (ID: $idnum) - ETA: " . formatTime($remaining) . "    ";

    // Skip if address is essentially empty (no city and no address)
    if (empty(trim($resource->CITY)) && strlen(trim($resource->ADDRESS1 . $resource->ADDRESS2)) < 3) {
        $unchanged++;
        continue;
    }

    // Geocode
    $result = $geocoder->geocode($address, $resource->ZIP);

    if ($result['success']) {
        $newLat = round($result['latitude'], 6);
        $newLng = round($result['longitude'], 6);
        $accuracy = $result['accuracy'];

        // Check if coordinates changed
        $latChanged = abs($newLat - $resource->OldLat) > 0.0001;
        $lngChanged = abs($newLng - $resource->OldLng) > 0.0001;

        if ($latChanged || $lngChanged || $resource->GeoAccuracy !== $accuracy) {
            if (!$dryRun) {
                // Calculate XYZ coordinates
                $xyz = AppleMapsGeocoder::calculateXYZ($result['latitude'], $result['longitude']);

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
                    ':lat' => $result['latitude'],
                    ':lng' => $result['longitude'],
                    ':accuracy' => $accuracy,
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

            $successful++;

            // Log significant changes
            if ($latChanged || $lngChanged) {
                $distance = sqrt(pow($newLat - $resource->OldLat, 2) + pow($newLng - $resource->OldLng, 2)) * 111; // rough km
                if ($distance > 1) {
                    logMessage("ID $idnum: Moved " . round($distance, 1) . "km - {$resource->NAME}", $logFile);
                }
            }
        } else {
            $unchanged++;
        }
    } else {
        $failed++;
        if ($processed <= 20 || $processed % 100 === 0) {
            logMessage("FAILED $idnum: " . ($result['error'] ?? 'unknown') . " - {$resource->NAME}", $logFile);
        }
    }

    // Rate limiting: 100ms between requests to avoid overwhelming the API
    usleep(100000);
}

echo "\n\n";

// Final summary
$elapsed = time() - $startTime;
logMessage("=== Batch Re-Geocoding Complete ===", $logFile);
logMessage("Time elapsed: " . formatTime($elapsed), $logFile);
logMessage("Processed: $processed", $logFile);
logMessage("Updated: $successful", $logFile);
logMessage("Unchanged: $unchanged", $logFile);
logMessage("Failed: $failed", $logFile);
logMessage("Rate: " . round($processed / max(1, $elapsed) * 60, 1) . " resources/minute", $logFile);
logMessage("Log file: $logFile", $logFile);

// Summary to restore if needed
if (!$dryRun && $successful > 0) {
    logMessage("\nTo restore from backup:", $logFile);
    logMessage("mysql -u dgqtkqjasj -p dgqtkqjasj < /home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/database_backups/resource_backup_20251227_193944.sql", $logFile);
}
?>
