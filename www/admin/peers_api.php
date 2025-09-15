<?php
header('Content-Type: application/json');

try {
    // Simple peer data from config file
    $configPath = '/app/config/peers.json';
    $peers = [];
    
    if (file_exists($configPath)) {
        $content = file_get_contents($configPath);
        $data = json_decode($content, true);
        $peers = $data['peers'] ?? [];
    }
    
    // If no peers in config, show local system
    if (empty($peers)) {
        $peers = [[
            'name' => 'Local System (Fallback)',
            'ip' => '127.0.0.1',
            'port' => 8080,
            'status' => 'online',
            'models' => ['auto'],
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