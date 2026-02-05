<?php
// 1. Include db_login.php FIRST (sets session configuration)
require_once('../private_html/db_login.php');

// 2. Start session (inherits 8-hour timeout from db_login.php)
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

// 3. Read any needed session data
$isAdminUser = isset($_SESSION['AdminUser']) && $_SESSION['AdminUser'] === 'true';

// 4. Release session lock IMMEDIATELY
session_write_close();

// 5. Continue with database operations, output generation, etc.

// Get request parameters with array_key_exists to avoid undefined array key warnings
$Category = array_key_exists('Category', $_REQUEST) ? $_REQUEST['Category'] ?: null : null;
$Range = array_key_exists('Range', $_REQUEST) ? $_REQUEST['Range'] ?: null : null;
$ZipCode = array_key_exists('ZipCode', $_REQUEST) ? $_REQUEST['ZipCode'] ?: null : null;
$City = array_key_exists('City', $_REQUEST) ? $_REQUEST['City'] ?: null : null;
$State = array_key_exists('State', $_REQUEST) ? $_REQUEST['State'] ?: null : null;
$Name = array_key_exists('Name', $_REQUEST) ? ($_REQUEST['Name'] ? trim($_REQUEST['Name']) : null) : null;
$SearchType = array_key_exists('SearchType', $_REQUEST) ? $_REQUEST['SearchType'] ?: "zip" : "zip";

$VolunteerID = 'travelstead';

// Check if this is an admin search (must have both the flag AND be an admin user)
// AdminUser is for pure admins, ResourceAdmin is a different type
$isAdminSearch = isset($_REQUEST['isAdminSearch']) &&
                 $_REQUEST['isAdminSearch'] === 'true' &&
                 $isAdminUser;

// Set the closed filter based on admin status
$closedFilter = $isAdminSearch ? "" : " AND Closed = 'N'";

$Latitude = null;
$Longitude = null;
$SearchCity = null;
$SearchState = null;
$LocationText = null;
$Country = null;

// Get the Coordinates of the Requested Zip Code 
if($SearchType == 'findZip') {
    $prequery = "SELECT zip 
                 FROM ZipCodesPostal 
                 WHERE City = ? 
                 AND State = ? 
                 AND LOCATIONTYPE = 'PRIMARY' 
                 ORDER BY zip ASC 
                 LIMIT 1";
    $preresult = dataQuery($prequery, [$City, $State]);
    
    if(!$preresult || count($preresult) == 0) {
        die("No ZipCode Located");
    }
    
    $ZipCode = $preresult[0]->zip;
    $SearchType = 'zip';
}

if($SearchType == 'zip') {
    $query = "SELECT latitude, longitude, City, State, locationText, Country 
              FROM ZipCodesPostal 
              WHERE Zip = ? 
              AND locationtype = 'PRIMARY' 
              LIMIT 1";
    
    $result = dataQuery($query, [$ZipCode]);
    
    if(!$result || count($result) == 0) {
        die("INVALID");
    }

    // Store the location data
    $location = $result[0];
    $Latitude = $location->latitude;
    $Longitude = $location->longitude;
    $SearchCity = $location->City;
    $SearchState = $location->State;
    $LocationText = $location->locationText;
    $Country = $location->Country;
}

$search = [
    'city' => $SearchCity ?? null,
    'state' => $SearchState ?? null,
    'place' => $LocationText ?? null,
    'zipcode' => $ZipCode ?? null,
    'range' => $Range ?? null,
    'category' => " " ?? null
];

// Calculate the Coordinates if we have valid coordinates
if ($Latitude !== null && $Longitude !== null) {
    $x = cos(deg2rad($Longitude)) * cos(deg2rad($Latitude));
    $y = sin(deg2rad($Longitude)) * cos(deg2rad($Latitude));
    $z = sin(deg2rad($Latitude));
} else {
    $x = 0;
    $y = 0;
    $z = 0;
}

// Distance calculation with fixes for edge cases:
// 1. When x,y,z are NULL (no coordinates), distance will be NULL (filtered out by WHERE clause)
// 2. When dot product >= 0.99999999999 (same location), treat as 0 distance
// 3. Cap dot product at 0.99999999999 to avoid acos(1) returning NULL
$dotProduct = "round(
    x * Cos(Radians($Longitude)) * Cos(Radians($Latitude)) 
    + y * Sin(Radians($Longitude)) * Cos(Radians($Latitude))
    + z * Sin(Radians($Latitude))
, 15)";

$Distance = "IF(x IS NULL OR y IS NULL OR z IS NULL,
    NULL,
    IF($dotProduct >= 0.99999999999, 
        0, 
        round(
            acos(LEAST(0.99999999999, $dotProduct)) * 180 * 60 / Pi() * 1852000 / 1609344
        )
    )
)";

// Build where clauses based on search type
if ($Country == "US" && $Category != "All") {
    $zipDistance = "IF(Local = 'Y' , -1 , $Distance)";
    $WhereStringDistance = "WHERE ((Local = 'Y'" . ($closedFilter ? " and Closed = 'N'" : "") . ") OR ($Distance <= ?" . $closedFilter . "))";
} else {
    $zipDistance = $Distance;
    $WhereStringDistance = "WHERE ($Distance <= ?" . $closedFilter . ")";
}

