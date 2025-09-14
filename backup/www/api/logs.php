<?php
header('Content-Type: application/json');

$type = $_GET['type'] ?? 'error';
$format = $_GET['format'] ?? 'json';
$lines = (int)($_GET['lines'] ?? 100);

function getErrorLogs($lines = 100) {
    $logs = [];
    $logSources = [
        '/app/logs/error.log',
        '/app/logs/zeroai.log', 
        '/app/logs/crews/error.log',
        '/var/log/apache2/error.log',
        '/var/log/nginx/error.log'
    ];
    
    foreach ($logSources as $logFile) {
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $logLines = array_filter(explode("\n", $content));
            
            foreach (array_slice($logLines, -$lines) as $line) {
                if (empty(trim($line))) continue;
                
                $parsed = parseLogLine($line, basename($logFile));
                if ($parsed) {
                    $logs[] = $parsed;
                }
            }
        }
    }
    
    // Add PHP error logs
    $phpErrors = getPhpErrorLogs($lines);
    $logs = array_merge($logs, $phpErrors);
    
    // Sort by timestamp (newest first)
    usort($logs, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    return array_slice($logs, 0, $lines);
}

function parseLogLine($line, $source) {
    // Try different log formats
    
    // Apache/Nginx error log format
    if (preg_match('/\[(.*?)\] \[(\w+)\] (.+)/', $line, $matches)) {
        return [
            'timestamp' => $matches[1],
            'level' => $matches[2],
            'message' => $matches[3],
            'source' => $source
        ];
    }
    
    // Python/ZeroAI log format
    if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}),\d+ - (\w+) - (.+)/', $line, $matches)) {
        return [
            'timestamp' => $matches[1],
            'level' => $matches[2],
            'message' => $matches[3],
            'source' => $source
        ];
    }
    
    // Generic timestamp detection
    if (preg_match('/(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})/', $line, $matches)) {
        $level = 'info';
        if (stripos($line, 'error') !== false) $level = 'error';
        elseif (stripos($line, 'warning') !== false) $level = 'warning';
        elseif (stripos($line, 'debug') !== false) $level = 'debug';
        
        return [
            'timestamp' => $matches[1],
            'level' => $level,
            'message' => $line,
            'source' => $source
        ];
    }
    
    // Fallback for lines without clear timestamp
    if (strlen(trim($line)) > 0) {
        $level = 'info';
        if (stripos($line, 'error') !== false) $level = 'error';
        elseif (stripos($line, 'warning') !== false) $level = 'warning';
        
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $line,
            'source' => $source
        ];
    }
    
    return null;
}

function getPhpErrorLogs($lines = 50) {
    $logs = [];
    
    // Check PHP error log
    $phpErrorLog = ini_get('error_log');
    if ($phpErrorLog && file_exists($phpErrorLog)) {
        $content = file_get_contents($phpErrorLog);
        $logLines = array_filter(explode("\n", $content));
        
        foreach (array_slice($logLines, -$lines) as $line) {
            if (empty(trim($line))) continue;
            
            // PHP error log format: [timestamp] PHP Fatal error: message
            if (preg_match('/\[(.*?)\] PHP (.*?): (.+)/', $line, $matches)) {
                $logs[] = [
                    'timestamp' => $matches[1],
                    'level' => strpos($matches[2], 'Fatal') !== false ? 'error' : 'warning',
                    'message' => 'PHP ' . $matches[2] . ': ' . $matches[3],
                    'source' => 'php_error.log'
                ];
            }
        }
    }
    
    return $logs;
}

function getSystemLogs() {
    $logs = [];
    
    // Add system information
    $logs[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'info',
        'message' => 'System Status Check - Memory: ' . round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
        'source' => 'system'
    ];
    
    // Check disk space
    $freeSpace = disk_free_space('/app');
    $totalSpace = disk_total_space('/app');
    if ($freeSpace && $totalSpace) {
        $usedPercent = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);
        $level = $usedPercent > 90 ? 'error' : ($usedPercent > 80 ? 'warning' : 'info');
        
        $logs[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => "Disk Usage: {$usedPercent}% used",
            'source' => 'system'
        ];
    }
    
    return $logs;
}

try {
    switch ($type) {
        case 'error':
            $logs = getErrorLogs($lines);
            break;
        case 'system':
            $logs = getSystemLogs();
            break;
        default:
            $logs = getErrorLogs($lines);
    }
    
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'count' => count($logs),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'logs' => []
    ]);
}
?>