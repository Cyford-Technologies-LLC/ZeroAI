<?php
header('Content-Type: application/json');

try {
    // Try multiple possible config paths
    $configPaths = [
        '/app/config/peers.json',
        __DIR__ . '/../config/peers.json',
        __DIR__ . '/../../config/peers.json'
    ];
    
    $peers = [];
    $configFound = false;
    
    foreach ($configPaths as $configPath) {
        if (file_exists($configPath)) {
            $content = file_get_contents($configPath);
            $data = json_decode($content, true);
            if ($data && isset($data['peers'])) {
                $peers = $data['peers'];
                $configFound = true;
                break;
            }
        }
    }
    
    // Process peers and add status
    foreach ($peers as &$peer) {
        // Convert timestamp to readable format if needed
        if (isset($peer['last_updated']) && is_numeric($peer['last_updated'])) {
            $peer['last_check'] = date('M j, H:i', (int)$peer['last_updated']);
        }
        
        // Set status based on available flag
        $peer['status'] = ($peer['available'] ?? false) ? 'online' : 'offline';
        
        // Ensure models is array
        if (!isset($peer['models']) || !is_array($peer['models'])) {
            $peer['models'] = [];
        }
    }
    
    // If no peers found, add local fallback
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
            'last_check' => date('M j, H:i')
        ]];
    }
    
    echo json_encode([
        'success' => true,
        'peers' => $peers,
        'config_found' => $configFound
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'peers' => []
    ]);
}
?>
