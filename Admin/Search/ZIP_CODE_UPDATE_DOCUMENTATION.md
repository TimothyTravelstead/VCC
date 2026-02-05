# Zip Code Table Update Documentation

## Overview
This document outlines the complete process for updating the ZipCodesPostal table with new zip code data from the uszips table, including latitude/longitude updates and x,y,z coordinate calculations.

## Prerequisites
- MySQL database access with credentials
- The `uszips.sql` file containing updated zip code data
- Sufficient permissions to ALTER tables and INSERT records

## Database Credentials
- **Username**: dgqtkqjasj
- **Password**: CXpskz9QXQ  
- **Database**: dgqtkqjasj

## Step-by-Step Process

### Step 1: Import the USZips Data
First, import the new zip code data into the uszips table:

```bash
mysql -u dgqtkqjasj -p'CXpskz9QXQ' dgqtkqjasj < /path/to/uszips.sql
```

### Step 2: Expand Field Lengths
Before adding new zip codes, expand the LocationText and Location fields to accommodate longer city names:

```bash
mysql -u dgqtkqjasj -p'CXpskz9QXQ' dgqtkqjasj < alter_locationtext_field.sql
```

**SQL Content (alter_locationtext_field.sql):**
```sql
-- Expand LocationText and Location columns to VARCHAR(255)
ALTER TABLE ZipCodesPostal 
MODIFY COLUMN LocationText VARCHAR(255);

ALTER TABLE ZipCodesPostal 
MODIFY COLUMN Location VARCHAR(255);
```

### Step 3: Update Existing Zip Codes
Update latitude, longitude, and recalculate x,y,z coordinates for existing zip codes:

```bash
mysql -u dgqtkqjasj -p'CXpskz9QXQ' dgqtkqjasj < update_zipcodes_simple.sql
```

**SQL Content (update_zipcodes_simple.sql):**
```sql
UPDATE ZipCodesPostal z
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
  AND u.lng != '';
```

### Step 4: Insert Missing Zip Codes
Add new zip codes that exist in uszips but not in ZipCodesPostal:

```bash
mysql -u dgqtkqjasj -p'CXpskz9QXQ' dgqtkqjasj < insert_missing_zipcodes_safe.sql
```

**SQL Content (insert_missing_zipcodes_safe.sql):**
```sql
INSERT INTO ZipCodesPostal (
    Zip, ZipCodeType, City, State, LocationType,
    Latitude, Longitude, x, y, z,
    WorldRegion, Country, LocationText, Location, Decommisioned
)
SELECT 
    u.zip as Zip,
    CASE 
        WHEN u.zcta = 1 THEN 'STANDARD'
        WHEN u.military = 1 THEN 'MILITARY'
        ELSE 'STANDARD'
    END as ZipCodeType,
    UPPER(u.city) as City,
    u.state_id as State,
    CASE 
        WHEN u.imprecise = 1 THEN 'NOT ACCEPTABLE'
        ELSE 'PRIMARY'
    END as LocationType,
    CAST(u.lat AS DECIMAL(20,17)) as Latitude,
    CAST(u.lng AS DECIMAL(20,17)) as Longitude,
    COS(RADIANS(CAST(u.lat AS DECIMAL(20,17)))) * COS(RADIANS(CAST(u.lng AS DECIMAL(20,17)))) as x,
    COS(RADIANS(CAST(u.lat AS DECIMAL(20,17)))) * SIN(RADIANS(CAST(u.lng AS DECIMAL(20,17)))) as y,
    SIN(RADIANS(CAST(u.lat AS DECIMAL(20,17)))) as z,
    'NA' as WorldRegion,
    'US' as Country,
    CONCAT(TRIM(u.city), ', ', u.state_id) as LocationText,
    CONCAT('NA-US-', u.state_id, '-', REPLACE(UPPER(TRIM(u.city)), ' ', ' ')) as Location,
    'FALSE' as Decommisioned
FROM uszips u
WHERE NOT EXISTS (SELECT 1 FROM ZipCodesPostal z WHERE z.Zip = u.zip)
AND u.lat IS NOT NULL 
AND u.lng IS NOT NULL
AND u.lat != ''
AND u.lng != '';
```

