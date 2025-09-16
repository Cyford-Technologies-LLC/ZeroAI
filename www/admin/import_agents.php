<?php
session_start();
require_once __DIR__ . '/../api/agent_db.php';

$agentDB = new AgentDB();
$message = '';
$error = '';

// Read and execute the SQL file
$sqlFile = __DIR__ . '/../../data/all_agents_complete.sql';
if (file_exists($sqlFile)) {
    $sql = file_get_contents($sqlFile);
    
    try {
        // Execute SQL directly on agents.db
        $agentDB->db->exec($sql);
        
        // Count imported agents
        $agents = $agentDB->getAllAgents();
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


