<?php


require_once('../../private_html/db_login.php');
session_start();
if ($_SESSION['auth'] != 'yes') {
    die("Unauthorized");
}


function getJsonParam($data, $key, $default = null) {
    return isset($data[$key]) ? trim($data[$key]) : $default;
}

$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (!$data) {
    die("Invalid JSON input");
}

$VolunteerID = $_SESSION["UserID"];
$UserID = $_SESSION["UserID"] ?? 'Snow White';
$admin = $_SESSION['editResources'] != "user";

// Extract all parameters from JSON data
$IdNum = getJsonParam($data, 'IdNum');
$NAME = getJsonParam($data, 'NAME');
$NAME2 = getJsonParam($data, 'NAME2');
$CONTACT = getJsonParam($data, 'CONTACT');
$ADDRESS1 = getJsonParam($data, 'ADDRESS1');
$ADDRESS2 = getJsonParam($data, 'ADDRESS2');
$CITY = getJsonParam($data, 'CITY');
$STATE = getJsonParam($data, 'STATE');
$ZIP = getJsonParam($data, 'ZIP');
$COUNTRY = getJsonParam($data, 'COUNTRY');
$TYPE1 = getJsonParam($data, 'TYPE1');
$TYPE2 = getJsonParam($data, 'TYPE2');
$TYPE3 = getJsonParam($data, 'TYPE3');
$TYPE4 = getJsonParam($data, 'TYPE4');
$TYPE5 = getJsonParam($data, 'TYPE5');
$TYPE6 = getJsonParam($data, 'TYPE6');
$TYPE7 = getJsonParam($data, 'TYPE7');
$TYPE8 = getJsonParam($data, 'TYPE8');
$EDATE = getJsonParam($data, 'EDATE');
$PHONE = getJsonParam($data, 'PHONE');
$EXT = getJsonParam($data, 'EXT');
$HOTLINE = getJsonParam($data, 'HOTLINE');
$FAX = getJsonParam($data, 'FAX');
$INTERNET = getJsonParam($data, 'INTERNET');
$SHOWMAIL = getJsonParam($data, 'SHOWMAIL');
$DESCRIPT = getJsonParam($data, 'DESCRIPT');
$WWWEB = getJsonParam($data, 'WWWEB');
$WWWEB2 = getJsonParam($data, 'WWWEB2');
$WWWEB3 = getJsonParam($data, 'WWWEB3');
$GIVE_ADDR = getJsonParam($data, 'GIVE_ADDR');
$CNATIONAL = getJsonParam($data, 'CNATIONAL');
$LOCAL = getJsonParam($data, 'LOCAL');
$CLOSED = getJsonParam($data, 'CLOSED');
$MAILPAGE = getJsonParam($data, 'MAILPAGE');
$STATEWIDE = getJsonParam($data, 'STATEWIDE');
$WEBSITE = getJsonParam($data, 'WEBSITE');
$AREACODE = getJsonParam($data, 'AREACODE');
$NONLGBT = getJsonParam($data, 'NONLGBT');
$NOTE = getJsonParam($data, 'NOTE');
$notesToAaron = getJsonParam($data, 'notesToAaron');
$websiteUpdateDate = getJsonParam($data, 'webSiteUpdateDate');
$websiteUpdateType = getJsonParam($data, 'webSiteUpdateType');
$editCheckBoxStatus = getJsonParam($data, 'editCheckBoxStatus');
$LATITUDE = getJsonParam($data, 'LATITUDE');
$LONGITUDE = getJsonParam($data, 'LONGITUDE');

$radioButtonResult = json_decode($editCheckBoxStatus, true);

$updateList = [];
$deleteUpdateList = [];

// Process radio button results for deletions
if ($radioButtonResult) {
    foreach ($radioButtonResult as $item) {
        foreach ($item as $key => $val) {
            if ($val == "Remove") {
                $fieldKey = str_replace("delete", "", $key);
                
                switch ($fieldKey) {
                    case "ADDRESS":
                    case "ADDRESS1":
                    case "ADDRESS2":
                        $deleteUpdateList['ADDRESS1'] = '';
                        $deleteUpdateList['ADDRESS2'] = '';
                        break;

                    case "NAME":
                    case "NAM2":
                        $deleteUpdateList['NAME'] = '';
                        $deleteUpdateList['NAME2'] = '';
                        break;

                    default:
                        $deleteUpdateList[$fieldKey] = '';
                }
                $data[$fieldKey] = null;
            }
        }
    }
}

// Build update list from remaining data
foreach ($data as $key => $val) {
    if ($key != "editCheckBoxStatus" && 
        $key != "notesToAaron" && 
        $key != "webSiteUpdateDate" && 
        $key != "webSiteUpdateType" && 
        $key != "editUser" && 
        !empty($val)) {
        $updateList[$key] = $val;
    }
}

if (!$admin) {
    if (!empty($updateList)) {
        // Non-admin update - store in review table
        $query = "UPDATE resourceReview 
                 SET EDATE = NOW(), 
                     UpdateData = ? 
                 WHERE IDNUM = ?";
        
        $result = dataQuery($query, [$requestBody, $IdNum]);
        
        if (!$result) {
            die("Error updating resource review");
        }
    }
} else {
    // Admin updates
    if (!empty($updateList)) {
        // Build the update query dynamically
        $setClause = "";
        $params = [];
        
        foreach ($updateList as $key => $value) {
            if ($setClause !== "") {
                $setClause .= ", ";
            }
            $setClause .= "$key = ?";
            $params[] = $value;
        }
        
        $params[] = $IdNum; // Add IdNum for WHERE clause
        
        $query = "UPDATE resource 
                 SET $setClause, EDATE = NOW() 
                 WHERE IDNum = ?";
        
        $result = dataQuery($query, $params);
        
        if (!$result) {
            die("Error updating resource");
        }
    }
    
    // Process deletions if any
    if (!empty($deleteUpdateList)) {
        $setClause = "";
        $params = [];
        
        foreach ($deleteUpdateList as $key => $value) {
            if ($setClause !== "") {
                $setClause .= ", ";
            }
            $setClause .= "$key = ?";
            $params[] = $value;
        }
        
        $params[] = $IdNum;
        
        $query = "UPDATE resource 
                 SET $setClause, EDATE = NOW() 
                 WHERE IDNum = ?";
        
        $result = dataQuery($query, $params);
        
        if (!$result) {
            die("Error processing deletions");
        }
    }
    
    // Log the approval
    $query = "INSERT INTO resourceEditLog (UserName, resourceIDNUM, Action) 
             VALUES (?, ?, 'Approved')";
    
    $result = dataQuery($query, [$VolunteerID, $IdNum]);
    
    if (!$result) {
        die("Error logging resource edit");
    }
    
    // Delete from review table
    $query = "DELETE FROM resourceReview WHERE IDNum = ?";
    
    $result = dataQuery($query, [$IdNum]);
    
    if (!$result) {
        die("Error deleting resource review");
    }
}

echo "OK";
?>
