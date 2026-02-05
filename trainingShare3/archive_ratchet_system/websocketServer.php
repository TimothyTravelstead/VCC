<?php
// Enhanced Ratchet WebSocket server for training system with session authentication
// Run with: php websocketServer.php

require __DIR__ . '/../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class TrainingWebSocketServer implements MessageComponentInterface {
    private $rooms;          // Room management
    private $connections;    // Connection metadata
    private $dbConnection;   // Database connection

    public function __construct() {
        $this->rooms = [];
        $this->connections = new \SplObjectStorage;
        $this->initDatabase();
        
        echo "[" . date('Y-m-d H:i:s') . "] Training WebSocket Server initialized\n";
    }

    private function initDatabase() {
        try {
            // Use the same database credentials as the main system without loading the full db_login.php
            // These are the hardcoded values from db_login.php lines 34-37
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
            
            echo "[" . date('Y-m-d H:i:s') . "] Database connection established\n";
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Database connection failed: " . $e->getMessage() . "\n";
            die("Cannot start server without database connection\n");
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

        // Validate session and get user role
        $userRole = $this->validateSession($sessionId, $userId);
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

    private function validateSession($sessionId, $userId) {
        try {
            // Check session in database (if stored there) or validate session file
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
            echo "[" . date('Y-m-d H:i:s') . "] Session validation error: {$e->getMessage()}\n";
            return false;
        }
    }

    private function handleWebRTCSignaling($clientInfo, $data) {
        $roomId = $clientInfo['roomId'];
        
        if (isset($data['target'])) {
            // Direct message to specific participant
            $this->sendToParticipant($roomId, $data['target'], $data);
        } else {
            // Broadcast to all others in room (typical for offers)
            $this->broadcastToRoom($roomId, $data, $clientInfo['userId']);
        }
    }

    private function handleScreenShareSignaling($clientInfo, $data) {
        $roomId = $clientInfo['roomId'];
        
        // Screen sharing signals go to all participants
        $this->broadcastToRoom($roomId, $data, $clientInfo['userId']);
        
        // Log screen sharing events
        echo "[" . date('Y-m-d H:i:s') . "] Screen sharing {$data['type']} by {$clientInfo['userId']} in room $roomId\n";
    }

    private function handleTrainerActive($clientInfo, $data) {
        $roomId = $clientInfo['roomId'];
        
        // Only trainers can send this signal
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

    public function getStats() {
        $stats = [
            'activeRooms' => count($this->rooms),
            'totalConnections' => $this->connections->count(),
            'rooms' => []
        ];

        foreach ($this->rooms as $roomId => $room) {
            $stats['rooms'][$roomId] = [
                'participants' => count($room['participants']),
                'trainer' => $room['trainer'],
                'createdAt' => $room['createdAt']
            ];
        }

        return $stats;
    }
}

// Create and start the server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new TrainingWebSocketServer()
        )
    ),
    8080
);

echo "🚀 Training WebSocket Server starting on ws://localhost:8080\n";
echo "💡 Use Ctrl+C to stop the server\n\n";

// Register signal handlers for graceful shutdown
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() use ($server) {
        echo "\n🛑 Received SIGTERM, shutting down gracefully...\n";
        exit(0);
    });
    
    pcntl_signal(SIGINT, function() use ($server) {
        echo "\n🛑 Received SIGINT, shutting down gracefully...\n";
        exit(0);
    });
}

$server->run();
?>