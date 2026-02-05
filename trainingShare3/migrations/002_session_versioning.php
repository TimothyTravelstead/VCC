<?php
/**
 * Migration 002: Add Session Versioning
 *
 * Adds session_version to training_rooms and training_signals tables.
 * This eliminates stale signal problems structurally - signals from old
 * sessions are automatically filtered out by version mismatch.
 *
 * Usage: php 002_session_versioning.php
 */

require_once(__DIR__ . '/../../../private_html/db_login.php');

echo "Migration 002: Session Versioning\n";
echo "==================================\n\n";

/**
 * Check if a column exists in a table
 */
function columnExists($table, $column) {
    $result = dataQuery("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return !empty($result);
}

/**
 * Check if an index exists in a table
 */
function indexExists($table, $indexName) {
    $result = dataQuery("SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'");
    return !empty($result);
}

$success = 0;
$failed = 0;

// Migration 1: Add session_version to training_rooms
echo "1. Add session_version to training_rooms... ";
if (columnExists('training_rooms', 'session_version')) {
    echo "SKIPPED (already exists)\n";
    $success++;
} else {
    $result = dataQuery("
        ALTER TABLE training_rooms
        ADD COLUMN session_version CHAR(32) NOT NULL DEFAULT ''
        COMMENT 'Unique version per session - regenerated when trainer creates room'
    ");
    if (is_array($result) && isset($result['error'])) {
        echo "FAILED: {$result['message']}\n";
        $failed++;
    } else {
        echo "OK\n";
        $success++;
    }
}

// Migration 2: Add session_version to training_signals
echo "2. Add session_version to training_signals... ";
if (columnExists('training_signals', 'session_version')) {
    echo "SKIPPED (already exists)\n";
    $success++;
} else {
    $result = dataQuery("
        ALTER TABLE training_signals
        ADD COLUMN session_version CHAR(32) NOT NULL DEFAULT ''
        COMMENT 'Version from room at signal creation time'
    ");
    if (is_array($result) && isset($result['error'])) {
        echo "FAILED: {$result['message']}\n";
        $failed++;
    } else {
        echo "OK\n";
        $success++;
    }
}

// Migration 3: Add index for efficient version filtering
echo "3. Add index for version filtering... ";
if (indexExists('training_signals', 'idx_recipient_version')) {
    echo "SKIPPED (already exists)\n";
    $success++;
} else {
    // Only add index if column exists
    if (!columnExists('training_signals', 'session_version')) {
        echo "SKIPPED (session_version column doesn't exist yet)\n";
    } else {
        $result = dataQuery("
            ALTER TABLE training_signals
            ADD INDEX idx_recipient_version (recipient_id, session_version, delivered, created_at)
        ");
        if (is_array($result) && isset($result['error'])) {
            echo "FAILED: {$result['message']}\n";
            $failed++;
        } else {
            echo "OK\n";
            $success++;
        }
    }
}

echo "\n==================================\n";
echo "Migration complete: $success succeeded, $failed failed\n";

// Verify columns exist
echo "\nVerifying schema:\n";
echo "  training_rooms.session_version: " . (columnExists('training_rooms', 'session_version') ? "EXISTS" : "MISSING") . "\n";
echo "  training_signals.session_version: " . (columnExists('training_signals', 'session_version') ? "EXISTS" : "MISSING") . "\n";

// Check index
echo "  idx_recipient_version index: " . (indexExists('training_signals', 'idx_recipient_version') ? "EXISTS" : "MISSING") . "\n";

echo "\nDone.\n";
