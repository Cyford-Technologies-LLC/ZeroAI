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
        
        // Add all missing CrewAI columns if they don't exist
        $columns = [
            'tools TEXT',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP',
            'llm_model TEXT DEFAULT "local"',
            'verbose BOOLEAN DEFAULT 0',
            'allow_delegation BOOLEAN DEFAULT 1',
            'allow_code_execution BOOLEAN DEFAULT 0',
            'memory BOOLEAN DEFAULT 0',
            'max_iter INTEGER DEFAULT 25',
            'max_rpm INTEGER',
            'max_execution_time INTEGER',
            'max_retry_limit INTEGER DEFAULT 2',
            'learning_enabled BOOLEAN DEFAULT 0',
            'learning_rate REAL DEFAULT 0.05',
            'feedback_incorporation TEXT DEFAULT "immediate"',
            'system_template TEXT',
            'prompt_template TEXT',
            'response_template TEXT'
        ];
        
        foreach ($columns as $column) {
            try {
                $db->query("ALTER TABLE agents ADD COLUMN {$column}");
            } catch (Exception $e) {
                // Column already exists, ignore
            }
        }
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !str_starts_with($statement, '--')) {
                $db->query($statement);
            }
        }
        
        // Fill in CrewAI defaults for any missing values
        $db->query("UPDATE agents SET 
            llm_model = COALESCE(llm_model, 'llama3.2:1b'),
            verbose = COALESCE(verbose, 0),
            allow_delegation = COALESCE(allow_delegation, 1),
            allow_code_execution = COALESCE(allow_code_execution, 0),
            memory = COALESCE(memory, 1),
            max_iter = COALESCE(max_iter, 25),
            max_retry_limit = COALESCE(max_retry_limit, 2),
            learning_enabled = COALESCE(learning_enabled, 1),
            learning_rate = COALESCE(learning_rate, 0.05),
            feedback_incorporation = COALESCE(feedback_incorporation, 'immediate'),
            system_template = COALESCE(system_template, 'You are {role}. {goal}\n\nBackground: {backstory}'),
            config = COALESCE(config, '{\"temperature\": 0.7, \"max_tokens\": 2000}'),
            updated_at = CURRENT_TIMESTAMP
            WHERE id > 0");
        
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