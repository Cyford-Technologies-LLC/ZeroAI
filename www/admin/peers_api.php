<?php
header('Content-Type: application/json');

try {
    // Validate URL to prevent SSRF
    $url = 'http://localhost:3939/peers';
    $parsedUrl = parse_url($url);
    if ($parsedUrl['host'] !== 'localhost' || $parsedUrl['port'] != 3939) {
        throw new Exception('Invalid URL');
    }
    
    $response = @file_get_contents($url, false, stream_context_create([
        'http' => ['timeout' => 1, 'method' => 'GET']
    ]));
    
    if ($response !== false) {
        $data = json_decode($response, true);
        echo json_encode([
            'success' => true,
            'peers' => $data['peers'] ?? []
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'peers' => []
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => true,
        'peers' => []
    ]);
}
?>