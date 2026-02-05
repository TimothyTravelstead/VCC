<?php


require_once('../../private_html/db_login.php');
session_start();
if ($_SESSION['auth'] != 'yes') {
    // die("Unauthorized");
}


$UserID = $_SESSION['UserID'] ?? 'Travelstead';
$admin = $_SESSION['editResources'] != "user";

function getResource($Counter, $IDNUM, $admin) {
    // Get main resource data
    $query = "SELECT * FROM resource WHERE Counter = ?";
    $result = dataQuery($query, [$Counter]);
    
    if (!$result || empty($result)) {
        return false;
    }
    
    // Convert the first result object to our resource array
    $resource = (array)$result[0];
    
    // Ensure proper character encoding for all values
    // Instead of utf8_encode, we'll handle the encoding based on the source
    array_walk($resource, function(&$value) {
        if (!is_null($value) && !mb_check_encoding($value, 'UTF-8')) {
            // If the value is not valid UTF-8, try to detect and convert from another encoding
            $detectedEncoding = mb_detect_encoding($value, ['ASCII', 'ISO-8859-1', 'UTF-8', 'Windows-1252'], true);
            if ($detectedEncoding) {
                $value = mb_convert_encoding($value, 'UTF-8', $detectedEncoding);
            } else {
                // If we can't detect the encoding, assume it's ISO-8859-1 (Latin1)
                $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }
        }
    });
    
    if (!$admin) {
        // Insert review record for non-admin users
        $query = "INSERT INTO resourceReview (Counter, AddedTime, IDNUM) 
                 VALUES (?, NOW(), ?)";
        $result = dataQuery($query, [$Counter, $IDNUM]);
        
        if (!$result) {
            die("Error: Failed to insert resource review");
        }
    } else {
        // Get review data for admin users
        $query = "SELECT * FROM resourceReview WHERE Counter = ?";
        $result = dataQuery($query, [$Counter]);
        
        if ($result) {
            $resource["updateData"] = (array)$result[0];
            // Handle proper character encoding for update data
            array_walk($resource["updateData"], function(&$value) {
                if (!is_null($value) && !mb_check_encoding($value, 'UTF-8')) {
                    $detectedEncoding = mb_detect_encoding($value, ['ASCII', 'ISO-8859-1', 'UTF-8', 'Windows-1252'], true);
                    if ($detectedEncoding) {
                        $value = mb_convert_encoding($value, 'UTF-8', $detectedEncoding);
                    } else {
                        $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                    }
                }
            });
            
            // Get editor information
            $query = "SELECT firstname, lastname 
                     FROM Volunteers 
                     WHERE username = (
                         SELECT username 
                         FROM resourceEditLog 
                         WHERE resourceIDNUM = ? 
                         AND Action != 'Email' 
                         ORDER BY id DESC 
                         LIMIT 1
                     )";
            $result = dataQuery($query, [$IDNUM]);
            
            if ($result) {
                $editor = $result[0];
                $resource["updateData"]["editUser"] = $editor->firstname . " " . $editor->lastname;
            }
        }
    }
    
    // Ensure JSON is encoded as UTF-8
    return json_encode($resource, JSON_UNESCAPED_UNICODE);
}

dataQuery("SET NAMES utf8mb4");

if ($admin) {
    // Admin flow: Get resources with modified reviews
    $query = "SELECT resourceReview.counter, resourceReview.IDNUM 
             FROM resourceReview 
             WHERE ModifiedTime IS NOT NULL 
             ORDER BY ModifiedTime ASC 
             LIMIT 1";
    
    $result = dataQuery($query);
    
    if (!$result || empty($result)) {
        echo "None";
        return;
    }
    
    $record = $result[0];
    $finalRecord = getResource($record->counter, $record->IDNUM, $admin);
    echo $finalRecord;
    return;
} else {
    // Non-admin flow: Get resources needing review
    $query = "SELECT DATE_ADD(resource.edate, INTERVAL 90 day) as extended_date
             FROM resource 
             LEFT JOIN resourceReview ON (resource.Counter = resourceReview.counter) 
             WHERE resourceReview.counter IS NULL 
             AND resource.Closed = 'N' 
             AND (resource.WWWEB != '' OR resource.WWWEB2 != '' OR resource.WWWEB3 != '') 
             ORDER BY resource.EDATE ASC, resource.IDNUM ASC 
             LIMIT 1";
             
    $result = dataQuery($query);
    
    if ($result) {
        $eDate = $result[0]->extended_date;
        
        $query = "SELECT resource.counter, resource.IDNUM 
                 FROM resource 
                 LEFT JOIN resourceReview ON (resource.Counter = resourceReview.counter) 
                 WHERE resource.edate > ? 
                 AND resourceReview.counter IS NULL 
                 AND resource.Closed = 'N' 
                 AND (resource.WWWEB != '' OR resource.WWWEB2 != '' OR resource.WWWEB3 != '') 
                 ORDER BY resource.EDATE ASC, resource.IDNUM ASC 
                 LIMIT 1";
                 
        $result = dataQuery($query, [$eDate]);
        
        if ($result) {
            $record = $result[0];
            $finalRecord = getResource($record->counter, $record->IDNUM, $admin);
            echo $finalRecord;
        }
    }
}
?>
