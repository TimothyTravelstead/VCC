<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../../private_html/db_login.php');
require_once('../../../private_html/csrf_protection.php');

// Include Apple Maps Geocoder for server-side geocoding
require_once('../../lib/AppleMapsGeocoder.php');

// Now start the session with the correct configuration
session_start();

$VolunteerID = $_SESSION['UserID'] ?? null;
session_write_close(); // Release session lock to prevent blocking concurrent requests

// Verify user is logged in
if (!$VolunteerID) {
    sendErrorResponse(401, 'Unauthorized access. You must be logged in to perform this action.');
}

// Validate CSRF token for security
requireValidCSRFToken($_REQUEST);

// Clean and prepare input data
function cleanInput($value) {
    return trim($value ?? '');
}

// Helper function for error responses
function sendErrorResponse($code, $message, $details = null) {
    $response = [
        'status' => 'ERROR',
        'code' => $code,
        'message' => $message
    ];
    
    if ($details) {
        $response['details'] = $details;
    }
    
    // Add SQL debug information if available
    if (!empty($GLOBALS['sql_log'])) {
        $response['sql_debug'] = $GLOBALS['sql_log'];
    }
    
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($response);
    exit;
}

try {
    // Get counter for new ID
    $queryA = "SELECT MAX(counter) + 1 AS new_id FROM resource";
    $resultA = dataQuery($queryA);
    
    if (empty($resultA)) {
        sendErrorResponse(500, 'Failed to generate a new resource ID.', 'Database query returned no results');
    }
    
    $IdNum = $resultA[0]->new_id ?? 1; // Default to 1 if null
    
    if (!$IdNum) {
        sendErrorResponse(500, 'Invalid resource ID generated.', 'The ID calculation returned null or zero');
    }

    // Prepare input data
    $input = [
        'NAME' => cleanInput($_REQUEST['NAME']),
        'NAME2' => cleanInput($_REQUEST['NAME2']),
        'CONTACT' => cleanInput($_REQUEST['CONTACT']),
        'ADDRESS1' => cleanInput($_REQUEST['ADDRESS1']),
        'ADDRESS2' => cleanInput($_REQUEST['ADDRESS2']),
        'CITY' => cleanInput($_REQUEST['CITY']),
        'STATE' => cleanInput($_REQUEST['STATE']),
        'ZIP' => cleanInput($_REQUEST['ZIP']),
        'COUNTRY' => cleanInput($_REQUEST['COUNTRY']),
        'TYPE1' => cleanInput($_REQUEST['TYPE1']),
        'TYPE2' => cleanInput($_REQUEST['TYPE2']),
        'TYPE3' => cleanInput($_REQUEST['TYPE3']),
        'TYPE4' => cleanInput($_REQUEST['TYPE4']),
        'TYPE5' => cleanInput($_REQUEST['TYPE5']),
        'TYPE6' => cleanInput($_REQUEST['TYPE6']),
        'TYPE7' => cleanInput($_REQUEST['TYPE7']),
        'TYPE8' => cleanInput($_REQUEST['TYPE8']),
        'PHONE' => cleanInput($_REQUEST['PHONE']),
        'EXT' => cleanInput($_REQUEST['EXT']),
        'HOTLINE' => cleanInput($_REQUEST['HOTLINE']),
        'FAX' => cleanInput($_REQUEST['FAX']),
        'INTERNET' => cleanInput($_REQUEST['INTERNET']),
        'SHOWMAIL' => cleanInput($_REQUEST['SHOWMAIL']),
        'DESCRIPT' => cleanInput($_REQUEST['DESCRIPT']),
        'WWWEB' => cleanInput($_REQUEST['WWWEB']),
        'WWWEB2' => cleanInput($_REQUEST['WWWEB2']),
        'WWWEB3' => cleanInput($_REQUEST['WWWEB3']),
        'GIVE_ADDR' => cleanInput($_REQUEST['GIVE_ADDR']),
        'CNATIONAL' => cleanInput($_REQUEST['CNATIONAL']),
        'LOCAL' => cleanInput($_REQUEST['LOCAL']),
        'CLOSED' => cleanInput($_REQUEST['CLOSED']),
        'MAILPAGE' => cleanInput($_REQUEST['MAILPAGE']),
        'STATEWIDE' => cleanInput($_REQUEST['STATEWIDE']),
        'WEBSITE' => cleanInput($_REQUEST['WEBSITE']),
        'AREACODE' => cleanInput($_REQUEST['AREACODE']),
        'NONLGBT' => cleanInput($_REQUEST['NONLGBT']),
        'NOTE' => cleanInput(urldecode($_REQUEST['NOTE'] ?? ''))
    ];
    
    // Validate required fields
    if (empty($input['NAME'])) {
        sendErrorResponse(400, 'Missing required field', 'The NAME field cannot be empty');
    }

    // Validate field lengths
    if (strlen($input['ADDRESS1']) > 100) {
        sendErrorResponse(400, 'Field too long', 'ADDRESS1 cannot exceed 100 characters. Current length: ' . strlen($input['ADDRESS1']));
    }
    if (strlen($input['ADDRESS2']) > 100) {
        sendErrorResponse(400, 'Field too long', 'ADDRESS2 cannot exceed 100 characters. Current length: ' . strlen($input['ADDRESS2']));
    }
    
    // First, check if the ZIP code exists in ZipCodesPostal
    $zipQuery = "SELECT Zip, Latitude, Longitude, State FROM ZipCodesPostal WHERE Zip = :zip LIMIT 1";
    $zipResult = dataQuery($zipQuery, [':zip' => $input['ZIP']]);

    $linkableZip = substr($input['ZIP'], 0, 5); // Use first 5 digits
    $regionInfo = null;
    $latitude = null;
    $longitude = null;
    $geoAccuracy = null;

    if (empty($zipResult)) {
        // No ZIP found - notify about missing ZIP
        error_log("Warning: ZIP code '{$input['ZIP']}' not found in database");
    } else {
        // ZIP found - get region info
        $zipInfo = $zipResult[0];
        $linkableZip = substr($zipInfo->Zip, 0, 5);

        $regionQuery = "SELECT Region, State FROM Regions WHERE Abbreviation = :state LIMIT 1";
        $regionResult = dataQuery($regionQuery, [':state' => $zipInfo->State]);
        $regionInfo = !empty($regionResult) ? $regionResult[0] : null;
    }

    // Build full address for geocoding
    $fullAddress = trim(
        $input['ADDRESS1'] . ' ' .
        $input['ADDRESS2'] . ', ' .
        $input['CITY'] . ' ' .
        $input['STATE'] . ' ' .
        $input['ZIP']
    );

    // Try server-side geocoding with Apple Maps API
    $geocodeSuccess = false;
    try {
        $geocoder = new AppleMapsGeocoder();
        $geoResult = $geocoder->geocode($fullAddress, $input['ZIP']);

        if ($geoResult['success']) {
            $latitude = $geoResult['latitude'];
            $longitude = $geoResult['longitude'];
            $geoAccuracy = $geoResult['accuracy'];

            // Mark if we had to simplify or fall back to zip
            if (!empty($geoResult['zipFallback'])) {
                $geoAccuracy = 'ZIP_FALLBACK';
            } elseif (!empty($geoResult['simplified'])) {
                $geoAccuracy = 'ADDRESS_SIMPLIFIED';
            }

            $geocodeSuccess = true;
            error_log("Geocoded new resource: $latitude, $longitude ($geoAccuracy)");
        }
    } catch (Exception $geoEx) {
        error_log("Geocoding exception for new resource: " . $geoEx->getMessage());
    }

    // Fall back to zip code coordinates if geocoding failed
    if (!$geocodeSuccess && !empty($zipResult)) {
        $latitude = $zipResult[0]->Latitude;
        $longitude = $zipResult[0]->Longitude;
        $geoAccuracy = 'ZIP_CENTER';  // Indicates fallback to zip center
        error_log("New resource geocoding failed, using zip center: $latitude, $longitude");
    }
    
    // Build the query with proper handling for geography data
    $query = "INSERT INTO resource (
        NAME, NAME2, CONTACT, ADDRESS1, ADDRESS2, CITY, STATE, ZIP, COUNTRY,
        POSTNET, REGION, TYPE1, TYPE2, TYPE3, TYPE4, EDATE, PHONE, EXT,
        HOTLINE, FAX, INTERNET, SHOWMAIL, DESCRIPT, WWWEB, WWWEB2, WWWEB3,
        GIVE_ADDR, CNATIONAL, LOCAL, CLOSED, MAILPAGE, STATEWIDE, WEBSITE,
        AREACODE, LATITUDE, LONGITUDE, GEOACCURACY, NOTE, IDNUM, LinkableZip, x, y, z,
        NONLGBT, TYPE5, TYPE6, TYPE7, TYPE8
    ) VALUES (
        :NAME, :NAME2, :CONTACT, :ADDRESS1, :ADDRESS2, :CITY, :STATE, :ZIP,
        NULLIF(:COUNTRY, 'NULL'),
        :POSTNET, :REGION, :TYPE1, :TYPE2, :TYPE3, :TYPE4, CURDATE(), :PHONE, :EXT, :HOTLINE,
        :FAX, :INTERNET, :SHOWMAIL, :DESCRIPT, :WWWEB, :WWWEB2, :WWWEB3,
        :GIVE_ADDR, :CNATIONAL, :LOCAL, :CLOSED, :MAILPAGE, :STATEWIDE,
        :WEBSITE, :AREACODE, :LATITUDE, :LONGITUDE, :GEOACCURACY, :NOTE, :IDNUM, :LinkableZip,
        :x_value, :y_value, :z_value,
        :NONLGBT, :TYPE5, :TYPE6, :TYPE7, :TYPE8
    )";

    // Prepare all parameters with default values for geographical data
    $params = $input;
    $params[':IDNUM'] = $IdNum;
    $params[':POSTNET'] = $regionInfo ? $regionInfo->Region : null;
    $params[':REGION'] = $regionInfo ? $regionInfo->State : null;
    $params[':LATITUDE'] = $latitude;
    $params[':LONGITUDE'] = $longitude;
    $params[':GEOACCURACY'] = $geoAccuracy;
    $params[':LinkableZip'] = $linkableZip;

    // Calculate spherical coordinates if lat/long are available
    if ($latitude !== null && $longitude !== null) {
        $xyz = AppleMapsGeocoder::calculateXYZ($latitude, $longitude);
        $params[':x_value'] = $xyz['x'];
        $params[':y_value'] = $xyz['y'];
        $params[':z_value'] = $xyz['z'];
    } else {
        // Default values if no coordinates
        $params[':x_value'] = 0;
        $params[':y_value'] = 0;
        $params[':z_value'] = 0;
    }

    // Execute insert
    $result = dataQuery($query, $params);

    // Check if we got an error array back from dataQuery
    if (is_array($result) && isset($result['error']) && $result['error'] === true) {
        sendErrorResponse(500, 
            "Database error creating new record for '{$input['NAME']}'", 
            $result['message'] ?? 'Unknown database error'
        );
    }

    // Verify the insert
    $verifyQuery = "SELECT IDNUM FROM resource WHERE IDNUM = :idnum";
    $verifyResult = dataQuery($verifyQuery, [':idnum' => $IdNum]);

    if (empty($verifyResult)) {
        sendErrorResponse(500, 
            'Record verification failed', 
            'The record was not found after insertion attempt'
        );
    }

    // Insert YouthBlock record
    try {
        $youthBlockValue = (cleanInput($_REQUEST['YOUTHBLOCK'] ?? '') === 'Y') ? 1 : 0;
        $youthBlockQuery = "INSERT INTO ResourceYouthBlock (IDNUM, YouthBlock) VALUES (:idnum, :youthblock)";
        $youthBlockParams = [
            ':idnum' => $IdNum,
            ':youthblock' => $youthBlockValue
        ];
        dataQuery($youthBlockQuery, $youthBlockParams);
    } catch (Exception $e) {
        // Continue even if YouthBlock insert fails - just note the error
        error_log("Failed to insert YouthBlock record: " . $e->getMessage());
    }

    // Log the edit
    try {
        $logQuery = "INSERT INTO resourceEditLog (UserName, resourceIDNUM, Action)
                    VALUES (:user, :idnum, 'New Record')";
        $logParams = [
            ':user' => $VolunteerID,
            ':idnum' => $IdNum
        ];
        dataQuery($logQuery, $logParams);
    } catch (Exception $e) {
        // Continue even if logging fails - just note the error
        error_log("Failed to log resource edit: " . $e->getMessage());
    }

    // Success response
    $returnValue = [
        'status' => 'OK',
        'code' => 201,
        'message' => 'Resource created successfully',
        'data' => [
            'location' => "{$input['ADDRESS1']} {$input['ADDRESS2']}, {$input['CITY']} {$input['STATE']}  {$input['ZIP']}",
            'idnum' => $IdNum
        ]
    ];

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(201); // Created
    echo json_encode($returnValue);
    
} catch (Exception $e) {
    // Catch any other exceptions
    sendErrorResponse(500, 
        'An unexpected error occurred', 
        $e->getMessage()
    );
}
?>