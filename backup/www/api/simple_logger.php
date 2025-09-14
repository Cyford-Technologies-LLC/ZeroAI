<?php
function logCommand($command, $result, $error = null) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'command' => $command,
        'result' => $result,
        'error' => $error
    ];
    
    $logFile = '/app/logs/claude_commands.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}
?>