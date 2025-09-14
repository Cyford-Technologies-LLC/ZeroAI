<?php
header('Content-Type: application/json');

try {
    $response = @file_get_contents('http://localhost:3939/peers', false, stream_context_create([
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