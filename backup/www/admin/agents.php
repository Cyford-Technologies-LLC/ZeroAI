<?php 
$pageTitle = 'Agent Management - ZeroAI';
$currentPage = 'agents';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../api/agent_db.php';

$agentDB = new AgentDB();

// Handle actions
if ($_POST['action'] ?? '' === 'import_agents') {
    $imported = $agentDB->importExistingAgents();
    $message = count($imported) . ' agents imported successfully';
} elseif ($_POST['action'] ?? '' === 'update_agent') {
    $updates = [
        'name' => $_POST['name'],
        'role' => $_POST['role'],
        'goal' => $_POST['goal'],
        'backstory' => $_POST['backstory'],
        'status' => $_POST['status'] ?? 'active',
        'llm_model' => $_POST['llm_model'] ?? 'local',
        'verbose' => isset($_POST['verbose']) ? 1 : 0,
        'allow_delegation' => isset($_POST['allow_delegation']) ? 1 : 0,
        'max_iter' => (int)($_POST['max_iter'] ?? 25),
        'max_rpm' => !empty($_POST['max_rpm']) ? (int)$_POST['max_rpm'] : null,
        'max_execution_time' => !empty($_POST['max_execution_time']) ? (int)$_POST['max_execution_time'] : null,
        'allow_code_execution' => isset($_POST['allow_code_execution']) ? 1 : 0,
        'max_retry_limit' => (int)($_POST['max_retry_limit'] ?? 2),
        'memory' => isset($_POST['memory']) ? 1 : 0,
        'learning_enabled' => isset($_POST['learning_enabled']) ? 1 : 0,
        'learning_rate' => (float)($_POST['learning_rate'] ?? 0.05),
        'feedback_incorporation' => $_POST['feedback_incorporation'] ?? 'immediate',
        'adaptation_strategy' => $_POST['adaptation_strategy'] ?? 'progressive',
        'personality_traits' => $_POST['personality_traits'] ?? null,
        'personality_quirks' => $_POST['personality_quirks'] ?? null,
        'communication_preferences' => $_POST['communication_preferences'] ?? null,
        'communication_formality' => $_POST['communication_formality'] ?? 'professional',
        'communication_verbosity' => $_POST['communication_verbosity'] ?? 'concise',
        'communication_tone' => $_POST['communication_tone'] ?? 'confident',
        'communication_technical_level' => $_POST['communication_technical_level'] ?? 'intermediate',
        'tools' => $_POST['tools'] ?? null,
        'knowledge' => $_POST['knowledge'] ?? null,
        'coworkers' => $_POST['coworkers'] ?? null
    ];
    $agentDB->updateAgent($_POST['agent_id'], $updates);
    $message = 'Agent updated successfully';
}

$agents = $agentDB->getActiveAgents();
?>

<h1>Agent Management</h1>

<?php if (isset($message)): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
    
<div class="card">
    <h3>Import Existing Agents</h3>
    <p>Import agents from your existing ZeroAI internal crews and Python files.</p>
    <form method="POST">
        <button type="submit" name="action" value="import_agents" class="btn-success">Import All Existing Agents</button>
    </form>
</div>

<div class="card">
    <h3>Active Agents (<?= count($agents) ?>)</h3>
    
    <?php foreach ($agents as $agent): ?>
        <div class="agent-item" style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 8px; <?= $agent['is_core'] ? 'border-left: 4px solid #007bff;' : '' ?>">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div style="flex: 1;">
                    <h4><?= htmlspecialchars($agent['name']) ?> 
                        <?= $agent['is_core'] ? '<span style="color: #007bff; font-size: 0.8em;">(Core Agent)</span>' : '' ?>
                    </h4>
                    <p><strong>Role:</strong> <?= htmlspecialchars($agent['role']) ?></p>
                    <p><strong>Goal:</strong> <?= htmlspecialchars($agent['goal']) ?></p>
                    <p><strong>Backstory:</strong> <?= htmlspecialchars(substr($agent['backstory'], 0, 100)) ?>...</p>
                    <p><strong>Status:</strong> <span style="color: #28a745;"><?= ucfirst($agent['status']) ?></span></p>
                </div>
                <div style="display: flex; flex-direction: column; gap: 5px;">
                    <button onclick="editAgent(<?= $agent['id'] ?>)" class="btn-warning">Edit</button>
                    <button onclick="chatWithAgent(<?= $agent['id'] ?>)" class="btn-success">Chat</button>
                    <button onclick="viewTasks(<?= $agent['id'] ?>)">View Tasks</button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <?php if (empty($agents)): ?>
        <p>No agents found. Click "Import All Existing Agents" to load your ZeroAI internal crew.</p>
    <?php endif; ?>
