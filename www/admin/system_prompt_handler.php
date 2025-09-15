<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    $db = \ZeroAI\Core\DatabaseManager::getInstance();
    
    if ($action === 'get') {
        $result = $db->query("SELECT prompt FROM claude_prompts ORDER BY created_at DESC LIMIT 1");
        
        if (!empty($result)) {
            echo json_encode([
                'success' => true,
                'prompt' => $result[0]['prompt']
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
        $db->query("CREATE TABLE IF NOT EXISTS claude_prompts (id INTEGER PRIMARY KEY AUTOINCREMENT, prompt TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)", 'claude');
        
        // Use raw SQL since DatabaseManager parameter binding is broken
        $escapedPrompt = str_replace("'", "''", $prompt);
        $db->query("INSERT INTO claude_prompts (prompt) VALUES ('$escapedPrompt')", 'claude');
        
        echo json_encode(['success' => true, 'message' => 'System prompt saved successfully']);
    }
    else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


