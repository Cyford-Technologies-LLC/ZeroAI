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
        
        // Call Docker container avatar service
        $ch = curl_init('http://avatar:7860/generate');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['prompt' => $prompt]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        
        if ($result) {
            echo $result;
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Avatar container not running. Text: ' . $prompt]);
        }
        
    } elseif ($action === 'analyze') {
        $question = $_POST['question'] ?? 'What do you see?';
        echo json_encode(['analysis' => 'Image analysis placeholder: ' . $question]);
        
    } elseif ($action === 'status') {
        // Check if avatar container is running
        $ch = curl_init('http://avatar:7860/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($result && $httpCode === 200) {
            echo json_encode(['status' => 'Avatar container is running', 'response' => json_decode($result)]);
        } else {
            echo json_encode(['status' => 'Avatar container not responding', 'error' => 'Container may not be running']);
        }
        
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

ob_end_flush();
?>