</div>

<!-- Edit Agent Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div style="position: absolute; top: 20px; left: 50%; transform: translateX(-50%); background: white; padding: 20px; border-radius: 8px; width: 800px; max-width: 95%; margin-bottom: 20px;">
        <h3>Edit Agent - All CrewAI Options</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update_agent">
            <input type="hidden" name="agent_id" id="editAgentId">
            
            <!-- Basic Info -->
            <div class="form-section">
                <h4>Basic Information</h4>
                <input type="text" name="name" id="editName" placeholder="Agent Name" required style="width: 100%; margin-bottom: 10px;">
                <input type="text" name="role" id="editRole" placeholder="Agent Role" required style="width: 100%; margin-bottom: 10px;">
                <textarea name="goal" id="editGoal" placeholder="Agent Goal" rows="2" required style="width: 100%; margin-bottom: 10px;"></textarea>
                <textarea name="backstory" id="editBackstory" placeholder="Agent Backstory" rows="3" required style="width: 100%; margin-bottom: 10px;"></textarea>
                <select name="status" id="editStatus" style="width: 100%; margin-bottom: 10px;">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select name="llm_model" id="editLlmModel" style="width: 100%; margin-bottom: 10px;">
                    <option value="local">Local</option>
                    <option value="openai">OpenAI</option>
                    <option value="claude">Claude</option>
                </select>
            </div>

            <!-- CrewAI Core Options -->
            <div class="form-section">
                <h4>CrewAI Core Options</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <label><input type="checkbox" name="verbose" id="editVerbose"> Verbose Output</label>
                    <label><input type="checkbox" name="allow_delegation" id="editAllowDelegation"> Allow Delegation</label>
                    <label><input type="checkbox" name="allow_code_execution" id="editAllowCodeExecution"> Allow Code Execution</label>
                    <label><input type="checkbox" name="memory" id="editMemory"> Enable Memory</label>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <input type="number" name="max_iter" id="editMaxIter" placeholder="Max Iterations (25)" min="1" max="100">
                    <input type="number" name="max_rpm" id="editMaxRpm" placeholder="Max RPM (optional)" min="1">
                    <input type="number" name="max_execution_time" id="editMaxExecutionTime" placeholder="Max Exec Time (s)" min="1">
                </div>
                <input type="number" name="max_retry_limit" id="editMaxRetryLimit" placeholder="Max Retry Limit (2)" min="0" max="10" style="width: 100%; margin-bottom: 10px;">
            </div>
            
            <!-- Learning Configuration -->
            <div class="form-section">
                <h4>Learning Configuration</h4>
                <label><input type="checkbox" name="learning_enabled" id="editLearningEnabled"> Enable Learning</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <input type="number" name="learning_rate" id="editLearningRate" placeholder="Learning Rate (0.05)" step="0.01" min="0" max="1">
                    <select name="feedback_incorporation" id="editFeedbackIncorporation">
                        <option value="immediate">Immediate</option>
                        <option value="batch">Batch</option>
                        <option value="delayed">Delayed</option>
                    </select>
                    <select name="adaptation_strategy" id="editAdaptationStrategy">
                        <option value="progressive">Progressive</option>
                        <option value="conservative">Conservative</option>
                        <option value="aggressive">Aggressive</option>
                    </select>
                </div>
            </div>
            
            <!-- Personality Configuration -->
            <div class="form-section">
                <h4>Personality Configuration</h4>
                <textarea name="personality_traits" id="editPersonalityTraits" placeholder="Personality Traits (JSON array: [&quot;organized&quot;, &quot;decisive&quot;])" rows="2" style="width: 100%; margin-bottom: 10px;"></textarea>
                <textarea name="personality_quirks" id="editPersonalityQuirks" placeholder="Personality Quirks (JSON array: [&quot;always has backup plan&quot;])" rows="2" style="width: 100%; margin-bottom: 10px;"></textarea>
                <textarea name="communication_preferences" id="editCommunicationPreferences" placeholder="Communication Preferences (JSON array: [&quot;structured updates&quot;])" rows="2" style="width: 100%; margin-bottom: 10px;"></textarea>
            </div>
            
            <!-- Communication Style -->
            <div class="form-section">
                <h4>Communication Style</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <select name="communication_formality" id="editCommunicationFormality">
                        <option value="professional">Professional</option>
                        <option value="casual">Casual</option>
                        <option value="formal">Formal</option>
                    </select>
                    <select name="communication_verbosity" id="editCommunicationVerbosity">
                        <option value="concise">Concise</option>
                        <option value="detailed">Detailed</option>
                        <option value="verbose">Verbose</option>
                    </select>
                    <select name="communication_tone" id="editCommunicationTone">
                        <option value="confident">Confident</option>
                        <option value="friendly">Friendly</option>
                        <option value="authoritative">Authoritative</option>
                        <option value="supportive">Supportive</option>
                    </select>
                    <select name="communication_technical_level" id="editCommunicationTechnicalLevel">
                        <option value="intermediate">Intermediate</option>
                        <option value="beginner">Beginner</option>
                        <option value="advanced">Advanced</option>
                        <option value="expert">Expert</option>
                    </select>
                </div>
            </div>
            
            <!-- Advanced Configuration -->
            <div class="form-section">
                <h4>Advanced Configuration</h4>
                <textarea name="tools" id="editTools" placeholder="Tools (JSON array of tool names)" rows="2" style="width: 100%; margin-bottom: 10px;"></textarea>
                <textarea name="knowledge" id="editKnowledge" placeholder="Knowledge Sources" rows="2" style="width: 100%; margin-bottom: 10px;"></textarea>
                <textarea name="coworkers" id="editCoworkers" placeholder="Coworkers (JSON array of agent roles)" rows="2" style="width: 100%; margin-bottom: 10px;"></textarea>
            </div>
            
            <div style="margin-top: 15px;">
                <button type="submit" class="btn-success">Update Agent</button>
                <button type="button" onclick="closeEditModal()" class="btn-danger">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.form-section {
    border: 1px solid #ddd;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 5px;
    background: #f9f9f9;
}
.form-section h4 {
    margin: 0 0 10px 0;
    color: #333;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
}
</style>

