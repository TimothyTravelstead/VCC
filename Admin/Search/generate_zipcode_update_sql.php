<?php
/**
 * Script to generate MySQL UPDATE command for updating ZipCodesPostal from uszips table
 * This creates a single SQL command that can be run directly in MySQL
 */

$_SERVER['HTTP_HOST'] = 'localhost';
require_once('../../../private_html/db_login.php');

echo "=== Generating MySQL UPDATE Command for ZipCodesPostal ===" . PHP_EOL;
echo "Starting at: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// First, check if uszips table exists
$checkTable = "SHOW TABLES LIKE 'uszips'";
$tableExists = dataQuery($checkTable);

if (!$tableExists) {
    echo "ERROR: uszips table does not exist." . PHP_EOL;
    echo "Please import uszips.sql first using:" . PHP_EOL;
    echo "mysql -u dgqtkqjasj -p'CVO4t-vkC4' dgqtkqjasj < /home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html/uszips.sql" . PHP_EOL;
    exit;
}

// Generate the SQL file
$sqlFile = '/home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html/Admin/Search/update_zipcodes.sql';

// Open file for writing
$fp = fopen($sqlFile, 'w');

if (!$fp) {
    echo "ERROR: Could not create SQL file." . PHP_EOL;
    exit;
}

// Write header comments
fwrite($fp, "-- =================================================\n");
fwrite($fp, "-- Update ZipCodesPostal with uszips data\n");
fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
fwrite($fp, "-- =================================================\n\n");

// Add safety checks
fwrite($fp, "-- Check that uszips table exists\n");
fwrite($fp, "SELECT COUNT(*) as 'USZips Records Available' FROM uszips;\n\n");

// Create backup table (optional)
fwrite($fp, "-- Create backup of current data (optional - uncomment to use)\n");
fwrite($fp, "-- CREATE TABLE ZipCodesPostal_backup_" . date('Ymd') . " AS SELECT * FROM ZipCodesPostal;\n\n");

// Generate the main UPDATE statement using JOIN
fwrite($fp, "-- Main update statement\n");
fwrite($fp, "-- This updates latitude, longitude and recalculates x, y, z coordinates\n\n");

// Using a multi-table UPDATE syntax
$updateSQL = "UPDATE ZipCodesPostal z
INNER JOIN uszips u ON z.Zip = u.zip
SET 
    z.latitude = CAST(u.lat AS DECIMAL(20,17)),
    z.longitude = CAST(u.lng AS DECIMAL(20,17)),
    z.x = COS(RADIANS(CAST(u.lat AS DECIMAL(20,17)))) * COS(RADIANS(CAST(u.lng AS DECIMAL(20,17)))),
    z.y = COS(RADIANS(CAST(u.lat AS DECIMAL(20,17)))) * SIN(RADIANS(CAST(u.lng AS DECIMAL(20,17)))),
    z.z = SIN(RADIANS(CAST(u.lat AS DECIMAL(20,17))))
WHERE u.lat IS NOT NULL 
  AND u.lng IS NOT NULL
  AND u.lat != ''
  AND u.lng != '';";

fwrite($fp, $updateSQL . "\n\n");

// Add verification queries
fwrite($fp, "-- Verification queries\n");
fwrite($fp, "SELECT 'Records Updated:' as Status, ROW_COUNT() as Count;\n\n");

fwrite($fp, "-- Check sample of updated records\n");
fwrite($fp, "SELECT \n");
fwrite($fp, "    z.Zip,\n");
fwrite($fp, "    z.latitude,\n");
fwrite($fp, "    z.longitude,\n");
fwrite($fp, "    z.x,\n");
fwrite($fp, "    z.y,\n");
fwrite($fp, "    z.z,\n");
fwrite($fp, "    u.city,\n");
fwrite($fp, "    u.state_id\n");
fwrite($fp, "FROM ZipCodesPostal z\n");
fwrite($fp, "INNER JOIN uszips u ON z.Zip = u.zip\n");
fwrite($fp, "WHERE z.x IS NOT NULL\n");
fwrite($fp, "LIMIT 10;\n\n");

// Statistics query
fwrite($fp, "-- Statistics after update\n");
fwrite($fp, "SELECT \n");
fwrite($fp, "    COUNT(*) as 'Total ZipCodes',\n");
fwrite($fp, "    COUNT(CASE WHEN x IS NOT NULL THEN 1 END) as 'With X,Y,Z Coordinates',\n");
fwrite($fp, "    COUNT(CASE WHEN x IS NULL THEN 1 END) as 'Missing X,Y,Z Coordinates'\n");
fwrite($fp, "FROM ZipCodesPostal;\n");

fclose($fp);

echo "SQL file generated: {$sqlFile}" . PHP_EOL . PHP_EOL;

// Also generate a simpler version for step-by-step execution
$simpleFile = '/home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html/Admin/Search/update_zipcodes_simple.sql';
$fp2 = fopen($simpleFile, 'w');

fwrite($fp2, "-- Simple version - just the UPDATE command\n");
fwrite($fp2, "-- Run this after importing uszips.sql\n\n");
fwrite($fp2, $updateSQL . "\n");

fclose($fp2);

echo "Simple SQL file generated: {$simpleFile}" . PHP_EOL . PHP_EOL;

// Display the commands to run
echo "=== How to Execute ===" . PHP_EOL . PHP_EOL;

echo "Step 1: Import the uszips data (if not already done):" . PHP_EOL;
echo "  mysql -u dgqtkqjasj -p'CVO4t-vkC4' dgqtkqjasj < /home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html/uszips.sql" . PHP_EOL . PHP_EOL;

echo "Step 2: Run the update (choose one option):" . PHP_EOL . PHP_EOL;

echo "Option A - Full script with verification:" . PHP_EOL;
echo "  mysql -u dgqtkqjasj -p'CVO4t-vkC4' dgqtkqjasj < {$sqlFile}" . PHP_EOL . PHP_EOL;

echo "Option B - Simple update only:" . PHP_EOL;
echo "  mysql -u dgqtkqjasj -p'CVO4t-vkC4' dgqtkqjasj < {$simpleFile}" . PHP_EOL . PHP_EOL;

echo "Option C - Run directly in MySQL console:" . PHP_EOL;
echo "  mysql -u dgqtkqjasj -p'CVO4t-vkC4' dgqtkqjasj" . PHP_EOL;
echo "  Then paste:" . PHP_EOL . PHP_EOL;
echo $updateSQL . PHP_EOL . PHP_EOL;

// Display the actual UPDATE command for review
echo "=== The Generated UPDATE Command ===" . PHP_EOL;
echo $updateSQL . PHP_EOL;

echo PHP_EOL . "Completed at: " . date('Y-m-d H:i:s') . PHP_EOL;
?>