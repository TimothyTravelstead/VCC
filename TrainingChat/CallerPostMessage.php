<?php
// Include db_login.php FIRST for session configuration
require_once '../../private_html/db_login.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_cache_limiter('nocache');
session_start();

// Check authentication like other TrainingChat files
if ($_SESSION['auth'] != 'yes') {
    http_response_code(401);
    die("Unauthorized");
}

// Helper functions defined first
function updateUserActivity($userID, $chatRoomID) {
    $params = [$userID, $chatRoomID];
    $query = "UPDATE callers SET modified = NOW() WHERE userID = ? AND chatRoomID = ?";
    $result = chatDataQuery($query, $params);
    if ($result === false) {
        throw new Exception("Failed to update user activity");
    }
    return $result;
}

function getLastInsertId() {
    // Try different methods to get the last insert ID
    global $connection, $pdo, $mysqli; // Common variable names
    
    // Try PDO method
    if (isset($pdo) && is_object($pdo)) {
        try {
            return $pdo->lastInsertId();
        } catch (Exception $e) {
            // Fall through to other methods
        }
    }
    
    // Try MySQLi method
    if (isset($connection) && is_object($connection)) {
        try {
            return mysqli_insert_id($connection);
        } catch (Exception $e) {
            // Fall through to other methods
        }
    }
    
    if (isset($mysqli) && is_object($mysqli)) {
        try {
            return $mysqli->insert_id;
        } catch (Exception $e) {
            // Fall through to other methods
        }
    }
    
    // If all methods fail, return null to use fallback query method
    return null;
}


$debug = [
   'timestamp' => date('Y-m-d H:i:s'),
   'session_check' => [
       'session_started' => session_status() === PHP_SESSION_ACTIVE,
       'session_id' => session_id(),
       'currentUserFullName' => $_SESSION['currentUserFullName'] ?? 'NOT SET'
   ],
   'input' => [
       'messageID' => $_POST["messageID"] ?? 'null',
       'userID' => $_POST["userID"] ?? 'null', 
       'textLength' => isset($_POST["Text"]) ? strlen($_POST["Text"]) : 0,
       'highlightMessage' => $_POST["highlightMessage"] ?? 'null',
       'deleteMessage' => $_POST["deleteMessage"] ?? 'null',
       'chatRoomID' => $_POST["chatRoomID"] ?? 'null',
       'debug_param' => $_POST["debug"] ?? 'null'
   ],
   'steps' => []
];

