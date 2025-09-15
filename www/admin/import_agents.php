<?php
session_start();
require_once __DIR__ . '/includes/autoload.php';

use ZeroAI\Core\DatabaseManager;

$db = DatabaseManager::getInstance();

// Read and execute the SQL file
$sqlFile = __DIR__ . '/../../data/all_agents_complete.sql';
if (file_exists($sqlFile)) {
    $sql = file_get_contents($sqlFile);
    
    // Execute the SQL directly (it should work with our table structure)
    try {
        $pdo = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='agents'");
        if (empty($pdo)) {
            // Create table first
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
        }
        
        // Execute the import SQL
        $db->query($sql);
        echo "✅ Agents imported successfully!";
    } catch (Exception $e) {
        echo "❌ Error importing agents: " . $e->getMessage();
    }
} else {
    echo "❌ SQL file not found: " . $sqlFile;
}

// Redirect back to agents page
header("Location: agents.php");
exit;
?>