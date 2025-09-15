<?php
session_start();
require_once __DIR__ . '/includes/autoload.php';

use ZeroAI\Core\DatabaseManager;

$db = DatabaseManager::getInstance();
$message = '';
$error = '';

// Read and execute the SQL file
$sqlFile = __DIR__ . '/../../data/all_agents_complete.sql';
if (file_exists($sqlFile)) {
    $sql = file_get_contents($sqlFile);
    
    try {
        // Create table with correct structure first
        $db->query("CREATE TABLE IF NOT EXISTS agents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            role TEXT NOT NULL,
            goal TEXT,
            backstory TEXT,
            tools TEXT,
            config TEXT,
            status TEXT DEFAULT 'active',
            is_core BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !str_starts_with($statement, '--')) {
                $db->query($statement);
            }
        }
        
        // Count imported agents
        $count = $db->query("SELECT COUNT(*) as count FROM agents");
        $agentCount = $count[0]['count'] ?? 0;
        
        $message = "✅ Agents imported successfully! Total agents: {$agentCount}";
        
    } catch (Exception $e) {
        $error = "❌ Error importing agents: " . $e->getMessage();
    }
} else {
    $error = "❌ SQL file not found: " . $sqlFile;
}

// Store message in session and redirect
if ($message) {
    $_SESSION['import_message'] = $message;
} elseif ($error) {
    $_SESSION['import_error'] = $error;
}

header("Location: agents.php");
exit;
?>