try {
    // Check if database login file exists
    $db_file = '../../private_html/training_chat_db_login.php';
    if (!file_exists($db_file)) {
        throw new Exception("Database login file not found: $db_file");
    }
    
    require_once($db_file);
    $debug['database_file_loaded'] = true;

    // Check if chatDataQuery function exists
    if (!function_exists('chatDataQuery')) {
        throw new Exception("chatDataQuery function not found - check database login file");
    }
    
    $debug['chatDataQuery_available'] = true;

    $messageID = $_POST["messageID"] ?? null;
    $userID = $_POST["userID"] ?? null;
    $Text = $_POST["Text"] ?? null;
    $highlightMessage = $_POST["highlightMessage"] ?? 0;
    $deleteMessage = $_POST["deleteMessage"] ?? 0;
    $newMessageNumber = null;
    $chatRoomID = $_POST["chatRoomID"] ?? null;

    // Initialize currentUserFullName if not set - use simple fallback
    $currentUserFullName = $_SESSION['currentUserFullName'] ?? $userID ?? 'Unknown User';
    if (!isset($_SESSION['currentUserFullName'])) {
        $_SESSION['currentUserFullName'] = $currentUserFullName;
    }
    session_write_close();

    // Validate required inputs
    if(!$chatRoomID) {
       throw new Exception("No ChatRoom Specified");
    }

    if(!$userID) {
       throw new Exception("No UserID Specified");
    }

    if(!$currentUserFullName) {
       throw new Exception("No user name in session - user may not be logged in");
    }

    // Convert string booleans to integers
    if($highlightMessage === 'false') {
       $highlightMessage = 0;
    } else if ($highlightMessage === 'true') {
       $highlightMessage = 1;
    } else {
       $highlightMessage = (int)$highlightMessage;
    }

    if($deleteMessage === 'false') {
       $deleteMessage = 0;
    } else if ($deleteMessage === 'true') {
       $deleteMessage = 1;
    } else {
       $deleteMessage = (int)$deleteMessage;
    }

    $debug['processed_values'] = [
        'messageID' => $messageID,
        'userID' => $userID,
        'chatRoomID' => $chatRoomID,
        'currentUserFullName' => $currentUserFullName,
        'highlightMessage' => $highlightMessage,
        'deleteMessage' => $deleteMessage,
        'textLength' => strlen($Text ?? '')
    ];

    // Test database connection with a simple query
    try {
        $testQuery = "SELECT 1 as test";
        $testResult = chatDataQuery($testQuery, []);
        $debug['database_connection'] = 'SUCCESS';
    } catch (Exception $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }

    // Update user's last activity timestamp
    try {
        updateUserActivity($userID, $chatRoomID);
        $debug['user_activity_updated'] = true;
    } catch (Exception $e) {
        $debug['user_activity_error'] = $e->getMessage();
        // Don't fail the whole operation for this
    }

    if($messageID != '0' && $messageID !== null) {
        // UPDATE EXISTING MESSAGE
        $debug['operation'] = 'UPDATE_MESSAGE';
        
        $params = [$highlightMessage, $deleteMessage, $messageID];
        $query = "UPDATE groupChat SET highlightMessage = ?, deleteMessage = ? WHERE MessageNumber = ?";
        
        $result = chatDataQuery($query, $params);
        
        $debug['steps'][] = [
            'stage' => 'update_groupChat',
            'success' => ($result !== false),
            'params' => $params,
            'query' => $query
        ];

        if ($result === false) {
            throw new Exception("GroupChat update failed");
        }

        // Log the update transaction
        $params = [$userID, $chatRoomID, $messageID, $Text, $highlightMessage, $deleteMessage];
        $query = "INSERT INTO Transactions (id, type, action, UserID, chatRoomID, messageNumber, Message, highlightMessage, deleteMessage, created, modified) 
                  VALUES (NULL, 'message', 'update', ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $result = chatDataQuery($query, $params);
        
        $debug['steps'][] = [
            'stage' => 'insert_transaction_update',
            'success' => ($result !== false),
            'params' => $params,
            'query' => $query
        ];

        if ($result === false) {
            throw new Exception("Transaction update insert failed");
        }

    } elseif($messageID == '0' || $messageID === null) {
        // CREATE NEW MESSAGE
        $debug['operation'] = 'CREATE_MESSAGE';
        
        // Validate text content for new messages
        if(empty($Text) || trim($Text) === '') {
            throw new Exception("No message text provided for new message");
        }

        // First, insert into Transactions to get the message ID
        $params = [$userID, $chatRoomID, $messageID, $Text, $highlightMessage, $deleteMessage];
        $query = "INSERT INTO Transactions (id, type, action, UserID, chatRoomID, messageNumber, Message, highlightMessage, deleteMessage, created, modified) 
                  VALUES (NULL, 'message', 'create', ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $result = chatDataQuery($query, $params);
        
        $debug['steps'][] = [
            'stage' => 'insert_transaction_create',
            'success' => ($result !== false),
            'params' => $params,
            'query' => $query
        ];

        if ($result === false) {
            throw new Exception("Transaction insert failed");
        }

        // Get the transaction ID that was just created
        try {
            // First try to get last insert ID
            $newMessageNumber = getLastInsertId();
            $debug['last_insert_id_method'] = $newMessageNumber;
        } catch (Exception $e) {
            $debug['last_insert_id_error'] = $e->getMessage();
            $newMessageNumber = null;
        }
        
        if (!$newMessageNumber) {
            // Fallback method to get the transaction ID
            $params = [$userID, $chatRoomID, $Text];
            $query = "SELECT id FROM Transactions 
                      WHERE type = 'message'
                      AND action = 'create' 
                      AND UserID = ? 
                      AND chatRoomID = ? 
                      AND Message = ?
                      ORDER BY id DESC
                      LIMIT 1";
            $result = chatDataQuery($query, $params);
            
            $debug['steps'][] = [
                'stage' => 'select_transaction_id',
                'success' => ($result !== false && !empty($result)),
                'result_count' => $result ? count($result) : 0,
                'params' => $params,
                'query' => $query
            ];
            
            if($result && is_array($result) && !empty($result)) {
                $newMessageNumber = $result[0]->id ?? $result[0]['id'] ?? null;
                $debug['fallback_method_result'] = $newMessageNumber;
            }
        }
        
        if ($newMessageNumber === null) {
            throw new Exception("No message ID generated - check Transactions table structure");
        }

        // Now insert into groupChat with the transaction ID (using same format as GroupChat)
        $params = [$newMessageNumber, $userID, $userID, $chatRoomID, $Text, $chatRoomID];
        $query = "INSERT INTO groupChat VALUES (?, now(), ?, '1', (select name from callers where userID = ? and chatRoomID = ? LIMIT 1), 
                  ?, NULL, NULL, ?, NULL, NULL, DEFAULT)";
        $result = chatDataQuery($query, $params);
        
        $debug['steps'][] = [
            'stage' => 'insert_groupChat',
            'success' => ($result !== false),
            'params' => $params,
            'query' => $query,
            'messageNumber' => $newMessageNumber
        ];

        if ($result === false) {
            throw new Exception("insert_groupChat failed - check groupChat table structure");
        }

        $debug['success'] = true;
        $debug['messageNumber'] = $newMessageNumber;
        $debug['message'] = "Message posted successfully";
    }

} catch (Exception $e) {
    $debug['error'] = $e->getMessage();
    $debug['trace'] = $e->getTraceAsString();
}

// Return response based on success/failure
if (isset($debug['error'])) {
    // Return detailed error for debugging
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode($debug, JSON_PRETTY_PRINT);
} else {
    // Check if this was a successful operation
    $allStepsSuccessful = true;
    if (isset($debug['steps'])) {
        foreach ($debug['steps'] as $step) {
            if (!$step['success']) {
                $allStepsSuccessful = false;
                break;
            }
        }
    }
    
    if ($allStepsSuccessful) {
        // Check if debug mode is requested
        if (isset($_GET['debug']) || isset($_POST['debug']) || 
            (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false)) {
            // Return detailed debug info in debug mode
            header('Content-Type: application/json');
            echo json_encode($debug, JSON_PRETTY_PRINT);
        } else {
            // Return simple OK for production (what your JS expects)
            header('Content-Type: text/plain');
            echo "OK";
        }
    } else {
        // Return detailed error info
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode($debug, JSON_PRETTY_PRINT);
    }
}
?>