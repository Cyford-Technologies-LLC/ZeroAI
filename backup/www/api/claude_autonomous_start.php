<?php
header('Content-Type: application/json');

// Start autonomous Claude worker
$pidFile = '/app/data/claude_autonomous.pid';

// Check if already running
if (file_exists($pidFile)) {
    $pid = file_get_contents($pidFile);
    if (posix_kill($pid, 0)) {
        echo json_encode(['success' => false, 'error' => 'Autonomous mode already running']);
        exit;
    }
}

// Start worker in background
$cmd = 'php /app/www/api/claude_autonomous_worker.php > /app/logs/claude_autonomous.log 2>&1 & echo $!';
$pid = shell_exec($cmd);
file_put_contents($pidFile, trim($pid));

echo json_encode(['success' => true, 'pid' => trim($pid)]);
?>