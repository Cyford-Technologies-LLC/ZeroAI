<?php
// Prevent any HTML output
ob_start();

// Set headers first
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
// Content-Type set dynamically based on response

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'generate') {
        $input = json_decode(file_get_contents('php://input'), true);
        $prompt = $input['prompt'] ?? 'Hello!';
        
        // Call Docker container avatar service
        $ch = curl_init('http://localhost:7860/generate');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['prompt' => $prompt]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Curl error: ' . $error]);
            return;
        }
        
        if ($httpCode !== 200) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'HTTP error: ' . $httpCode]);
            return;
        }
        
        if ($result) {
            // Set proper headers for video response
            header('Content-Type: video/mp4');
            header('Content-Disposition: attachment; filename="avatar.mp4"');
            echo $result;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Avatar container not responding']);
        }
        
    } elseif ($action === 'analyze') {
        $question = $_POST['question'] ?? 'What do you see?';
        echo json_encode(['analysis' => 'Image analysis placeholder: ' . $question]);
        
    } elseif ($action === 'status') {
        // Check if avatar container is running
        $ch = curl_init('http://localhost:7860/health');
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