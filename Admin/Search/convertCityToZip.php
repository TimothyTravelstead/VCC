<?php
/**
 * Convert CITY/REGION accuracy records to ZIP_ONLY
 *
 * Updates records that have CITY or REGION accuracy to use ZIP centroid
 * coordinates instead, which is typically more geographically precise.
 *
 * Usage:
 *   php convertCityToZip.php              # Process all remaining records
 *   php convertCityToZip.php --limit=500  # Process only 500 records
 *   php convertCityToZip.php --dry-run    # Show what would be done
 *
 * Note: Apple Maps API has daily quotas. If you hit the limit, wait until
 * midnight Pacific time and run again.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once(__DIR__ . '/../../../private_html/db_login.php');
require_once(__DIR__ . '/../../lib/AppleMapsGeocoder.php');

$options = getopt('', ['limit:', 'dry-run', 'help']);

if (isset($options['help'])) {
    echo "Usage: php convertCityToZip.php [options]\n";
    echo "Options:\n";
    echo "  --limit=N    Process only N records\n";
    echo "  --dry-run    Show what would be done without updating\n";
    echo "  --help       Show this help message\n";
    exit(0);
}

$limit = isset($options['limit']) ? (int)$options['limit'] : null;
$dryRun = isset($options['dry-run']);

$logFile = __DIR__ . '/convert_city_zip_log_' . date('Ymd_His') . '.txt';

function logMsg($msg, $logFile) {
    $line = "[" . date('Y-m-d H:i:s') . "] $msg\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

logMsg("=== Convert CITY/REGION to ZIP_ONLY ===", $logFile);
logMsg("Dry run: " . ($dryRun ? "YES" : "NO"), $logFile);

$geocoder = new AppleMapsGeocoder();

// Get CITY/REGION records with ZIP codes (US records have 2-letter state codes)
$query = "SELECT IDNUM, NAME, CITY, STATE, ZIP,
                 ROUND(LATITUDE, 6) as OldLat, ROUND(LONGITUDE, 6) as OldLng, GEOACCURACY
          FROM resource
          WHERE CLOSED = 'N'
          AND GEOACCURACY IN ('CITY', 'REGION')
          AND ZIP IS NOT NULL
          AND TRIM(ZIP) <> ''
          ORDER BY IDNUM";

if ($limit) {
    $query .= " LIMIT $limit";
}

$resources = dataQuery($query);
$total = count($resources);

logMsg("Records to process: $total", $logFile);

if ($total === 0) {
    logMsg("No records to process.", $logFile);
    exit(0);
}

$updated = 0;
$failed = 0;
$unsupported = 0;
$startTime = time();
$quotaExceeded = false;

foreach ($resources as $i => $r) {
    $processed = $i + 1;

    // Try to geocode the ZIP
    $result = $geocoder->geocode($r->ZIP, $r->ZIP);

    if ($result['success']) {
        if (!$dryRun) {
            $xyz = AppleMapsGeocoder::calculateXYZ($result['latitude'], $result['longitude']);

            $updateQuery = "UPDATE resource
                            SET LATITUDE = :lat, LONGITUDE = :lng, GEOACCURACY = 'ZIP_ONLY',
                                x = :x, y = :y, z = :z
                            WHERE IDNUM = :idnum";
            dataQuery($updateQuery, [
                ':lat' => $result['latitude'],
                ':lng' => $result['longitude'],
                ':x' => $xyz['x'],
                ':y' => $xyz['y'],
                ':z' => $xyz['z'],
                ':idnum' => $r->IDNUM
            ]);
        }
        $updated++;
    } else {
        // Check if quota exceeded
        if (strpos($result['error'] ?? '', 'HTTP 429') !== false ||
            strpos($result['error'] ?? '', 'Daily Limit') !== false) {
            logMsg("QUOTA EXCEEDED - Stopping. Run again tomorrow.", $logFile);
            $quotaExceeded = true;
            break;
        }

        // Check if this is likely international (non-US ZIP format)
        if (!preg_match('/^\d{5}(-\d{4})?$/', $r->ZIP)) {
            // Mark as UNSUPPORTED if it's international
            if (!$dryRun) {
                dataQuery("UPDATE resource SET GEOACCURACY = 'UNSUPPORTED' WHERE IDNUM = :id",
                         [':id' => $r->IDNUM]);
            }
            $unsupported++;
        } else {
            $failed++;
        }
    }

    // Progress every 100 records
    if ($processed % 100 === 0 || $processed === $total) {
        $pct = round($processed / $total * 100, 1);
        $elapsed = time() - $startTime;
        $rate = $processed / max(1, $elapsed);
        $eta = ($total - $processed) / max(0.1, $rate);
        $etaMin = round($eta / 60, 1);
        logMsg("[$pct%] $processed/$total - Updated: $updated, Unsupported: $unsupported, Failed: $failed (ETA: {$etaMin}m)", $logFile);
    }

    usleep(100000); // 100ms rate limit
}

echo "\n";
logMsg("=== Complete ===", $logFile);
logMsg("Processed: $processed", $logFile);
logMsg("Updated to ZIP_ONLY: $updated", $logFile);
logMsg("Marked UNSUPPORTED: $unsupported", $logFile);
logMsg("Failed: $failed", $logFile);
if ($quotaExceeded) {
    logMsg("NOTE: Quota exceeded. Run again tomorrow to continue.", $logFile);
}
logMsg("Log: $logFile", $logFile);
?>
