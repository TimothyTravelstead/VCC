<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Release session lock immediately - this script doesn't read/write session data
session_write_close();

/**
 * Replaces problematic character sequences with their proper equivalents
 * @param string|null $string The input string to clean
 * @return string The cleaned string
 */
function replace_problematic_sequences($string) {
    if ($string === null) {
        return '';
    }
    
    $replacements = [
        'â' => "'", // Apostrophe
        'â' => '"', // Left double quote
        'â' => '"', // Right double quote
        'â' => '-', // Dash
    ];
    return strtr($string, $replacements);
}

// Get and validate the record ID
$RecordNo = $_REQUEST["idnum"] ?? '';

if (empty($RecordNo)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID provided']);
    exit;
}

// Query using parameter binding - include YouthBlock from ResourceYouthBlock table
// Explicitly select LATITUDE, LONGITUDE, GEOACCURACY to ensure they're included
$query = "SELECT resource.IDNUM, resource.NAME, resource.NAME2, resource.CONTACT,
          resource.ADDRESS1, resource.ADDRESS2, resource.CITY, resource.STATE, resource.ZIP,
          resource.POSTNET, resource.REGION, resource.TYPE1, resource.TYPE2, resource.TYPE3,
          resource.TYPE4, resource.TYPE5, resource.TYPE6, resource.TYPE7, resource.TYPE8,
          resource.PHONE, resource.EXT, resource.HOTLINE, resource.FAX, resource.INTERNET,
          resource.SHOWMAIL, resource.DESCRIPT, resource.WWWEB, resource.WWWEB2, resource.WWWEB3,
          resource.GIVE_ADDR, resource.CNATIONAL, resource.LOCAL, resource.CLOSED, resource.NONLGBT,
          resource.MAILPAGE, resource.STATEWIDE, resource.WEBSITE, resource.AREACODE, resource.NOTE,
          resource.EDATE, resource.Country, resource.LinkableZip,
          resource.LATITUDE, resource.LONGITUDE, resource.GEOACCURACY,
          resource.x, resource.y, resource.z,
          COALESCE(ryb.YouthBlock, 0) as YOUTHBLOCK
          FROM resource
          LEFT JOIN ResourceYouthBlock ryb ON resource.idnum = ryb.IDNUM
          WHERE resource.idnum = :idnum";
$params = [':idnum' => $RecordNo];

$result = dataQuery($query, $params);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed']);
    exit;
}

if (empty($result)) {
    http_response_code(404);
    echo json_encode(['error' => 'Resource not found']);
    exit;
}

// Clean and prepare the resource data
$resource = [];
foreach ($result[0] as $key => $value) {
    $resource[$key] = replace_problematic_sequences($value);
}

// Set proper JSON content type and prevent caching
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Output the JSON-encoded resource
echo json_encode($resource, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
