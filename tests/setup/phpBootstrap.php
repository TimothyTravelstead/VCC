<?php
/**
 * PHPUnit Bootstrap File
 * Sets up test environment with database transaction isolation
 */

// Define test mode
define('TEST_MODE', true);

// Autoload composer dependencies
require_once __DIR__ . '/../../vendor/autoload.php';

// Create mock database functions for testing
class TestDatabase {
    private static $pdo = null;
    private static $mockResults = [];
    private static $queryCalls = [];

    /**
     * Initialize test database connection (in-memory SQLite)
     */
    public static function init() {
        if (self::$pdo === null) {
            self::$pdo = new PDO('sqlite::memory:');
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::createTables();
        }
        return self::$pdo;
    }

    /**
     * Create test tables matching production schema
     */
    private static function createTables() {
        // volunteers table
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS volunteers (
                UserId INTEGER PRIMARY KEY AUTOINCREMENT,
                UserName TEXT UNIQUE NOT NULL,
                FullName TEXT,
                trainer INTEGER DEFAULT 0,
                trainee INTEGER DEFAULT 0,
                LoggedOn INTEGER DEFAULT 0,
                OnCall INTEGER DEFAULT 0,
                TraineeID TEXT,
                groupChatMonitor INTEGER DEFAULT 0
            )
        ");

        // training_session_control table
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS training_session_control (
                trainer_id TEXT PRIMARY KEY,
                active_controller TEXT NOT NULL,
                controller_role TEXT NOT NULL,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // CallControl table
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS CallControl (
                user_id TEXT PRIMARY KEY,
                logged_on_status INTEGER DEFAULT 0,
                can_receive_calls INTEGER DEFAULT 1,
                can_receive_chats INTEGER DEFAULT 1
            )
        ");

        // TwilioStatusLog table
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS TwilioStatusLog (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                CallSid TEXT,
                ConferenceSid TEXT,
                FriendlyName TEXT,
                StatusCallbackEvent TEXT,
                CallStatus TEXT,
                Muted INTEGER,
                RawRequest TEXT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    /**
     * Reset database state between tests
     */
    public static function reset() {
        if (self::$pdo) {
            self::$pdo->exec("DELETE FROM volunteers");
            self::$pdo->exec("DELETE FROM training_session_control");
            self::$pdo->exec("DELETE FROM CallControl");
            self::$pdo->exec("DELETE FROM TwilioStatusLog");
        }
        self::$mockResults = [];
        self::$queryCalls = [];
    }

    /**
     * Get PDO instance
     */
    public static function getPDO() {
        return self::init();
    }

    /**
     * Set mock result for a specific query pattern
     */
    public static function setMockResult($pattern, $result) {
        self::$mockResults[$pattern] = $result;
    }

    /**
     * Get recorded query calls
     */
    public static function getQueryCalls() {
        return self::$queryCalls;
    }

    /**
     * Record a query call
     */
    public static function recordQuery($query, $params) {
        self::$queryCalls[] = [
            'query' => $query,
            'params' => $params,
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Find mock result matching query
     */
    public static function findMockResult($query) {
        foreach (self::$mockResults as $pattern => $result) {
            if (strpos($query, $pattern) !== false || preg_match("/$pattern/i", $query)) {
                return $result;
            }
        }
        return null;
    }
}

/**
 * Mock dataQuery function for testing
 * Replaces the production database function
 */
function dataQuery($query, $params = []) {
    TestDatabase::recordQuery($query, $params);

    // Check for mock result
    $mockResult = TestDatabase::findMockResult($query);
    if ($mockResult !== null) {
        return $mockResult;
    }

    // Use in-memory SQLite for actual queries
    $pdo = TestDatabase::getPDO();

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        // Return results based on query type
        if (stripos($query, 'SELECT') === 0) {
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);
            return $results;
        } elseif (stripos($query, 'INSERT') === 0) {
            return $pdo->lastInsertId();
        } else {
            return $stmt->rowCount();
        }
    } catch (PDOException $e) {
        return ['error' => true, 'message' => $e->getMessage()];
    }
}

/**
 * Test helper to insert test data
 */
function insertTestVolunteer($data) {
    $pdo = TestDatabase::getPDO();
    $defaults = [
        'UserName' => 'TestUser',
        'FullName' => 'Test User',
        'trainer' => 0,
        'trainee' => 0,
        'LoggedOn' => 0,
        'OnCall' => 0,
        'TraineeID' => null
    ];

    $data = array_merge($defaults, $data);

    $stmt = $pdo->prepare("
        INSERT INTO volunteers (UserName, FullName, trainer, trainee, LoggedOn, OnCall, TraineeID)
        VALUES (:UserName, :FullName, :trainer, :trainee, :LoggedOn, :OnCall, :TraineeID)
    ");

    return $stmt->execute($data);
}

/**
 * Test helper to insert training session control record
 */
function insertTestTrainingControl($trainerId, $activeController, $controllerRole) {
    $pdo = TestDatabase::getPDO();
    $stmt = $pdo->prepare("
        INSERT INTO training_session_control (trainer_id, active_controller, controller_role)
        VALUES (?, ?, ?)
    ");
    return $stmt->execute([$trainerId, $activeController, $controllerRole]);
}

/**
 * Test helper to get training control record
 */
function getTestTrainingControl($trainerId) {
    $pdo = TestDatabase::getPDO();
    $stmt = $pdo->prepare("SELECT * FROM training_session_control WHERE trainer_id = ?");
    $stmt->execute([$trainerId]);
    return $stmt->fetch(PDO::FETCH_OBJ);
}

/**
 * Test helper to get CallControl records
 */
function getTestCallControl($userId = null) {
    $pdo = TestDatabase::getPDO();
    if ($userId) {
        $stmt = $pdo->prepare("SELECT * FROM CallControl WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    } else {
        return $pdo->query("SELECT * FROM CallControl")->fetchAll(PDO::FETCH_OBJ);
    }
}

// Initialize database on load
TestDatabase::init();
