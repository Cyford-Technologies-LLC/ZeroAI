<?php
header('Content-Type: application/json');
require_once __DIR__ . '/sqlite_manager.php';

$input = json_decode(file_get_contents('php://input'), true);
$newPrompt = $input['prompt'] ?? '';

if (!$newPrompt) {
    echo json_encode(['success' => false, 'error' => 'No prompt provided']);
    exit;
}

try {
    // Save to new memory database
    $memoryDir = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data';
    if (!is_dir($memoryDir)) mkdir($memoryDir, 0777, true);
    
    $dbPath = $memoryDir . '/claude_memory.db';
    $pdo = new PDO("sqlite:$dbPath");
    
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_prompts (id INTEGER PRIMARY KEY AUTOINCREMENT, prompt TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    
    // Insert new prompt
    $stmt = $pdo->prepare("INSERT INTO system_prompts (prompt) VALUES (?)");
    $stmt->execute([$newPrompt]);
    
    echo json_encode(['success' => true, 'message' => 'System prompt saved to memory database']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>