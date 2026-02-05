<?php
/**
 * Update Resource Location Data
 *
 * This endpoint updates the geographic coordinates for a resource.
 * It supports both:
 *   1. Server-side geocoding (preferred): Pass ADDRESS parameter
 *   2. Client-provided coordinates (legacy): Pass LATITUDE/LONGITUDE
 *
 * Parameters:
 *   IDNUM    - Required: Resource ID to update
 *   ADDRESS  - Optional: Full address to geocode server-side
 *   ZIP      - Optional: ZIP code for fallback
 *   LATITUDE - Optional: Client-provided latitude (legacy)
 *   LONGITUDE- Optional: Client-provided longitude (legacy)
 *   ACCURACY - Optional: Client-provided accuracy (legacy)
 */

// Include database connection FIRST to set session configuration
require_once('../../../private_html/db_login.php');

// Include Apple Maps Geocoder for server-side geocoding
require_once('../../lib/AppleMapsGeocoder.php');

// Start session and verify authentication
session_start();
$volunteerID = $_SESSION["UserID"] ?? '';
session_write_close(); // Release session lock

// Check if user is logged in
if (empty($volunteerID)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ERROR', 'message' => 'Unauthorized - not logged in']);
    exit;
}

// Sanitize and validate input
$idNum = trim($_REQUEST['IDNUM'] ?? '');
$address = trim($_REQUEST['ADDRESS'] ?? '');
$latitude = trim($_REQUEST['LATITUDE'] ?? '');
$longitude = trim($_REQUEST['LONGITUDE'] ?? '');
$accuracy = trim($_REQUEST['ACCURACY'] ?? '');
$zip = trim($_REQUEST['ZIP'] ?? '');

try {
    if (empty($idNum)) {
        throw new Exception("Missing required parameter: IDNUM");
    }

    // If address is provided, use server-side geocoding (preferred method)
    if (!empty($address)) {
        $geocoder = new AppleMapsGeocoder();
        $geoResult = $geocoder->geocode($address, $zip);

        if ($geoResult['success']) {
            $latitude = $geoResult['latitude'];
            $longitude = $geoResult['longitude'];
            $accuracy = $geoResult['accuracy'];

            // Mark accuracy level based on result type
            if (!empty($geoResult['zipFallback'])) {
                $accuracy = 'ZIP_FALLBACK';
            } elseif (!empty($geoResult['simplified'])) {
                $accuracy = 'ADDRESS_SIMPLIFIED';
            }

            error_log("Server geocoded resource $idNum: $latitude, $longitude ($accuracy)");
        } else {
            // Geocoding failed - fall back to zip code
            error_log("Server geocoding failed for resource $idNum: " . ($geoResult['error'] ?? 'unknown'));
        }
    }

    // If still no coordinates, try zip code lookup
    if (empty($latitude) || empty($longitude)) {
        if (!empty($zip)) {
            $zipPrefix = substr($zip, 0, 6);
            $coordQuery = "SELECT latitude, longitude
                          FROM ZipCodesPostal
                          WHERE ZIP = :zip
                          LIMIT 1";

            $coords = dataQuery($coordQuery, [':zip' => $zipPrefix]);

            if (!empty($coords)) {
                $latitude = $coords[0]->latitude;
                $longitude = $coords[0]->longitude;
                $accuracy = 'ZIP_CENTER';
                error_log("Resource $idNum using zip center fallback: $latitude, $longitude");
            }
        }
    }

    // Validate coordinates
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        throw new Exception("Invalid or missing coordinates");
    }

    // Calculate the x, y, z values for spherical coordinate distance calculations
    $xyz = AppleMapsGeocoder::calculateXYZ($latitude, $longitude);

    // Use direct PDO connection for both UPDATE and verification to ensure consistency
    global $env;
    $dbh = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_DATABASE']};charset=utf8mb4",
        $env['DB_USERNAME'],
        $env['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Log what we're about to update
    error_log("Re-geocode: Updating resource $idNum with lat=$latitude, lng=$longitude, acc=$accuracy");

    // Update resource with new coordinates
    $updateQuery = "UPDATE resource
                   SET LATITUDE = :latitude,
                       LONGITUDE = :longitude,
                       GEOACCURACY = :accuracy,
                       x = :x,
                       y = :y,
                       z = :z
                   WHERE IDNUM = :idNum";

    $stmt = $dbh->prepare($updateQuery);
    $stmt->execute([
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':accuracy' => $accuracy,
        ':x' => $xyz['x'],
        ':y' => $xyz['y'],
        ':z' => $xyz['z'],
        ':idNum' => $idNum
    ]);

    $rowCount = $stmt->rowCount();
    error_log("Re-geocode UPDATE executed for resource $idNum by $volunteerID - rows affected: $rowCount");

    // Verify the record exists and get current values (same connection)
    // Note: rowCount may be 0 if values didn't change, which is not an error
    $verifyStmt = $dbh->prepare("SELECT LATITUDE, LONGITUDE, GEOACCURACY FROM resource WHERE IDNUM = :idNum");
    $verifyStmt->execute([':idNum' => $idNum]);
    $row = $verifyStmt->fetch(PDO::FETCH_OBJ);

    if (!$row) {
        throw new Exception("Resource not found: $idNum");
    }

    $savedLat = $row->LATITUDE;
    $savedLng = $row->LONGITUDE;
    $savedAcc = $row->GEOACCURACY;

    error_log("Verified update for resource $idNum: lat=$savedLat, lng=$savedLng, acc=$savedAcc");

    // Log the action to resourceEditLog
    try {
        $logQuery = "INSERT INTO resourceEditLog (UserName, resourceIDNUM, Action) VALUES (:volunteerID, :idNum, :action)";
        dataQuery($logQuery, [
            ':volunteerID' => $volunteerID,
            ':idNum' => $idNum,
            ':action' => 'Re-geocode'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log re-geocode action: " . $e->getMessage());
    }

    // Return success with details
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'OK',
        'latitude' => $savedLat,
        'longitude' => $savedLng,
        'accuracy' => $savedAcc
    ]);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ERROR',
        'message' => $e->getMessage(),
        'idnum' => $idNum
    ]);
    error_log("updateLocationData error for ID $idNum: " . $e->getMessage());
}
?>
