<?php 
$pageTitle = 'Error Logs - ZeroAI Admin';
$currentPage = 'error_logs';
include __DIR__ . '/includes/header.php';
?>
<style>
.log-section { margin-bottom: 30px; }
.log-content { background: #000; color: #0f0; font-family: monospace; padding: 15px; border-radius: 3px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; }
.error { color: #ff6b6b; }
.warning { color: #ffa500; }
.info { color: #87ceeb; }
</style>
        <h1>🚨 Error Logs - ZeroAI Admin</h1>
        
        <button class="refresh-btn" onclick="location.reload()">Refresh Logs</button>
        <button class="refresh-btn btn-danger" onclick="clearLog('zeroai')">Clear ZeroAI Log</button>
        <button class="refresh-btn btn-danger" onclick="clearLog('nginx')">Clear Nginx Log</button>
        <button class="refresh-btn btn-danger" onclick="clearLog('php')">Clear PHP Log</button>
        <button class="refresh-btn btn-danger" onclick="clearLog('claude')">Clear Claude Log</button>
        <button class="refresh-btn btn-danger" onclick="clearAllLogs()">Clear All Logs</button>
        <button id="debug-btn" class="refresh-btn" onclick="toggleDebug()" style="background: #dc3545; color: #fff;">Debug Mode OFF</button>
        
        <div class="log-section">
            <h2>ZeroAI Application Errors (Last 50 lines)</h2>
            <div class="log-content">
<?php
$logger = \ZeroAI\Core\Logger::getInstance();
$logs = $logger->getRecentLogs(50);
if (!empty($logs)) {
    foreach ($logs as $line) {
        $class = '';
        if (strpos($line, 'ERROR') !== false) $class = 'error';
        elseif (strpos($line, 'WARNING') !== false) $class = 'warning';
        else $class = 'info';
        echo "<span class='$class'>" . htmlspecialchars($line) . "</span>\n";
    }
} else {
    echo "No application errors found";
}
?>
            </div>
        </div>
        
        <div class="log-section">
            <h2>Nginx Error Log (Last 30 lines)</h2>
            <div class="log-content">
<?php
$nginxLog = '/var/log/nginx/error.log';
if (file_exists($nginxLog)) {
    $lines = array_reverse(array_slice(file($nginxLog, FILE_IGNORE_NEW_LINES), -30));
    foreach ($lines as $line) {
        $class = '';
        if (strpos($line, 'error') !== false) $class = 'error';
        elseif (strpos($line, 'warn') !== false) $class = 'warning';
        else $class = 'info';
        echo "<span class='$class'>" . htmlspecialchars($line) . "</span>\n";
    }
} else {
    echo "Log file not found: $nginxLog";
}
?>
            </div>
        </div>

        <div class="log-section">
            <h2>PHP-FPM Error Log (Last 30 lines)</h2>
            <div class="log-content">
<?php
$phpLog = '/var/log/php8.1-fpm.log';
if (file_exists($phpLog)) {
    $lines = array_reverse(array_slice(file($phpLog, FILE_IGNORE_NEW_LINES), -30));
    foreach ($lines as $line) {
        echo "<span class='error'>" . htmlspecialchars($line) . "</span>\n";
    }
} else {
    echo "Log file not found: $phpLog";
}
?>
            </div>
        </div>

        <div class="log-section">
            <h2>Claude Commands Log (Last 20 lines)</h2>
            <div class="log-content">
<?php
$claudeLog = '/app/logs/claude_commands.log';
if (file_exists($claudeLog)) {
    $lines = array_reverse(array_slice(file($claudeLog, FILE_IGNORE_NEW_LINES), -20));
    foreach ($lines as $line) {
        echo "<span class='info'>" . htmlspecialchars($line) . "</span>\n";
    }
} else {
    echo "Log file not found: $claudeLog";
}
?>
            </div>
        </div>

        <div class="log-section">
            <h2>Claude Command History (Last 20)</h2>
            <div class="log-content">
<?php
$claudeDbPath = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data/claude_memory.db';
if (\ZeroAI\Core\InputValidator::validatePath($claudeDbPath) && file_exists($claudeDbPath)) {
    try {
        $claudePdo = new PDO("sqlite:$claudeDbPath");
        $stmt = $claudePdo->prepare("SELECT command, output, status, model_used, timestamp FROM command_history ORDER BY timestamp DESC LIMIT 20");
        $stmt->execute();
        $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($commands)) {
            foreach (array_reverse($commands) as $cmd) {
                $time = date('M d H:i:s', strtotime($cmd['timestamp']));
                echo "<span class='info'>[$time] {$cmd['command']} - Status: {$cmd['status']}</span>\n";
                if ($cmd['output']) {
                    echo "<span class='info'>Output: " . htmlspecialchars(substr($cmd['output'], 0, 200)) . "</span>\n";
                }
                echo "\n";
            }
        } else {
            echo "<span class='info'>No command history found</span>";
        }
    } catch (Exception $e) {
        echo "<span class='error'>Error reading Claude's database: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
} else {
    echo "<span class='error'>Claude's database not found</span>";
}
?>
            </div>
        </div>

        <div class="log-section">
            <h2>Recent Command Save Errors</h2>
            <div class="log-content">
<?php
if (file_exists($nginxLog)) {
    $lines = file($nginxLog, FILE_IGNORE_NEW_LINES);
    $commandErrors = array_filter($lines, function($line) {
        return strpos($line, 'Command save error') !== false;
    });
    $commandErrors = array_reverse(array_slice($commandErrors, -10));
    
    if (!empty($commandErrors)) {
        foreach ($commandErrors as $line) {
            echo "<span class='error'>" . htmlspecialchars($line) . "</span>\n";
        }
    } else {
        echo "<span class='info'>No command save errors found</span>";
    }
} else {
    echo "Cannot check for command errors";
}
?>
            </div>
        </div>
    </div>

<?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script>
    function clearLog(logType) {
        if (!confirm('Are you sure you want to clear the ' + logType + ' log?')) return;
        
        fetch('/admin/clear_log.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({log: logType})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Log cleared successfully');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => alert('Error: ' + error));
    }
    
    function clearAllLogs() {
        if (!confirm('Are you sure you want to clear ALL logs? This cannot be undone.')) return;
        
        Promise.all([
            fetch('/admin/clear_log.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({log: 'nginx'})}),
            fetch('/admin/clear_log.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({log: 'php'})}),
            fetch('/admin/clear_log.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({log: 'claude'})})
        ])
        .then(() => {
            alert('All logs cleared successfully');
            location.reload();
        })
        .catch(error => alert('Error clearing logs: ' + error));
    }
    
    let debugMode = localStorage.getItem('claude_debug_mode') === 'true';
    
    function toggleDebug() {
        debugMode = !debugMode;
        localStorage.setItem('claude_debug_mode', debugMode);
        
        const btn = document.getElementById('debug-btn');
        if (debugMode) {
            btn.textContent = 'Debug Mode ON';
            btn.style.background = '#28a745';
            btn.style.color = '#fff';
            alert('Debug mode ENABLED. Claude commands will now show detailed logging.');
        } else {
            btn.textContent = 'Debug Mode OFF';
            btn.style.background = '#dc3545';
            btn.style.color = '#fff';
            alert('Debug mode DISABLED.');
        }
    }
    
    // Set initial button state
    if (debugMode) {
        const btn = document.getElementById('debug-btn');
        btn.textContent = 'Debug Mode ON';
        btn.style.background = '#28a745';
        btn.style.color = '#fff';
    }
    </script>
