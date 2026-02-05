<?php


require_once('../../private_html/db_login.php');
session_start();
if (@$_SESSION["auth"] != "yes") {
    die("Unauthorized");
}

// Release session lock after authentication check
session_write_close();

// Clear existing data
$clearImport = "DELETE FROM ResourcesImport";
$clearResources = "DELETE FROM resources";

if (!dataQuery($clearImport) || !dataQuery($clearResources)) {
    header("Location: index.php?error=clear_failed");
    exit();
}

// Load data from CSV
$loadData = "LOAD DATA LOCAL INFILE '" . $_FILES["Fields"]["tmp_name"] . "'
    INTO TABLE ResourcesImport 
    FIELDS TERMINATED BY ',' 
    ENCLOSED BY '\"' 
    LINES TERMINATED BY '\r'";

if (!dataQuery($loadData)) {
    header("Location: index.php?error=load_failed");
    exit();
}

// Insert processed data into resources
$insertData = "INSERT INTO resources (
    idnum, edate, name, name2, contact, address1, address2, 
    city, state, zip, Region, type1, type2, type3, type4, 
    phone, Ext, fax, hotline, internet, gender, cnational, 
    wwweb, descript, give_addr, latitude, longitude
) 
SELECT 
    idnum,
    CONCAT(RIGHT(edate,4),'-',LEFT(edate,2),'-',MID(edate,4,2)),
    name, name2, contact, address1, address2, city, state, zip, 
    Region, type1, type2, type3, type4, phone, Ext, fax, 
    hotline, internet, gender, cnational, wwweb, descript, 
    give_addr, latitude, longitude 
FROM ResourcesImport";

if (!dataQuery($insertData)) {
    header("Location: index.php?error=insert_failed");
    exit();
}

// Update coordinates
$updateCoords = "UPDATE resources 
    SET 
        x = COS(RADIANS(Longitude)) * COS(RADIANS(Latitude)),
        y = SIN(RADIANS(Longitude)) * COS(RADIANS(Latitude)),
        z = SIN(RADIANS(Latitude)),
        linkablezip = LEFT(zip,5)";

if (!dataQuery($updateCoords)) {
    header("Location: index.php?error=update_failed");
    exit();
}

header("Location: index.php");
exit();
?>
