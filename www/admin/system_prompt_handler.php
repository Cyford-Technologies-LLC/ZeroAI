<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    $db = new \ZeroAI\Core\DatabaseManager();
    
    if ($action === 'get') {
        // Use Claude's own database
        $memoryDir = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data';
        if (!is_dir($memoryDir)) {
            mkdir($memoryDir, 0777, true);
        }
        
        $dbPath = $memoryDir . '/claude_memory.db';
        $pdo = new \PDO("sqlite:$dbPath");
        
        // Create table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS claude_prompts (id INTEGER PRIMARY KEY AUTOINCREMENT, prompt TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        
        $stmt = $pdo->prepare("SELECT prompt FROM claude_prompts ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['prompt']) {
            echo json_encode([
                'success' => true,
                'prompt' => $result['prompt']
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
        
        // Use Claude's own database
        $memoryDir = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data';
        if (!is_dir($memoryDir)) {
            mkdir($memoryDir, 0777, true);
        }
        
        $dbPath = $memoryDir . '/claude_memory.db';
        $pdo = new \PDO("sqlite:$dbPath");
        
        // Create table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS claude_prompts (id INTEGER PRIMARY KEY AUTOINCREMENT, prompt TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        
        $pdo->prepare("INSERT INTO claude_prompts (prompt) VALUES (?)")->execute([$prompt]);
        
        echo json_encode(['success' => true, 'message' => 'System prompt saved successfully']);
    }
    else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>