<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/autonomous_trainer.php';

try {
    $trainer = new AutonomousTrainer();
    $improvements = $trainer->runAutonomousTraining();
    
    // Log autonomous actions
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => 'autonomous_training',
        'improvements' => $improvements,
        'agent' => 'claude'
    ];
    
    file_put_contents('/app/logs/claude_autonomous.log', json_encode($logEntry) . "\n", FILE_APPEND);
    
    echo json_encode([
        'success' => true,
        'improvements' => $improvements,
        'message' => 'Autonomous training completed. ' . count($improvements) . ' improvements made.'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Autonomous training failed: ' . $e->getMessage()
    ]);
}
?>