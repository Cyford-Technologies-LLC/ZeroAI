<?php
header('Content-Type: application/json');

require_once __DIR__ . '/includes/autoload.php';

try {
    $peerManager = \ZeroAI\Core\PeerManager::getInstance();
    $peers = $peerManager->getPeers();
    
    // If no peers in config, show local system
    if (empty($peers)) {
        $peers = [[
            'name' => 'Local System',
            'ip' => '127.0.0.1',
            'port' => 8080,
            'status' => 'online',
            'models' => ['llama3.2:1b'],
            'memory_gb' => round(memory_get_usage(true) / 1024 / 1024 / 1024, 1),
            'gpu_available' => false,
            'gpu_memory_gb' => 0,
            'last_check' => date('Y-m-d H:i:s')
        ]];
    }
    
    echo json_encode([
        'success' => true,
        'peers' => $peers
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'peers' => []
    ]);
}
?>