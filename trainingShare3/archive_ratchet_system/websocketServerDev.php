<?php
// Development WebSocket Server - Allows test users without database validation
// Based on websocketServer.php but with relaxed authentication for testing

require __DIR__ . '/../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class TrainingWebSocketServerDev implements MessageComponentInterface {
    private $rooms;          // Room management
    private $connections;    // Connection metadata
    private $dbConnection;   // Database connection (optional for dev)

    public function __construct() {
        $this->rooms = [];
        $this->connections = new \SplObjectStorage;
        $this->initDatabase();
        
        echo "[" . date('Y-m-d H:i:s') . "] Training WebSocket Server (DEV MODE) initialized\n";
        echo "⚠️  DEV MODE: Test users allowed without database validation\n";
    }

    private function initDatabase() {
        try {
            // Use the same database credentials as the main system
            $dbname = "dgqtkqjasj";
            $host = "localhost";
            $user = "dgqtkqjasj";
            $pass = "CXpskz9QXQ";
            
            $this->dbConnection = new PDO(
                "mysql:host=$host;dbname=$dbname",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            echo "[" . date('Y-m-d H:i:s') . "] Database connection established (optional in dev mode)\n";
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Database connection failed (continuing in dev mode): " . $e->getMessage() . "\n";
            $this->dbConnection = null;
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $params);
        
        echo "[" . date('Y-m-d H:i:s') . "] New connection attempt from {$conn->resourceId}\n";
        echo "Query params: " . json_encode($params) . "\n";

        // Validate required parameters
        if (!isset($params['room']) || !isset($params['user']) || !isset($params['sessionId'])) {
            $this->sendError($conn, 'Missing required parameters: room, user, sessionId');
            $conn->close();
            return;
        }

        $roomId = $params['room'];
        $userId = $params['user'];
        $sessionId = $params['sessionId'];

        // Validate session and get user role (dev mode)
        $userRole = $this->validateSessionDev($sessionId, $userId);
        if (!$userRole) {
            $this->sendError($conn, 'Invalid session or unauthorized user');
            $conn->close();
            return;
        }

        // Store connection metadata
        $this->connections->attach($conn, [
            'roomId' => $roomId,
            'userId' => $userId,
            'userRole' => $userRole,
            'sessionId' => $sessionId,
            'joinedAt' => time()
        ]);

        // Initialize room if needed
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [
                'participants' => [],
                'createdAt' => time(),
                'trainer' => null
            ];
        }

        // Add to room
        $this->rooms[$roomId]['participants'][$userId] = [
            'connection' => $conn,
            'role' => $userRole,
            'joinedAt' => time()
        ];

        // Set trainer if this is a trainer
        if ($userRole === 'trainer') {
            $this->rooms[$roomId]['trainer'] = $userId;
        }

        // Send welcome message
        $conn->send(json_encode([
            'type' => 'welcome',
            'userId' => $userId,
            'roomId' => $roomId,
            'role' => $userRole,
            'participants' => array_keys($this->rooms[$roomId]['participants'])
        ]));

        // Notify other participants
        $this->broadcastToRoom($roomId, [
            'type' => 'participant-joined',
            'userId' => $userId,
            'role' => $userRole,
            'participants' => array_keys($this->rooms[$roomId]['participants'])
        ], $userId);

        echo "[" . date('Y-m-d H:i:s') . "] User $userId ($userRole) joined room $roomId\n";
    }

    // DEV MODE: Relaxed session validation
    private function validateSessionDev($sessionId, $userId) {
        // Allow test users without database validation
        if (strpos($userId, 'Test') === 0 || strpos($userId, 'test') === 0) {
            echo "[" . date('Y-m-d H:i:s') . "] DEV MODE: Allowing test user $userId as trainer\n";
            return 'trainer'; // Default test users to trainer role
        }
        
        // For real users, try database validation if available
        if ($this->dbConnection) {
            try {
                $stmt = $this->dbConnection->prepare(
                    "SELECT trainer, trainee, LoggedOn FROM Volunteers WHERE UserName = ? LIMIT 1"
                );
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    echo "[" . date('Y-m-d H:i:s') . "] User $userId not found in database\n";
                    return false;
                }

                // Determine role based on session and database flags
                if ($user['LoggedOn'] == 4 && $user['trainer'] == 1) {
                    return 'trainer';
                } elseif ($user['LoggedOn'] == 6 && $user['trainee'] == 1) {
                    return 'trainee';
                }

                echo "[" . date('Y-m-d H:i:s') . "] User $userId has invalid role/login status\n";
                return false;

            } catch (Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] Session validation error: " . $e->getMessage() . "\n";
                return false;
            }
        }
        
        // No database, allow as trainer for dev
        echo "[" . date('Y-m-d H:i:s') . "] DEV MODE: No database, allowing $userId as trainer\n";
        return 'trainer';
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        if (!$this->connections->contains($from)) {
            echo "[" . date('Y-m-d H:i:s') . "] Message from unregistered connection\n";
            return;
        }

        $clientInfo = $this->connections[$from];
        $data = json_decode($msg, true);

        if (!$data || !isset($data['type'])) {
            echo "[" . date('Y-m-d H:i:s') . "] Invalid message format\n";
            return;
        }

        // Add sender information
        $data['from'] = $clientInfo['userId'];
        $data['fromRole'] = $clientInfo['userRole'];
        $data['timestamp'] = microtime(true);

        echo "[" . date('Y-m-d H:i:s') . "] Message '{$data['type']}' from {$clientInfo['userId']} in room {$clientInfo['roomId']}\n";

        switch ($data['type']) {
            case 'offer':
            case 'answer':
            case 'ice-candidate':
                $this->handleWebRTCSignaling($clientInfo, $data);
                break;

            case 'screen-share-start':
            case 'screen-share-stop':
                $this->handleScreenShareSignaling($clientInfo, $data);
                break;

            case 'trainer-active':
                $this->handleTrainerActive($clientInfo, $data);
                break;

            case 'ping':
                // Heartbeat response
                $from->send(json_encode(['type' => 'pong', 'timestamp' => time()]));
                break;

            default:
                // Broadcast generic messages to room
                $this->broadcastToRoom($clientInfo['roomId'], $data, $clientInfo['userId']);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        if (!$this->connections->contains($conn)) {
            return;
        }

        $clientInfo = $this->connections[$conn];
        $roomId = $clientInfo['roomId'];
        $userId = $clientInfo['userId'];

        echo "[" . date('Y-m-d H:i:s') . "] User $userId disconnected from room $roomId\n";

        // Remove from room
        if (isset($this->rooms[$roomId]['participants'][$userId])) {
            unset($this->rooms[$roomId]['participants'][$userId]);

            // If trainer left, clear trainer
            if ($this->rooms[$roomId]['trainer'] === $userId) {
                $this->rooms[$roomId]['trainer'] = null;
            }

            // Notify remaining participants
            $this->broadcastToRoom($roomId, [
                'type' => 'participant-left',
                'userId' => $userId,
                'participants' => array_keys($this->rooms[$roomId]['participants'])
            ]);

            // Clean up empty rooms
            if (empty($this->rooms[$roomId]['participants'])) {
                unset($this->rooms[$roomId]);
                echo "[" . date('Y-m-d H:i:s') . "] Room $roomId deleted (empty)\n";
            }
        }

        $this->connections->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Connection error: {$e->getMessage()}\n";
        
        if ($this->connections->contains($conn)) {
            $this->onClose($conn);
        }
        
        $conn->close();
    }

    // Copy the rest of the methods from the original server
    private function handleWebRTCSignaling($clientInfo, $data) {
        $roomId = $clientInfo['roomId'];
        
        if (isset($data['target'])) {
            $this->sendToParticipant($roomId, $data['target'], $data);
        } else {
            $this->broadcastToRoom($roomId, $data, $clientInfo['userId']);
        }
    }

    private function handleScreenShareSignaling($clientInfo, $data) {
        $roomId = $clientInfo['roomId'];
        $this->broadcastToRoom($roomId, $data, $clientInfo['userId']);
        echo "[" . date('Y-m-d H:i:s') . "] Screen sharing {$data['type']} by {$clientInfo['userId']} in room $roomId\n";
    }

    private function handleTrainerActive($clientInfo, $data) {
        $roomId = $clientInfo['roomId'];
        if ($clientInfo['userRole'] === 'trainer') {
            $this->broadcastToRoom($roomId, $data, $clientInfo['userId']);
        }
    }

    private function sendToParticipant($roomId, $targetUserId, $message) {
        if (!isset($this->rooms[$roomId]['participants'][$targetUserId])) {
            echo "[" . date('Y-m-d H:i:s') . "] Target participant $targetUserId not found in room $roomId\n";
            return;
        }

        $targetConn = $this->rooms[$roomId]['participants'][$targetUserId]['connection'];
        
        try {
            $targetConn->send(json_encode($message));
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Failed to send message to $targetUserId: {$e->getMessage()}\n";
        }
    }

    private function broadcastToRoom($roomId, $message, $excludeUserId = null) {
        if (!isset($this->rooms[$roomId])) {
            return;
        }

        foreach ($this->rooms[$roomId]['participants'] as $userId => $participant) {
            if ($excludeUserId && $userId === $excludeUserId) {
                continue;
            }

            try {
                $participant['connection']->send(json_encode($message));
            } catch (Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] Failed to broadcast to $userId: {$e->getMessage()}\n";
            }
        }
    }

    private function sendError($conn, $message) {
        $conn->send(json_encode([
            'type' => 'error',
            'message' => $message,
            'timestamp' => time()
        ]));
    }
}

// Create and start the server on port 8083 for dev
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new TrainingWebSocketServerDev()
        )
    ),
    8083,
    '0.0.0.0'  // Bind to all interfaces for external access
);

echo "🚀 Training WebSocket Server (DEV MODE) starting on ws://0.0.0.0:8083\n";
echo "💡 This server allows test users without database validation\n";
echo "💡 Use Ctrl+C to stop the server\n\n";

$server->run();
?>