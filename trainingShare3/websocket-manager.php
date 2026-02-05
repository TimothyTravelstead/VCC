<?php
// WebSocket Server Manager for Training System
// Usage: php websocket-manager.php [start|stop|restart|status]

function getServerPid() {
    $output = shell_exec('pgrep -f "websocketServer.php"');
    return $output ? (int)trim($output) : null;
}

function isServerRunning() {
    return getServerPid() !== null;
}

function startServer() {
    if (isServerRunning()) {
        echo "❌ WebSocket server is already running (PID: " . getServerPid() . ")\n";
        return false;
    }

    echo "🚀 Starting WebSocket server...\n";
    
    $serverPath = __DIR__ . '/websocketServer.php';
    $logPath = __DIR__ . '/websocket.log';
    
    // Start server in background
    $cmd = "php $serverPath > $logPath 2>&1 & echo $!";
    $pid = shell_exec($cmd);
    
    if ($pid) {
        $pid = (int)trim($pid);
        echo "✅ WebSocket server started with PID: $pid\n";
        echo "📄 Logs: $logPath\n";
        echo "🌐 URL: ws://localhost:8080\n";
        
        // Save PID for later reference
        file_put_contents(__DIR__ . '/websocket.pid', $pid);
        
        return true;
    } else {
        echo "❌ Failed to start WebSocket server\n";
        return false;
    }
}

function stopServer() {
    $pid = getServerPid();
    
    if (!$pid) {
        echo "❌ WebSocket server is not running\n";
        return false;
    }

    echo "🛑 Stopping WebSocket server (PID: $pid)...\n";
    
    // Try graceful shutdown first
    shell_exec("kill -TERM $pid");
    sleep(2);
    
    // Check if still running
    if (isServerRunning()) {
        echo "⚠️  Graceful shutdown failed, forcing termination...\n";
        shell_exec("kill -KILL $pid");
        sleep(1);
    }
    
    if (!isServerRunning()) {
        echo "✅ WebSocket server stopped\n";
        
        // Clean up PID file
        $pidFile = __DIR__ . '/websocket.pid';
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
        
        return true;
    } else {
        echo "❌ Failed to stop WebSocket server\n";
        return false;
    }
}

function showStatus() {
    $pid = getServerPid();
    
    if ($pid) {
        echo "✅ WebSocket server is RUNNING (PID: $pid)\n";
        
        // Show some server stats
        $logPath = __DIR__ . '/websocket.log';
        if (file_exists($logPath)) {
            echo "📄 Log file: $logPath\n";
            echo "📊 Log size: " . number_format(filesize($logPath)) . " bytes\n";
            
            // Show last few log lines
            $lines = explode("\n", trim(shell_exec("tail -n 5 $logPath")));
            echo "📝 Recent log entries:\n";
            foreach ($lines as $line) {
                if (!empty($line)) {
                    echo "   $line\n";
                }
            }
        }
        
        // Check if port is listening
        $portCheck = shell_exec('netstat -tlnp 2>/dev/null | grep :8080');
        if ($portCheck) {
            echo "🌐 Port 8080 is listening\n";
        } else {
            echo "⚠️  Port 8080 is not listening (server may be starting up)\n";
        }
        
    } else {
        echo "❌ WebSocket server is NOT running\n";
    }
}

function restartServer() {
    echo "🔄 Restarting WebSocket server...\n";
    stopServer();
    sleep(1);
    return startServer();
}

function showUsage() {
    echo "WebSocket Server Manager for Training System\n\n";
    echo "Usage: php websocket-manager.php [command]\n\n";
    echo "Commands:\n";
    echo "  start    Start the WebSocket server\n";
    echo "  stop     Stop the WebSocket server\n";
    echo "  restart  Restart the WebSocket server\n";
    echo "  status   Show server status\n";
    echo "  logs     Show recent log entries\n";
    echo "  help     Show this help message\n\n";
    echo "Examples:\n";
    echo "  php websocket-manager.php start\n";
    echo "  php websocket-manager.php status\n";
}

function showLogs() {
    $logPath = __DIR__ . '/websocket.log';
    
    if (!file_exists($logPath)) {
        echo "❌ Log file not found: $logPath\n";
        return;
    }
    
    echo "📄 WebSocket Server Logs:\n";
    echo "====================================\n";
    
    // Show last 20 lines
    $lines = explode("\n", trim(shell_exec("tail -n 20 $logPath")));
    foreach ($lines as $line) {
        if (!empty($line)) {
            echo "$line\n";
        }
    }
    
    echo "====================================\n";
    echo "📊 Full log: $logPath\n";
}

// Main execution
$command = $argv[1] ?? 'help';

switch ($command) {
    case 'start':
        startServer();
        break;
        
    case 'stop':
        stopServer();
        break;
        
    case 'restart':
        restartServer();
        break;
        
    case 'status':
        showStatus();
        break;
        
    case 'logs':
        showLogs();
        break;
        
    case 'help':
    default:
        showUsage();
        break;
}

echo "\n";
?>