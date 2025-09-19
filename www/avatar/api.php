<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$requestUri = $_SERVER['REQUEST_URI'];

if (strpos($requestUri, '/generate') !== false) {
    // Avatar generation endpoint
    $input = json_decode(file_get_contents('php://input'), true);
    $prompt = $input['prompt'] ?? 'Hello!';
    
    // For now, return a placeholder response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'Avatar generation not yet implemented', 'prompt' => $prompt]);
    
} elseif (strpos($requestUri, '/analyze') !== false) {
    // Image analysis endpoint
    $question = $_POST['question'] ?? 'What do you see?';
    
    // For now, return a placeholder response
    header('Content-Type: application/json');
    echo json_encode(['analysis' => 'Image analysis not yet implemented. Question: ' . $question]);
    
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}
?>