<?php
// Ensure Claude is in the database
require_once __DIR__ . '/../api/agent_db.php';

$agentDB = new AgentDB();

// Check if Claude exists
$agents = $agentDB->getAllAgents();
$claudeExists = false;
foreach ($agents as $agent) {
    if ($agent['name'] === 'Claude AI Assistant') {
        $claudeExists = true;
        echo "Claude found in database with ID: " . $agent['id'] . "\n";
        echo "Current role: " . $agent['role'] . "\n";
        echo "Current goal: " . $agent['goal'] . "\n";
        break;
    }
}

if (!$claudeExists) {
    echo "Claude not found. Adding to database...\n";
    
    $claudeId = $agentDB->createAgent([
        'name' => 'Claude AI Assistant',
        'role' => 'Senior AI Architect & Code Review Specialist',
        'goal' => 'Provide expert code review, architectural guidance, and strategic optimization recommendations to enhance ZeroAI system performance and development quality.',
        'backstory' => 'I am Claude, an advanced AI assistant created by Anthropic. I specialize in software architecture, code optimization, and strategic technical guidance. I work alongside your ZeroAI development team to ensure high-quality deliverables, identify potential issues early, and provide insights that enhance overall project success.',
        'status' => 'active',
        'llm_model' => 'claude',
        'is_core' => 1
    ]);
    
    echo "Claude added with ID: $claudeId\n";
}
?>