<?php 
$pageTitle = 'Agent Management - ZeroAI';
$currentPage = 'agents';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../api/agent_importer.php';

$importer = new AgentImporter();

// Handle actions
if ($_POST['action'] ?? '' === 'import_agents') {
    $imported = $importer->importExistingAgents();
    $message = count($imported) . ' agents imported successfully';
} elseif ($_POST['action'] ?? '' === 'update_agent') {
    $updates = [
        'role' => $_POST['role'],
        'goal' => $_POST['goal'],
        'backstory' => $_POST['backstory']
    ];
    $importer->updateAgentRealtime($_POST['agent_id'], $updates);
    $message = 'Agent updated successfully';
}

$agents = $importer->getActiveAgents();
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
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; width: 500px; max-width: 90%;">
        <h3>Edit Agent</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update_agent">
            <input type="hidden" name="agent_id" id="editAgentId">
            <input type="text" name="role" id="editRole" placeholder="Agent Role" required>
            <textarea name="goal" id="editGoal" placeholder="Agent Goal" rows="2" required></textarea>
            <textarea name="backstory" id="editBackstory" placeholder="Agent Backstory" rows="3" required></textarea>
            <div style="margin-top: 15px;">
                <button type="submit" class="btn-success">Update Agent</button>
                <button type="button" onclick="closeEditModal()" class="btn-danger">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function editAgent(agentId) {
    // Get agent data and populate form
    const agents = <?= json_encode($agents) ?>;
    const agent = agents.find(a => a.id == agentId);
    
    document.getElementById('editAgentId').value = agentId;
    document.getElementById('editRole').value = agent.role;
    document.getElementById('editGoal').value = agent.goal;
    document.getElementById('editBackstory').value = agent.backstory;
    
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

        </div>
    </div>
<?php include __DIR__ . '/includes/footer.php'; ?>