<?php
require_once '/app/www/api/agent_db.php';

$agentDB = new AgentDB();

echo "=== Clearing existing agents ===\n";

// Clear all agents
$db = new SQLite3('/app/data/agents.db');
$db->exec('DELETE FROM agent_capabilities');
$db->exec('DELETE FROM agents');
$db->exec('DELETE FROM crews');
$db->exec('DELETE FROM crew_agents');
$db->exec('DELETE FROM tasks');
$db->close();

echo "Database cleared.\n";

echo "\n=== Re-importing all agents ===\n";
$imported = $agentDB->importExistingAgents();
echo "Imported " . count($imported) . " agents:\n";
foreach ($imported as $agent) {
    echo "- " . $agent['role'] . "\n";
}

echo "\n=== Final count ===\n";
$all = $agentDB->getAllAgents();
echo "Total agents in database: " . count($all) . "\n";
?>