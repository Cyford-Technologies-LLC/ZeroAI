<?php
require_once __DIR__ . '/includes/autoload.php';

header('Content-Type: application/json');

try {
    $db = \ZeroAI\Core\DatabaseManager::getInstance();
    
    // Check if system_prompts table exists, create if not
    $db->query("CREATE TABLE IF NOT EXISTS system_prompts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prompt TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $result = $db->query("SELECT prompt FROM system_prompts ORDER BY created_at DESC LIMIT 1");
    
    if ($result && count($result) > 0) {
        echo json_encode(['success' => true, 'prompt' => $result[0]['prompt']]);
    } else {
        // Return default prompt if none exists
        $defaultPrompt = "You are Claude, an AI assistant integrated into the ZeroAI system. You help with code review, system optimization, and development guidance.";
        echo json_encode(['success' => true, 'prompt' => $defaultPrompt]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