<script>
function editAgent(agentId) {
    // Get agent data and populate form
    const agents = <?= json_encode($agents) ?>;
    const agent = agents.find(a => a.id == agentId);
    
    // Basic Info
    document.getElementById('editAgentId').value = agentId;
    document.getElementById('editName').value = agent.name || '';
    document.getElementById('editRole').value = agent.role || '';
    document.getElementById('editGoal').value = agent.goal || '';
    document.getElementById('editBackstory').value = agent.backstory || '';
    document.getElementById('editStatus').value = agent.status || 'active';
    document.getElementById('editLlmModel').value = agent.llm_model || 'local';
    
    // CrewAI Core Options
    document.getElementById('editVerbose').checked = agent.verbose == 1;
    document.getElementById('editAllowDelegation').checked = agent.allow_delegation == 1;
    document.getElementById('editAllowCodeExecution').checked = agent.allow_code_execution == 1;
    document.getElementById('editMemory').checked = agent.memory == 1;
    document.getElementById('editMaxIter').value = agent.max_iter || 25;
    document.getElementById('editMaxRpm').value = agent.max_rpm || '';
    document.getElementById('editMaxExecutionTime').value = agent.max_execution_time || '';
    document.getElementById('editMaxRetryLimit').value = agent.max_retry_limit || 2;
    
    // Learning Configuration
    document.getElementById('editLearningEnabled').checked = agent.learning_enabled == 1;
    document.getElementById('editLearningRate').value = agent.learning_rate || 0.05;
    document.getElementById('editFeedbackIncorporation').value = agent.feedback_incorporation || 'immediate';
    document.getElementById('editAdaptationStrategy').value = agent.adaptation_strategy || 'progressive';
    
    // Personality Configuration
    document.getElementById('editPersonalityTraits').value = agent.personality_traits || '';
    document.getElementById('editPersonalityQuirks').value = agent.personality_quirks || '';
    document.getElementById('editCommunicationPreferences').value = agent.communication_preferences || '';
    
    // Communication Style
    document.getElementById('editCommunicationFormality').value = agent.communication_formality || 'professional';
    document.getElementById('editCommunicationVerbosity').value = agent.communication_verbosity || 'concise';
    document.getElementById('editCommunicationTone').value = agent.communication_tone || 'confident';
    document.getElementById('editCommunicationTechnicalLevel').value = agent.communication_technical_level || 'intermediate';
    
    // Advanced Configuration
    document.getElementById('editTools').value = agent.tools || '';
    document.getElementById('editKnowledge').value = agent.knowledge || '';
    document.getElementById('editCoworkers').value = agent.coworkers || '';
    
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function chatWithAgent(agentId) {
    window.open('/admin/chat?agent=' + agentId, '_blank');
}

function viewTasks(agentId) {
    window.location.href = '/admin/tasks?agent=' + agentId;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>