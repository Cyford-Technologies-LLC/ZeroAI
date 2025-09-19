<?php
// Prevent any HTML output
ob_start();

// Set headers first
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'generate') {
        $input = json_decode(file_get_contents('php://input'), true);
        $prompt = $input['prompt'] ?? 'Hello!';
        echo json_encode(['status' => 'success', 'message' => 'Avatar generation requested: ' . $prompt]);
        
    } elseif ($action === 'analyze') {
        $question = $_POST['question'] ?? 'What do you see?';
        echo json_encode(['analysis' => 'Image analysis placeholder: ' . $question]);
        
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

ob_end_flush();
?>