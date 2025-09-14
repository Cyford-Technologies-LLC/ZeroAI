<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get peer data from Python API
$python_api_url = 'http://localhost:3939/peers';

try {
    $context = stream_context_create([
        'http' => [
            'timeout' => 2,
            'method' => 'GET'
        ]
    ]);
    
    $response = file_get_contents($python_api_url, false, $context);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        echo json_encode([
            'success' => true,
            'peers' => $data['peers'] ?? [],
            'timestamp' => time()
        ]);
    } else {
        throw new Exception('Failed to fetch from Python API');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'peers' => [],
        'error' => 'Python API unavailable',
        'timestamp' => time()
    ]);
}
?>