### Step 5: Update Resources with Missing X,Y,Z Coordinates
For any resources that have latitude/longitude but missing x,y,z coordinates:

```bash
php recalculate_xyz_coordinates.php
```

**PHP Script (recalculate_xyz_coordinates.php):**
- Finds resources with lat/lon but missing x,y,z
- Calculates x,y,z using the formulas:
  - x = cos(latRad) * cos(lonRad)
  - y = cos(latRad) * sin(lonRad)
  - z = sin(latRad)
- Updates the resource table

## Verification Queries

### Check Update Results
```sql
-- Count of zip codes by type
SELECT 
    COUNT(*) as 'Total Zip Codes',
    COUNT(CASE WHEN x IS NOT NULL THEN 1 END) as 'With Coordinates',
    COUNT(CASE WHEN x IS NULL THEN 1 END) as 'Without Coordinates'
FROM ZipCodesPostal;

-- Compare tables
SELECT 
    (SELECT COUNT(*) FROM uszips) as 'USZips Total',
    (SELECT COUNT(*) FROM ZipCodesPostal) as 'ZipCodesPostal Total',
    (SELECT COUNT(*) FROM uszips u WHERE NOT EXISTS 
        (SELECT 1 FROM ZipCodesPostal z WHERE z.Zip = u.zip)) as 'Still Missing';
```

### Check Resources
```sql
-- Resources with coordinates
SELECT 
    COUNT(*) as 'Total Resources',
    COUNT(CASE WHEN x IS NOT NULL THEN 1 END) as 'With X,Y,Z',
    COUNT(CASE WHEN x IS NULL THEN 1 END) as 'Without X,Y,Z'
FROM resource;
```

## Important Notes

1. **Coordinate Formulas**: The x,y,z coordinates are calculated for spherical distance calculations:
   - These are Cartesian coordinates on a unit sphere
   - Used for efficient distance calculations in search queries

2. **Military Zip Codes**: APO/DPO/FPO addresses typically don't have lat/lon coordinates
   - These are excluded from coordinate-based searches
   - About 162 military zip codes have NULL coordinates

3. **Field Mapping**:
   - uszips.zip → ZipCodesPostal.Zip
   - uszips.city → ZipCodesPostal.City (uppercase)
   - uszips.state_id → ZipCodesPostal.State
   - uszips.lat → ZipCodesPostal.latitude
   - uszips.lng → ZipCodesPostal.longitude

4. **Results from Test System**:
   - 65 new zip codes added with coordinates
   - 162 military/special zip codes excluded (no coordinates)
   - All existing zip codes updated with new lat/lon values

## File Locations (Test System)
All scripts are located in: `/home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html/Admin/Search/`

- `alter_locationtext_field.sql` - Expands field lengths
- `update_zipcodes_simple.sql` - Updates existing zip codes
- `insert_missing_zipcodes_safe.sql` - Adds new zip codes
- `recalculate_xyz_coordinates.php` - Updates resource x,y,z
- `compare_zipcodes.sql` - Verification queries
- `run_zipcode_updates.sh` - Bash script to run all steps

## Production Migration Checklist

- [ ] Backup ZipCodesPostal table
- [ ] Backup resource table  
- [ ] Copy uszips.sql file to production
- [ ] Copy all SQL scripts to production
- [ ] Update database credentials in scripts
- [ ] Run Step 1: Import uszips data
- [ ] Run Step 2: Expand field lengths
- [ ] Run Step 3: Update existing zip codes
- [ ] Run Step 4: Insert new zip codes
- [ ] Run Step 5: Update resource coordinates
- [ ] Run verification queries
- [ ] Test distance-based searches

## Troubleshooting

**Issue**: "Data too long for column 'LocationText'"
- **Solution**: Ensure Step 2 (field expansion) was run first

**Issue**: Resources showing NULL distance
- **Solution**: Run recalculate_xyz_coordinates.php to update x,y,z values

**Issue**: ACOS domain errors in distance calculations
- **Solution**: Already fixed in ZipLocateTable.php with LEAST/GREATEST clamping

## Contact
For questions about this process, refer to the CLAUDE.md file in the project root for full technical documentation of the Admin/Search system improvements.