$WhereStringCity = "WHERE City = ? AND State = ?" . $closedFilter;
$WhereStringName = "WHERE (Name LIKE ? OR Name2 LIKE ?)" . $closedFilter;
$WhereStringNational = "WHERE CNATIONAL = 'Y' AND (COUNTRY IS NULL OR COUNTRY = '')" . $closedFilter;
$WhereStringInternational = "WHERE TRIM(COUNTRY) = TRIM(?)" . $closedFilter;
$WhereStringState = "WHERE State = ?" . $closedFilter;

// Determine search configuration based on search type
try {
    [$WhereString, $Distance, $OrderBy, $params] = match($SearchType) {
    'zip' => [
        $WhereStringDistance,
        $zipDistance,
        "ORDER BY Local desc, $Distance, City, State, Zip, Address1",
        [$Range]
    ],
    'city' => [
        $WhereStringCity,
        "'N/A'",
        "ORDER BY city, state, zip, address1",
        [$City, $State]
    ],
    'state' => [
        $WhereStringState,
        "'N/A'",
        "ORDER BY city, state, zip, address1",
        [$State]
    ],
    'name' => [
        $WhereStringName,
        "'N/A'",
        "ORDER BY city, state, zip, address1",
        ["%$Name%", "%$Name%"]
    ],
    'national' => [
        $WhereStringNational,
        "'N/A'",
        "ORDER BY Name, city, state, zip, address1",
        []
    ],
    'international' => [
        $WhereStringInternational,
        "'N/A'",
        "ORDER BY Name, city, state, zip, address1",
        [$Name]
    ],
    default => [
        $WhereStringNational,
        "'N/A'",
        "ORDER BY Name, city, state, zip, address1",
        []
    ]
};
} catch (Exception $e) {
    error_log("Match expression error for SearchType: " . $SearchType);
    // Default to national search if match fails
    [$WhereString, $Distance, $OrderBy, $params] = [
        $WhereStringNational,
        "'N/A'",
        "ORDER BY Name, city, state, zip, address1",
        []
    ];
}

// Add category filter if needed
if($Category != "All") {
    if (strpos($Category, "-") !== false) {
        $WhereString .= " AND (TYPE1 = ? OR TYPE2 = ? OR TYPE3 = ? OR TYPE4 = ? 
                         OR TYPE5 = ? OR TYPE6 = ? OR TYPE7 = ? OR TYPE8 = ?)";
        array_push($params, ...array_fill(0, 8, $Category));
    } else {
        $WhereString .= " AND (TYPE1 LIKE ? OR TYPE2 LIKE ? OR TYPE3 LIKE ? OR TYPE4 LIKE ? 
                         OR TYPE5 LIKE ? OR TYPE6 LIKE ? OR TYPE7 LIKE ? OR TYPE8 LIKE ?)";
        $categoryPattern = "$Category%";
        array_push($params, ...array_fill(0, 8, $categoryPattern));
    }
}

// Final resource query with all fields
$resourceQuery = "SELECT 
    trim(idnum) as idnum,
    concat(trim(name), ' ', trim(name2)) as Name,
    Type1, Type2, Type3, Type4,
    if(GIVE_ADDR = 'N', trim(address1), ' ') as Address1,
    if(GIVE_ADDR = 'N', trim(address2), ' ') as Address2,
    trim(city) as City,
    state as State,
    concat(trim(City), ', ', trim(State)) as Location,
    trim(linkableZip) as Zip,
    trim(contact) as Contact,
    trim(phone) as Phone,
    EXT,
    trim(descript) as Description,
    right(Note, (length(note) - locate('\r', note))) as Note,
    $Distance as Distance,
    trim(hotline) as Hotline,
    trim(fax) as Fax,
    trim(internet) as Internet,
    trim(wwweb) as WWWEB,
    trim(wwweb2) as WWWEB2,
    trim(wwweb3) as WWWEB3,
    trim(edate) as Edate,
    longitude, latitude, Local, Country, NonLGBT,
    Type5, Type6, Type7, Type8,
    Closed
FROM resource 
$WhereString 
$OrderBy 
LIMIT 500";

$result = dataQuery($resourceQuery, $params);

if(!$result || count($result) == 0) {
    die("NONE");
}

switch ($SearchType) {
	case 'city':
		$search['place'] = $City.", ".$State;
		$search['range'] = "N/A";
		break;
	case 'state':
		$search['place'] = "State=".$State;
		$search['range'] = "N/A";
		break;
	case 'name':
		$search['place'] = "Name = '".$Name."'";
		$search['range'] = "N/A";
		break;
	case 'national':
		$search['place'] = "National Listings";
		$search['range'] = "N/A";
		break;
	case 'international':
		$search['place'] = $Name." Listings";
		$search['range'] = "N/A";
		break;
}



// Build response
$resources = [];
foreach($result as $resource) {
    $resourceArray = (array)$resource;
    $resources[] = $resourceArray;  // Use numeric array instead of associative
}

$response = [
    "Search" => $search,
    "Resources" => $resources
];

echo json_encode($response);
?>
