<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    $db = new \ZeroAI\Core\DatabaseManager();
    
    if ($action === 'get') {
        $result = $db->executeSQL("SELECT prompt FROM claude_prompts ORDER BY created_at DESC LIMIT 1", 'claude');
        
        if (!empty($result[0]['data'])) {
            echo json_encode([
                'success' => true,
                'prompt' => $result[0]['data'][0]['prompt']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'prompt' => 'You are Claude, integrated into ZeroAI.'
            ]);
        }
    } 
    elseif ($action === 'save') {
        $prompt = $input['prompt'] ?? '';
        
        if (!$prompt) {
            echo json_encode(['success' => false, 'error' => 'Prompt required']);
            exit;
        }
        
        $db->executeSQL("INSERT INTO claude_prompts (prompt) VALUES (?)", 'claude', [$prompt]);
        
        echo json_encode(['success' => true, 'message' => 'System prompt saved successfully']);
    }
    else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>