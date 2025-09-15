<?php
header('Content-Type: application/json');

try {
    // Simple token usage stats - since you use dynamic routing to powerful peers
    // Claude usage should be minimal as local Ollama is just fallback
    $stats = [
        'hour' => [
            'models' => [],
            'total_tokens' => 0,
            'total_cost' => 0.00,
            'total_requests' => 0
        ],
        'day' => [
            'models' => [],
            'total_tokens' => 0,
            'total_cost' => 0.00,
            'total_requests' => 0
        ],
        'week' => [
            'models' => [],
            'total_tokens' => 0,
            'total_cost' => 0.00,
            'total_requests' => 0
        ],
        'total' => [
            'models' => [],
            'total_tokens' => 0,
            'total_cost' => 0.00,
            'total_requests' => 0
        ]
    ];
    
    // Check if token usage file exists
    $tokenFile = '/app/data/token_usage.json';
    if (file_exists($tokenFile)) {
        $tokenData = json_decode(file_get_contents($tokenFile), true);
        if ($tokenData) {
            $stats = array_merge($stats, $tokenData);
        }
    }
    
    echo json_encode(['success' => true, 'stats' => $stats]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => true, 
        'stats' => [
            'hour' => ['models' => [], 'total_tokens' => 0, 'total_cost' => 0.00, 'total_requests' => 0],
            'day' => ['models' => [], 'total_tokens' => 0, 'total_cost' => 0.00, 'total_requests' => 0],
            'week' => ['models' => [], 'total_tokens' => 0, 'total_cost' => 0.00, 'total_requests' => 0],
            'total' => ['models' => [], 'total_tokens' => 0, 'total_cost' => 0.00, 'total_requests' => 0]
        ]
    ]);
}
?>
