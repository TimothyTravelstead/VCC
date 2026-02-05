<?php
/**
 * Run Training System Database Migration
 *
 * Usage: php run_migration.php
 *
 * Creates tables for database-based signaling, state machine, and mute management.
 */

require_once(__DIR__ . '/../../../private_html/db_login.php');

echo "Training System Database Migration\n";
echo "===================================\n\n";

// Table definitions (without DELIMITER syntax which doesn't work in PDO)
$tables = [
    'training_rooms' => "
        CREATE TABLE IF NOT EXISTS training_rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id VARCHAR(100) NOT NULL UNIQUE COMMENT 'Trainer username used as room identifier',
            trainer_id VARCHAR(50) NOT NULL COMMENT 'Username of the trainer',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status ENUM('active', 'inactive') DEFAULT 'active',
            INDEX idx_trainer (trainer_id),
            INDEX idx_status (status, last_activity),
            INDEX idx_room_status (room_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'training_participants' => "
        CREATE TABLE IF NOT EXISTS training_participants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id VARCHAR(100) NOT NULL COMMENT 'References training_rooms.room_id',
            participant_id VARCHAR(50) NOT NULL COMMENT 'Username of the participant',
            participant_role ENUM('trainer', 'trainee') NOT NULL,
            call_sid VARCHAR(50) DEFAULT NULL COMMENT 'Twilio CallSid for conference participant',
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_connected BOOLEAN DEFAULT TRUE COMMENT 'WebRTC connection status',
            UNIQUE KEY uk_room_participant (room_id, participant_id),
            INDEX idx_room (room_id),
            INDEX idx_participant (participant_id),
            INDEX idx_last_seen (last_seen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'training_signals' => "
        CREATE TABLE IF NOT EXISTS training_signals (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            room_id VARCHAR(100) NOT NULL COMMENT 'Room where signal originated',
            sender_id VARCHAR(50) NOT NULL COMMENT 'Username of signal sender',
            recipient_id VARCHAR(50) DEFAULT NULL COMMENT 'Target participant (NULL = broadcast to room)',
            signal_type VARCHAR(50) NOT NULL COMMENT 'Type: offer, answer, ice-candidate, etc.',
            signal_data JSON NOT NULL COMMENT 'WebRTC signaling data',
            created_at TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) COMMENT 'Millisecond precision for ordering',
            delivered BOOLEAN DEFAULT FALSE COMMENT 'Has recipient received this signal?',
            INDEX idx_recipient_pending (recipient_id, delivered, created_at),
            INDEX idx_room (room_id),
            INDEX idx_cleanup (created_at),
            INDEX idx_sender (sender_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'training_session_state' => "
        CREATE TABLE IF NOT EXISTS training_session_state (
            trainer_id VARCHAR(50) PRIMARY KEY COMMENT 'Trainer username (one state per trainer)',
            session_state ENUM('INITIALIZING', 'CONNECTED', 'ON_CALL', 'RECONNECTING', 'DISCONNECTED')
                DEFAULT 'INITIALIZING' COMMENT 'Current session state',
            active_controller VARCHAR(50) NOT NULL COMMENT 'Who currently has call control',
            external_call_active BOOLEAN DEFAULT FALSE COMMENT 'Is there an external caller in conference?',
            external_call_sid VARCHAR(50) DEFAULT NULL COMMENT 'CallSid of external caller',
            conference_sid VARCHAR(50) DEFAULT NULL COMMENT 'Twilio Conference SID',
            state_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_state (session_state),
            INDEX idx_controller (active_controller)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'training_mute_state' => "
        CREATE TABLE IF NOT EXISTS training_mute_state (
            trainer_id VARCHAR(50) NOT NULL COMMENT 'Trainer username (conference identifier)',
            participant_id VARCHAR(50) NOT NULL COMMENT 'Participant username',
            is_muted BOOLEAN DEFAULT FALSE COMMENT 'Should this participant be muted?',
            call_sid VARCHAR(50) DEFAULT NULL COMMENT 'Participant Twilio CallSid for API calls',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            mute_reason VARCHAR(100) DEFAULT NULL COMMENT 'Why muted: external_call, control_change, etc.',
            PRIMARY KEY (trainer_id, participant_id),
            INDEX idx_trainer (trainer_id),
            INDEX idx_participant (participant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'training_events_log' => "
        CREATE TABLE IF NOT EXISTS training_events_log (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            trainer_id VARCHAR(50) NOT NULL,
            event_type VARCHAR(50) NOT NULL COMMENT 'join, leave, call_start, call_end, control_change, etc.',
            event_data JSON DEFAULT NULL COMMENT 'Additional event details',
            participant_id VARCHAR(50) DEFAULT NULL COMMENT 'Who triggered the event',
            created_at TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
            INDEX idx_trainer_time (trainer_id, created_at),
            INDEX idx_event_type (event_type),
            INDEX idx_cleanup (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

$success = 0;
$failed = 0;

foreach ($tables as $tableName => $createSql) {
    echo "Creating table: $tableName... ";

    $result = dataQuery($createSql);

    if (is_array($result) && isset($result['error'])) {
        echo "FAILED\n";
        echo "  Error: {$result['message']}\n";
        $failed++;
    } else {
        echo "OK\n";
        $success++;
    }
}

// Create the cleanup stored procedure
echo "\nCreating cleanup procedure... ";
$procedureSql = "
    CREATE PROCEDURE IF NOT EXISTS cleanup_training_signals()
    BEGIN
        DELETE FROM training_signals
        WHERE delivered = TRUE
        AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);

        DELETE FROM training_signals
        WHERE delivered = FALSE
        AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE);

        DELETE FROM training_events_log
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

        UPDATE training_rooms
        SET status = 'inactive'
        WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        AND status = 'active';

        DELETE FROM training_participants
        WHERE last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE);
    END
";

$result = dataQuery($procedureSql);
if (is_array($result) && isset($result['error'])) {
    echo "SKIPPED (may already exist or DELIMITER not supported)\n";
} else {
    echo "OK\n";
}

echo "\n===================================\n";
echo "Migration complete: $success tables created, $failed failed\n";

// Verify tables exist
echo "\nVerifying tables:\n";
$verifyTables = ['training_rooms', 'training_participants', 'training_signals',
                 'training_session_state', 'training_mute_state', 'training_events_log'];

foreach ($verifyTables as $table) {
    $check = dataQuery("SHOW TABLES LIKE ?", [$table]);
    $exists = !empty($check);
    echo "  $table: " . ($exists ? "EXISTS" : "MISSING") . "\n";
}

echo "\nDone.\n";
