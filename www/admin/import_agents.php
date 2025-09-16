<?php
session_start();
require_once __DIR__ . '/../src/Core/DatabaseManager.php';
require_once __DIR__ . '/../src/Core/AgentMethods.php';
require_once __DIR__ . '/../src/Core/UserMethods.php';
require_once __DIR__ . '/../src/Core/CompanyMethods.php';
require_once __DIR__ . '/../src/Core/TaskMethods.php';

$db = \ZeroAI\Core\DatabaseManager::getInstance();
$message = '';
$error = '';

// Read and execute the SQL file
$sqlFile = __DIR__ . '/../../data/all_agents_complete.sql';
if (file_exists($sqlFile)) {
    $sql = file_get_contents($sqlFile);
    
    try {
        // Execute SQL directly
        $db->query($sql);
        
        // Count imported agents
        $agents = $db->getAllAgents();
        $agentCount = count($agents);
        
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


