<?php
// Admin Logs Handler - Pure OOP
header('Content-Type: application/json');

require_once __DIR__ . '/includes/autoload.php';
use Models\Logs;
use Core\SystemInit;

try {
    SystemInit::initialize();
    $logsModel = new Logs();
    
    $type = $_GET['type'] ?? 'ai';
    $lines = (int)($_GET['lines'] ?? 50);
    
    $logs = match($type) {
        'error' => $logsModel->getErrorLogs($lines),
        'ai' => $logsModel->getAILogs($lines),
        'debug' => $logsModel->getDebugLogs($lines),
        default => $logsModel->getRecentLogs($type, $lines)
    };
    
    // Convert log lines to structured format
    $structuredLogs = [];
    foreach ($logs as $logLine) {
        if (preg_match('/\[(.*?)\] \[(.*?)\] (.*?)( \{.*\})?$/', $logLine, $matches)) {
            $structuredLogs[] = [
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'source' => 'ZeroAI',
                'message' => $matches[3],
                'context' => isset($matches[4]) ? $matches[4] : ''
            ];
        } else {
            $structuredLogs[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => 'INFO',
                'source' => 'System',
                'message' => $logLine,
                'context' => ''
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'logs' => $structuredLogs,
        'count' => count($structuredLogs),
        'type' => $type
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
