<?php
require_once __DIR__ . '/includes/autoload.php';
use ZeroAI\Core\DatabaseManager;

$db = DatabaseManager::getInstance();

echo "<h2>Debug: Agents Database</h2>";

// Check if agents table exists
try {
    $tables = $db->executeSQL("SELECT name FROM sqlite_master WHERE type='table' AND name='agents'");
    echo "<p><strong>Agents table exists:</strong> " . (count($tables) > 0 ? "YES" : "NO") . "</p>";
    
    if (count($tables) > 0) {
        // Get table structure
        $structure = $db->executeSQL("PRAGMA table_info(agents)");
        echo "<h3>Table Structure:</h3><pre>";
        print_r($structure);
        echo "</pre>";
        
        // Count agents
        $count = $db->executeSQL("SELECT COUNT(*) as count FROM agents");
        echo "<p><strong>Total agents:</strong> " . ($count[0]['count'] ?? 0) . "</p>";
        
        // Show all agents
        $agents = $db->executeSQL("SELECT * FROM agents LIMIT 20");
        echo "<h3>Agents Data:</h3><pre>";
        print_r($agents);
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

// Check database file path
echo "<h3>Database Info:</h3>";
echo "<p><strong>Database path:</strong> /app/data/zeroai.db</p>";
echo "<p><strong>File exists:</strong> " . (file_exists('/app/data/zeroai.db') ? "YES" : "NO") . "</p>";
if (file_exists('/app/data/zeroai.db')) {
    echo "<p><strong>File size:</strong> " . filesize('/app/data/zeroai.db') . " bytes</p>";
}
?>
