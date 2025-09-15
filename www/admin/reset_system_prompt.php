<?php
require_once __DIR__ . '/includes/autoload.php';

header('Content-Type: application/json');

try {
    $db = \ZeroAI\Core\DatabaseManager::getInstance();
    
    $defaultPrompt = "You are Claude, an AI assistant integrated into the ZeroAI system. You help with code review, system optimization, and development guidance. You have access to file commands (@file, @create, @edit, @append, @delete) and system commands (@agents, @crews, @list, @search).";
    
    // Create table if not exists
    $db->executeSQL("CREATE TABLE IF NOT EXISTS system_prompts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prompt TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert default prompt
    $db->executeSQL("INSERT INTO system_prompts (prompt) VALUES (?)", [$defaultPrompt]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
