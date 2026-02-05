<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../../private_html/db_login.php');

// Include Apple Maps Geocoder for server-side geocoding
require_once('../../lib/AppleMapsGeocoder.php');

// Now start the session with the correct configuration
session_start();

try {
    // Get volunteer ID from session
    $volunteerID = $_SESSION["UserID"] ?? '';
    session_write_close(); // Release session lock to prevent blocking concurrent requests

    // Check if user is logged in
    if (empty($volunteerID)) {
        throw new Exception('Unauthorized access. You must be logged in to perform this action.');
    }
    
    // Function to safely process input
    function processInput($key) {
        return trim($_REQUEST[$key] ?? '');
    }
    
    // Process input parameters
    $idNum = processInput('IdNum');
    if (empty($idNum)) {
        throw new Exception('Missing required parameter: IdNum');
    }
    
    $params = [
        ':idNum' => $idNum,
        ':name' => processInput('NAME'),
        ':name2' => processInput('NAME2'),
        ':contact' => processInput('CONTACT'),
        ':address1' => processInput('ADDRESS1'),
        ':address2' => processInput('ADDRESS2'),
        ':city' => processInput('CITY'),
        ':state' => processInput('STATE'),
        ':zip' => processInput('ZIP'),
        ':country' => processInput('COUNTRY'),
        ':type1' => processInput('TYPE1'),
        ':type2' => processInput('TYPE2'),
        ':type3' => processInput('TYPE3'),
        ':type4' => processInput('TYPE4'),
        ':type5' => processInput('TYPE5'),
        ':type6' => processInput('TYPE6'),
        ':type7' => processInput('TYPE7'),
        ':type8' => processInput('TYPE8'),
        ':phone' => processInput('PHONE'),
        ':ext' => processInput('EXT'),
        ':hotline' => processInput('HOTLINE'),
        ':fax' => processInput('FAX'),
        ':internet' => processInput('INTERNET'),
        ':showmail' => processInput('SHOWMAIL'),
        ':descript' => processInput('DESCRIPT'),
        ':wwweb' => processInput('WWWEB'),
        ':wwweb2' => processInput('WWWEB2'),
        ':wwweb3' => processInput('WWWEB3'),
        ':give_addr' => processInput('GIVE_ADDR'),
        ':cnational' => processInput('CNATIONAL'),
        ':local' => processInput('LOCAL'),
        ':closed' => processInput('CLOSED'),
        ':nonlgbt' => processInput('NONLGBT'),
        ':mailpage' => processInput('MAILPAGE'),
        ':statewide' => processInput('STATEWIDE'),
        ':website' => processInput('WEBSITE'),
        ':areacode' => processInput('AREACODE'),
        ':note' => urldecode(processInput('NOTE'))
    ];
    
    // Handle special cases for country - NULL vs string value
    $countryValue = $params[':country'];
    if ($countryValue === 'NULL' || empty($countryValue)) {
        $countryClause = 'COUNTRY = NULL';
        // Remove from params to avoid binding a parameter that isn't used
        unset($params[':country']);
    } else {
        $countryClause = 'COUNTRY = :country';
    }
    
    // Handle special case for EDATE
    $edateValue = processInput('EDATE');
    $edateClause = ($edateValue === 'Update') ? 'EDATE = CURDATE(),' : '';
    
    // Validate field lengths
    if (strlen($params[':address1']) > 100) {
        throw new Exception('ADDRESS1 cannot exceed 100 characters. Current length: ' . strlen($params[':address1']));
    }
    if (strlen($params[':address2']) > 100) {
        throw new Exception('ADDRESS2 cannot exceed 100 characters. Current length: ' . strlen($params[':address2']));
    }

    // First, verify record exists
    $checkQuery = "SELECT IDNUM FROM resource WHERE IDNUM = :idNum";
    $checkResult = dataQuery($checkQuery, [':idNum' => $idNum]);

    if (empty($checkResult)) {
        throw new Exception("Record with ID $idNum not found");
    }
    
    // Get zip code geographical data
    $zip = $params[':zip'];
    $linkableZip = substr($zip, 0, min(6, strlen($zip)));
    
    // Fetch region and state data for the zip code
    $regionQuery = "SELECT r.Region, r.State 
                   FROM Regions r 
                   JOIN ZipCodesPostal z ON (z.State = r.Abbreviation) 
                   WHERE z.Zip = :zip 
                   LIMIT 1";
    $regionData = dataQuery($regionQuery, [':zip' => $zip]);
    
    // Default values if no region data found
    $postnet = null;
    $region = null;
    
    if (!empty($regionData)) {
        $postnet = $regionData[0]->Region;
        $region = $regionData[0]->State;
    }
    
    // Default values
    $latitude = null;
    $longitude = null;
    $geoAccuracy = null;
    $x = 0;
    $y = 0;
    $z = 0;

    // Build full address for geocoding
    $fullAddress = trim(
        $params[':address1'] . ' ' .
        $params[':address2'] . ', ' .
        $params[':city'] . ' ' .
        $params[':state'] . ' ' .
        $params[':zip']
    );

    // Try server-side geocoding with Apple Maps API
    $geocodeSuccess = false;
    try {
        $geocoder = new AppleMapsGeocoder();
        $geoResult = $geocoder->geocode($fullAddress, $params[':zip']);

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
            error_log("Geocoded resource $idNum: $latitude, $longitude ($geoAccuracy)");
        }
    } catch (Exception $geoEx) {
        error_log("Geocoding exception for resource $idNum: " . $geoEx->getMessage());
    }

    // Fall back to zip code coordinates if geocoding failed
    if (!$geocodeSuccess) {
        $coordQuery = "SELECT Latitude, Longitude
                      FROM ZipCodesPostal
                      WHERE Zip = :zip
                      LIMIT 1";
        $coordData = dataQuery($coordQuery, [':zip' => $linkableZip]);

        if (!empty($coordData) && isset($coordData[0]->Latitude) && isset($coordData[0]->Longitude)) {
            $latitude = $coordData[0]->Latitude;
            $longitude = $coordData[0]->Longitude;
            $geoAccuracy = 'ZIP_CENTER';  // Indicates fallback to zip center
            error_log("Resource $idNum geocoding failed, using zip center: $latitude, $longitude");
        }
    }

    // Calculate spherical coordinates if we have lat/long data
    if ($latitude !== null && $longitude !== null) {
        $xyz = AppleMapsGeocoder::calculateXYZ($latitude, $longitude);
        $x = $xyz['x'];
        $y = $xyz['y'];
        $z = $xyz['z'];
    }
    
    // Add the calculated values to parameters
    $params[':postnet'] = $postnet;
    $params[':region'] = $region;
    $params[':latitude'] = $latitude;
    $params[':longitude'] = $longitude;
    $params[':geoAccuracy'] = $geoAccuracy;
    $params[':linkableZip'] = $linkableZip;
    $params[':x'] = $x;
    $params[':y'] = $y;
    $params[':z'] = $z;
    
    // Build the main update query
    $updateQuery = "UPDATE resource SET 
        NAME = :name,
        NAME2 = :name2,
        CONTACT = :contact,
        ADDRESS1 = :address1,
        ADDRESS2 = :address2,
        CITY = :city,
        STATE = :state,
        ZIP = :zip,
        $countryClause,
        POSTNET = :postnet,
        REGION = :region,
        TYPE1 = :type1,
        TYPE2 = :type2,
        TYPE3 = :type3,
        TYPE4 = :type4,
        TYPE5 = :type5,
        TYPE6 = :type6,
        TYPE7 = :type7,
        TYPE8 = :type8,
        $edateClause
        PHONE = :phone,
        EXT = :ext,
        HOTLINE = :hotline,
        FAX = :fax,
        INTERNET = :internet,
        SHOWMAIL = :showmail,
        DESCRIPT = :descript,
        WWWEB = :wwweb,
        WWWEB2 = :wwweb2,
        WWWEB3 = :wwweb3,
        GIVE_ADDR = :give_addr,
        CNATIONAL = :cnational,
        LOCAL = :local,
        CLOSED = :closed,
        NONLGBT = :nonlgbt,
        MAILPAGE = :mailpage,
        STATEWIDE = :statewide,
        WEBSITE = :website,
        AREACODE = :areacode,
        LATITUDE = :latitude,
        LONGITUDE = :longitude,
        GEOACCURACY = :geoAccuracy,
        NOTE = :note,
        LinkableZip = :linkableZip,
        x = :x,
        y = :y,
        z = :z
        WHERE IDNUM = :idNum";
    
    // Execute the update query
    $result = dataQuery($updateQuery, $params);
    
    // Check if the update was successful (handle both boolean and array response)
    if (is_array($result) && isset($result['error']) && $result['error'] === true) {
        throw new Exception("Database error: " . ($result['message'] ?? 'Unknown database error'));
    }
    
    // Delete from review table if closed
    if ($params[':closed'] === 'Y') {
        $deleteQuery = "DELETE FROM resourceReview WHERE IDNUM = :idNum";
        dataQuery($deleteQuery, [':idNum' => $idNum]);
    }

    // Update YouthBlock record (INSERT or UPDATE)
    try {
        $youthBlockValue = (trim($_REQUEST['YOUTHBLOCK'] ?? '') === 'Y') ? 1 : 0;
        $youthBlockQuery = "INSERT INTO ResourceYouthBlock (IDNUM, YouthBlock) VALUES (:idnum, :youthblock)
                           ON DUPLICATE KEY UPDATE YouthBlock = :youthblock2";
        $youthBlockParams = [
            ':idnum' => $idNum,
            ':youthblock' => $youthBlockValue,
            ':youthblock2' => $youthBlockValue
        ];
        dataQuery($youthBlockQuery, $youthBlockParams);
    } catch (Exception $e) {
        // Continue even if YouthBlock update fails - just note the error
        error_log("Failed to update YouthBlock record: " . $e->getMessage());
    }
    
    // Log the action
    $actionType = ($params[':closed'] === 'Y') ? 'Closed' : 'Update';
    $logQuery = "INSERT INTO resourceEditLog (UserName, resourceIDNUM, Action) 
                 VALUES (:volunteerID, :idNum, :action)";
    dataQuery($logQuery, [
        ':volunteerID' => $volunteerID,
        ':idNum' => $idNum,
        ':action' => $actionType
    ]);
    
    // Prepare success response
    $response = [
        'location' => trim($params[':address1'] . ' ' . $params[':address2'] . ', ' . 
                         $params[':city'] . ' ' . $params[':state'] . '  ' . $params[':zip']),
        'idnum' => $idNum,
        'status' => 'OK',
        'message' => 'Update Successful'
    ];
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    
} catch (Exception $e) {
    // Get the debug information if available
    $debugInfo = [];
    if (!empty($GLOBALS['sql_log'])) {
        $debugInfo['sql_log'] = $GLOBALS['sql_log'];
    }
    
    // Send error response
    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'An error occurred: ' . $e->getMessage(),
        'idnum' => $params[':idNum'] ?? null,
        'debug' => $debugInfo
    ]);
    
    // Log the error
    error_log("Resource update error for ID " . ($params[':idNum'] ?? 'unknown') . ": " . $e->getMessage());
}
?>