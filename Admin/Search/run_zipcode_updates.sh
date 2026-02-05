#!/bin/bash

# Script to update ZipCodesPostal table with new zip codes
# Using correct database credentials

echo "=== Updating ZipCodesPostal Table ==="
echo ""

# Step 1: Expand field lengths
echo "Step 1: Expanding LocationText and Location field lengths..."
mysql -u dgqtkqjasj -p'CXpskz9QXQ' dgqtkqjasj < /home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html/Admin/Search/alter_locationtext_field.sql

if [ $? -eq 0 ]; then
    echo "✓ Field lengths expanded successfully"
else
    echo "✗ Error expanding field lengths"
    exit 1
fi

echo ""

# Step 2: Insert missing zip codes
echo "Step 2: Inserting 227 missing zip codes..."
mysql -u dgqtkqjasj -p'CXpskz9QXQ' dgqtkqjasj < /home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html/Admin/Search/insert_missing_zipcodes_safe.sql

if [ $? -eq 0 ]; then
    echo "✓ Missing zip codes inserted successfully"
else
    echo "✗ Error inserting zip codes"
    exit 1
fi

echo ""
echo "=== Update Complete ==="