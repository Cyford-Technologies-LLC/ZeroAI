<?php
require_once '/app/www/api/agent_db.php';

$agentDB = new AgentDB();

echo "=== Testing Agent Import ===\n";

// Test parsing team_manager agents.py
$content = file_get_contents('/app/src/crews/internal/team_manager/agents.py');
echo "File size: " . strlen($content) . " bytes\n";

// Test AVAILABLE_AGENTS parsing
if (preg_match('/AVAILABLE_AGENTS\s*=\s*\{([\s\S]*?)\}(?=\s*\n\s*\n|\s*def|\s*#|$)/m', $content, $match)) {
    echo "Found AVAILABLE_AGENTS block\n";
    echo "Block size: " . strlen($match[1]) . " bytes\n";
    
    // Parse it
    $reflection = new ReflectionClass($agentDB);
    $method = $reflection->getMethod('parseAvailableAgents');
    $method->setAccessible(true);
    $agents = $method->invoke($agentDB, $match[1]);
    
    echo "Parsed " . count($agents) . " agents from AVAILABLE_AGENTS\n";
    foreach ($agents as $agent) {
        echo "- " . $agent['role'] . "\n";
    }
} else {
    echo "AVAILABLE_AGENTS not found\n";
}

// Test function parsing
preg_match_all('/def\s+(create_\w*agent|get_\w*agent)\s*\([^)]*\):/mi', $content, $matches);
echo "\nFound " . count($matches[0]) . " agent creation functions:\n";
foreach ($matches[1] as $func) {
    echo "- $func\n";
}

echo "\n=== Current Database Agents ===\n";
$existing = $agentDB->getAllAgents();
echo "Database has " . count($existing) . " agents:\n";
foreach ($existing as $agent) {
    echo "- " . $agent['role'] . " (ID: " . $agent['id'] . ")\n";
}
?>