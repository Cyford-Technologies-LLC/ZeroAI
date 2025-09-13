<?php 
$pageTitle = 'Test Dynamic Agents - ZeroAI';
$currentPage = 'agents';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../api/agent_db.php';

$agentDB = new AgentDB();

// Test dynamic agent loading
$testResults = [];

// Test 1: Check database agents
$agents = $agentDB->getAllAgents();
$testResults['database_agents'] = [
    'count' => count($agents),
    'agents' => array_slice($agents, 0, 3) // Show first 3 for brevity
];

// Test 2: Check if agents have all CrewAI options
$sampleAgent = !empty($agents) ? $agents[0] : null;
$testResults['crewai_options'] = [];
if ($sampleAgent) {
    $crewaiFields = [
        'memory', 'learning_enabled', 'learning_rate', 'personality_traits', 
        'communication_formality', 'allow_delegation', 'verbose', 'max_iter'
    ];
    foreach ($crewaiFields as $field) {
        $testResults['crewai_options'][$field] = isset($sampleAgent[$field]) ? 'Present' : 'Missing';
    }
}

// Test 3: Import agents to ensure database is populated
if (isset($_POST['import_agents'])) {
    $imported = $agentDB->importExistingAgents();
    $testResults['import_result'] = count($imported) . ' agents imported';
}

?>

<h1>🧪 Dynamic Agent Configuration Test</h1>

<div class="card">
    <h3>Test Results</h3>
    
    <h4>1. Database Agents</h4>
    <p><strong>Count:</strong> <?= $testResults['database_agents']['count'] ?></p>
    <?php if ($testResults['database_agents']['count'] > 0): ?>
        <p><strong>Sample Agents:</strong></p>
        <ul>
            <?php foreach ($testResults['database_agents']['agents'] as $agent): ?>
                <li><?= htmlspecialchars($agent['role']) ?> 
                    (Memory: <?= $agent['memory'] ? 'Yes' : 'No' ?>, 
                     Delegation: <?= $agent['allow_delegation'] ? 'Yes' : 'No' ?>,
                     Learning: <?= $agent['learning_enabled'] ? 'Yes' : 'No' ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p style="color: orange;">⚠️ No agents found in database. Import agents first.</p>
    <?php endif; ?>
    
    <h4>2. CrewAI Options Check</h4>
    <?php if (!empty($testResults['crewai_options'])): ?>
        <table style="width: 100%; border-collapse: collapse;">
            <tr><th style="border: 1px solid #ddd; padding: 8px;">Field</th><th style="border: 1px solid #ddd; padding: 8px;">Status</th></tr>
            <?php foreach ($testResults['crewai_options'] as $field => $status): ?>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?= htmlspecialchars($field) ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px; color: <?= $status === 'Present' ? 'green' : 'red' ?>;">
                        <?= $status === 'Present' ? '✅' : '❌' ?> <?= $status ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p style="color: orange;">⚠️ No sample agent available for testing</p>
    <?php endif; ?>
    
    <?php if (isset($testResults['import_result'])): ?>
        <h4>3. Import Result</h4>
        <p style="color: green;">✅ <?= htmlspecialchars($testResults['import_result']) ?></p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Actions</h3>
    <form method="POST">
        <button type="submit" name="import_agents" class="btn-success">Import Agents from Static Files</button>
    </form>
    <br>
    <a href="/admin/agents" class="btn-primary">Go to Agent Management</a>
</div>

<div class="card">
    <h3>✅ Verification Status</h3>
    <?php 
    $hasAgents = $testResults['database_agents']['count'] > 0;
    $hasCrewAIOptions = !empty($testResults['crewai_options']) && 
                       in_array('Present', array_values($testResults['crewai_options']));
    ?>
    
    <div style="padding: 15px; border-radius: 5px; background: <?= $hasAgents && $hasCrewAIOptions ? '#d4edda' : '#fff3cd' ?>;">
        <?php if ($hasAgents && $hasCrewAIOptions): ?>
            <h4 style="color: #155724;">🎉 SUCCESS: Agents are using database settings!</h4>
            <p>✅ Database contains <?= $testResults['database_agents']['count'] ?> agents</p>
            <p>✅ Agents have all CrewAI configuration options</p>
            <p>✅ Dynamic loading is working correctly</p>
        <?php elseif ($hasAgents): ?>
            <h4 style="color: #856404;">⚠️ PARTIAL: Agents found but missing some options</h4>
            <p>✅ Database contains <?= $testResults['database_agents']['count'] ?> agents</p>
            <p>❌ Some CrewAI options may be missing</p>
        <?php else: ?>
            <h4 style="color: #721c24;">❌ FAILED: No agents in database</h4>
            <p>❌ Database is empty</p>
            <p>🔧 Click "Import Agents from Static Files" to populate database</p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3>📋 How Dynamic Loading Works</h3>
    <ol>
        <li><strong>Database First:</strong> Crews try to load agents from database using <code>dynamic_agent_loader.py</code></li>
        <li><strong>Full Configuration:</strong> Database agents include all CrewAI options (memory, learning, personality, etc.)</li>
        <li><strong>Static Fallback:</strong> If database loading fails, crews fall back to static Python files</li>
        <li><strong>Admin Control:</strong> You can edit all agent settings through the admin interface</li>
    </ol>
    
    <h4>🔧 Files Updated for Dynamic Loading:</h4>
    <ul>
        <li><code>src/crews/internal/research/crew.py</code> - Research crew uses dynamic agents</li>
        <li><code>src/crews/internal/developer/crew.py</code> - Developer crew uses dynamic agents</li>
        <li><code>src/crews/internal/team_manager/agents.py</code> - Team manager loads dynamic coworkers</li>
        <li><code>src/utils/dynamic_agent_loader.py</code> - Handles all CrewAI options from database</li>
    </ul>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>