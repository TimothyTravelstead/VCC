<?php
// 1. Include db_login.php FIRST (sets session configuration)
require_once '../../private_html/db_login.php';

// 2. Start session (inherits 8-hour timeout from db_login.php)
session_cache_limiter('nocache');
session_start();

if ($_SESSION['auth'] != 'yes') {
    die("Unauthorized");
}

// Enable error reporting to display errors in the browser
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone in database using dataQuery - use main DB for this
$timezoneQuery = "SET time_zone = ?";
dataQuery($timezoneQuery, [$offset]);

$UserID = $_SESSION['UserID'] ?? null;
$trainee = $_SESSION["trainee"] ?? null;

// Determine trainer ID based on role
if ($_SESSION["trainer"] == 1) {
    // Current user is trainer
    $trainer = $_SESSION['UserID'];
} elseif ($_SESSION["trainee"] == 1) {
    // Current user is trainee, find their assigned trainer from database
    $trainerQuery = "SELECT UserName FROM volunteers WHERE FIND_IN_SET(?, TraineeID) > 0";
    $trainerResult = dataQuery($trainerQuery, [$UserID]);
    
    if (!empty($trainerResult)) {
        $trainer = $trainerResult[0]->UserName;
        error_log("DEBUG: Found trainer for trainee $UserID: $trainer");
    } else {
        error_log("DEBUG: No trainer found for trainee $UserID in TraineeID fields");
        $trainer = null;
    }
} else {
    // Legacy case: trainer field contains actual trainer ID
    $trainer = $_SESSION["trainer"];
}

// Release session lock after reading all session data
session_write_close();

// Function to get training participants with control status
function getTrainingParticipants($trainerId) {
    // Handle case where trainer ID is null or invalid
    if (!$trainerId) {
        error_log("DEBUG: getTrainingParticipants called with null/empty trainer ID");
        return ['participants' => [], 'activeController' => null];
    }
    
    // Use the new getParticipants.php endpoint to get participants with control info
    $url = "http://" . $_SERVER['HTTP_HOST'] . "/trainingShare3/getParticipants.php?trainerId=" . urlencode($trainerId);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    if ($response === false) {
        error_log("DEBUG: file_get_contents failed for URL: $url");
        return ['participants' => [], 'activeController' => null];
    }
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['success']) || !$data['success']) {
        return ['participants' => [], 'activeController' => null];
    }
    
    $activeController = null;
    foreach ($data['participants'] as $participant) {
        if ($participant['hasControl']) {
            $activeController = $participant['id'];
            break;
        }
    }
    
    return [
        'participants' => $data['participants'],
        'activeController' => $activeController
    ];
}

// Get participants for the Training Control panel UI
// chatFrame.php handles chat participants separately
$participantData = getTrainingParticipants($trainer);
$participants = $participantData['participants'] ?? [];
$activeController = $participantData['activeController'] ?? null;

$currentUserFullName = $_SESSION['currentUserFullName'] ?? 'Unknown User';

// Determine if current user is trainer
$isTrainer = ($_SESSION["trainer"] == 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, private">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta NAME="ROBOTS" CONTENT="NONE" />    
    <meta NAME="GOOGLEBOT" CONTENT="NOARCHIVE" />
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no" />
    <title>VCC Training Chat</title>
    <link rel="stylesheet" href="index.css" />
    <script src="index.js" type="text/javascript"></script>
    <script src="../LibraryScripts/Ajax.js" type="text/javascript"></script>
</head>
<body>
    <div class="app-container">
        <!-- Ultra-Compact Single Line Header -->
        <header class="app-header-ultra-compact">
            <div class="header-content-ultra-compact">
                <span class="app-title-ultra">💬 VCC Training Chat</span>
                <div class="header-info-ultra">
                    <span class="user-name-ultra"><?php echo htmlspecialchars($currentUserFullName); ?></span>
                    <span class="separator">•</span>
                    <div class="status-inline">
                        <div class="status-dot-ultra"></div>
                        <span>Active</span>
                    </div>
                    <span class="separator">•</span>
                    <div class="status-inline">
                        <div class="status-dot-ultra connected"></div>
                        <span>Online</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Training Control Panel - Ultra Compact -->
            <div class="training-control-panel">
                <div class="control-panel-compact">
                    <span class="control-label">🎯 Control:</span>
                    <div class="participants-horizontal">
                        <?php foreach ($participants as $participant): ?>
                            <button class="participant-pill <?php echo $participant['hasControl'] ? 'has-control' : ''; ?> <?php echo $participant['isSignedOn'] ? 'online' : 'offline'; ?>" 
                                 data-participant-id="<?php echo htmlspecialchars($participant['id'] ?? ''); ?>"
                                 <?php if ($isTrainer): ?>
                                    onclick="transferControl('<?php echo htmlspecialchars($participant['id'] ?? ''); ?>', '<?php echo htmlspecialchars(($participant['name'] ?: $participant['id']) ?? 'Unknown'); ?>')"
                                    type="button"
                                 <?php else: ?>
                                    disabled
                                 <?php endif; ?>
                                 title="<?php echo htmlspecialchars(($participant['name'] ?: $participant['id']) ?? 'Unknown') . ' - ' . ucfirst($participant['role']) . ' - ' . ($participant['isSignedOn'] ? 'Online' : 'Offline'); ?>">
                                <span class="pill-dot <?php echo $participant['isSignedOn'] ? 'online' : 'offline'; ?>"></span>
                                <span class="pill-name"><?php echo htmlspecialchars(($participant['name'] ?: $participant['id']) ?? 'Unknown'); ?></span>
                                <?php if ($participant['hasControl']): ?>
                                    <span class="pill-control">✓</span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <span class="control-hint">
                        <?php if ($isTrainer): ?>
                            Click to transfer
                        <?php else: ?>
                            View only
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Chat Container -->
            <div class="chat-container">
                <div class="chat-wrapper">
                    <iframe 
                        class="chat-iframe"
                        title="Training Chat Interface"
                        loading="lazy">
                        Your browser does not support iframes. Please use a modern browser to access the chat.
                    </iframe>
                </div>
            </div>
        </main>
    </div>

    <!-- Hidden inputs for JavaScript -->
    <input id='trainerID' type='hidden' value='<?php echo htmlspecialchars($trainer); ?>'>
    <input id='assignedTraineeIDs' type='hidden' value='<?php echo htmlspecialchars($trainee); ?>'>
    <input id='volunteerID' type='hidden' value='<?php echo htmlspecialchars($UserID); ?>'>
    <input id='userID' type='hidden' value='<?php echo htmlspecialchars($UserID); ?>'>

    <!-- Loading overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p class="loading-text">Initializing chat session...</p>
        </div>
    </div>

    <script>
    // Delay iframe loading to ensure room is created first
    setTimeout(function() {
        const iframe = document.querySelector('.chat-iframe');
        if (iframe) {
            iframe.src = 'chatFrame.php';
            console.log('Loading chat iframe with delay to ensure room setup');
        }
    }, 500); // 500ms delay to ensure room creation completes

    // Add spinner animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>