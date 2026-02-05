<?php
/**
 * Geocode Preview Endpoint
 *
 * Returns geocoded coordinates for an address without saving to database.
 * Used by "Show Map View" to preview location before saving.
 */

require_once('../../../private_html/db_login.php');
require_once('../../lib/AppleMapsGeocoder.php');

session_start();
session_write_close();

// Require authentication
if (empty($_SESSION['UserID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$address = trim($_REQUEST['address'] ?? '');
$zip = trim($_REQUEST['zip'] ?? '');

if (empty($address)) {
    echo json_encode(['success' => false, 'error' => 'No address provided']);
    exit;
}

try {
    $geocoder = new AppleMapsGeocoder();
    $result = $geocoder->geocode($address, $zip);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
            'accuracy' => $result['accuracy'],
            'formattedAddress' => $result['formattedAddress'] ?? ''
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Geocoding failed'
        ]);
    }
} catch (Exception $e) {
    error_log("geocodePreview error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error during geocoding'
    ]);
}
?>
