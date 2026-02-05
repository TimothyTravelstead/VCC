<?php
/**
 * initializeRedisCache.php - Initialize Redis cache with current database state
 *
 * Run this script:
 * 1. After deploying the Redis polling system
 * 2. After Redis restart
 * 3. If cache becomes stale
 *
 * Usage:
 *   php initializeRedisCache.php
 *   OR
 *   Visit in browser (requires admin session)
 *
 * @author Claude Code
 * @date December 20, 2025
 */

// CLI mode doesn't need session
$isCLI = (php_sapi_name() === 'cli');

if (!$isCLI) {
    require_once('../private_html/db_login.php');
    session_start();

    // Require admin for web access
    $userID = $_SESSION['UserID'] ?? null;
    $isAdmin = isset($_SESSION['AdminUser']) && $_SESSION['AdminUser'];

    if (!$userID || !$isAdmin) {
        http_response_code(403);
        die("Access denied. Admin authentication required.");
    }

    session_write_close();
    header('Content-Type: text/plain');
} else {
    require_once(dirname(__FILE__) . '/../private_html/db_login.php');
}

echo "=== Redis Cache Initialization ===\n\n";

// Check Redis connection
echo "1. Checking Redis connection...\n";
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379, 2.0);
    $pong = $redis->ping();
    echo "   Redis: Connected (PING: $pong)\n\n";
} catch (Exception $e) {
    echo "   ERROR: Redis connection failed - " . $e->getMessage() . "\n";
    exit(1);
}

// Initialize user list cache
echo "2. Refreshing user list cache...\n";
try {
    require_once(__DIR__ . '/lib/VCCFeedPublisher.php');
    $publisher = new VCCFeedPublisher(true); // Enable debug mode

    if (!$publisher->isEnabled()) {
        echo "   ERROR: VCCFeedPublisher failed to connect to Redis\n";
        exit(1);
    }

    $result = $publisher->refreshUserListCache();
    if ($result) {
        echo "   User list cache: REFRESHED\n";

        // Verify cache contents
        $cached = $redis->get('vcc:userlist');
        if ($cached) {
            $data = json_decode($cached, true);
            $userCount = count($data['users'] ?? []);
            $timestamp = $data['timestamp'] ?? 0;
            echo "   Users cached: $userCount\n";
            echo "   Cache timestamp: " . date('Y-m-d H:i:s', $timestamp) . "\n";
        }
    } else {
        echo "   WARNING: refreshUserListCache returned false\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Clear any stale ringing states
echo "3. Clearing stale ringing states...\n";
try {
    $keys = $redis->keys('vcc:ringing:*');
    $count = count($keys);
    if ($count > 0) {
        foreach ($keys as $key) {
            $redis->del($key);
        }
        echo "   Cleared $count stale ringing keys\n";
    } else {
        echo "   No stale ringing states found\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Clear any stale user event queues
echo "4. Clearing stale event queues...\n";
try {
    $keys = $redis->keys('vcc:user:*:events');
    $count = count($keys);
    if ($count > 0) {
        foreach ($keys as $key) {
            $redis->del($key);
        }
        echo "   Cleared $count stale event queues\n";
    } else {
        echo "   No stale event queues found\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Summary
echo "=== Initialization Complete ===\n\n";
echo "Redis cache is now initialized.\n";
echo "To test: togglePolling(true) in browser console\n";
echo "\n";

// Show current Redis keys
echo "Current vcc:* keys in Redis:\n";
try {
    $keys = $redis->keys('vcc:*');
    if (empty($keys)) {
        echo "   (none)\n";
    } else {
        foreach ($keys as $key) {
            $type = $redis->type($key);
            $typeNames = [0 => 'none', 1 => 'string', 2 => 'set', 3 => 'list', 4 => 'zset', 5 => 'hash'];
            $typeName = $typeNames[$type] ?? 'unknown';
            echo "   $key ($typeName)\n";
        }
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

$redis->close();
