<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    $db = new \ZeroAI\Core\DatabaseManager();
    
    if ($action === 'get') {
        // Create table if not exists
        $db->executeSQL("CREATE TABLE IF NOT EXISTS default_prompts (id INTEGER PRIMARY KEY, prompt TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)", 'main');
        
        $result = $db->executeSQL("SELECT prompt FROM default_prompts WHERE id = 1", 'main');
        
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
        
        // Create table if not exists
        $db->executeSQL("CREATE TABLE IF NOT EXISTS default_prompts (id INTEGER PRIMARY KEY, prompt TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)", 'main');
        
        $db->executeSQL("INSERT OR REPLACE INTO default_prompts (id, prompt, created_at) VALUES (1, ?, datetime('now'))", 'main', [$prompt]);
        
        echo json_encode(['success' => true, 'message' => 'System prompt saved successfully']);
    }
    else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>