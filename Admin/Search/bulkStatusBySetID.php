<?php


require_once('../../../private_html/db_login.php');
session_start();
ini_set('memory_limit', '2G');


$bulkSetID = $_REQUEST["bulkSetID"];

// First query refactored to use dataQuery with parameter binding
$query = "SELECT resourceID 
          FROM bulkLog 
          LEFT JOIN Resource ON (bulkLog.ResourceID = resource.IDNUM) 
          WHERE bulkSetID = :bulkSetID 
          AND Status = 'Selected' 
          ORDER BY resource.edate ASC";

// Main query refactored to use dataQuery with parameter binding
$query6 = "SELECT resource.edate as Date, 
                  resourceID, 
                  Name, 
                  emailAddress, 
                  Status 
           FROM bulkLog 
           LEFT JOIN Resource ON (bulkLog.ResourceID = resource.IDNUM) 
           WHERE bulkSetID = :bulkSetID 
           ORDER BY resource.edate ASC";

$params = [':bulkSetID' => $bulkSetID];
$result6 = dataQuery($query6, $params);

$table = "<table><style>th, td { width: 150px; text-align:center; border-bottom: 1px dotted black; }</style>";
$table .= "<tr><th>DATE</th><th>IDNUM</th><th>NAME</th><th>EMAIL ADDRESS</th><th>STATUS</th></tr>";

if ($result6) {
    foreach ($result6 as $item) {
        $table .= "<tr>";
        $table .= "<td>" . htmlspecialchars($item->Date) . "</td>";
        $table .= "<td>" . htmlspecialchars($item->resourceID) . "</td>";
        $table .= "<td>" . htmlspecialchars($item->Name) . "</td>";
        $table .= "<td>" . htmlspecialchars($item->emailAddress) . "</td>";
        $table .= "<td>" . htmlspecialchars($item->Status) . "</td>";
        $table .= "</tr>";
    }
}

$table .= "</table>";

echo $table;
?>
