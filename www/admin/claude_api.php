<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'chat') {
    $message = $input['message'] ?? '';
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message required']);
        exit;
    }
    
    // Read API key from .env
    $envFile = '/app/.env';
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        preg_match('/ANTHROPIC_API_KEY=(.+)/', $envContent, $matches);
        $apiKey = isset($matches[1]) ? trim($matches[1]) : '';
        
        if ($apiKey) {
            // Simple Claude API call
            $response = callClaudeAPI($message, $apiKey);
            echo json_encode(['success' => true, 'response' => $response]);
        } else {
            echo json_encode(['success' => false, 'error' => 'API key not configured']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Environment file not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function callClaudeAPI($message, $apiKey) {
    $url = 'https://api.anthropic.com/v1/messages';
    
    $data = [
        'model' => 'claude-3-5-sonnet-20241022',
        'max_tokens' => 1000,
        'messages' => [
            [
                'role' => 'user',
                'content' => $message
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $decoded = json_decode($response, true);
        return $decoded['content'][0]['text'] ?? 'No response received';
    } else {
        return 'API Error: HTTP ' . $httpCode;
    }
}
?>