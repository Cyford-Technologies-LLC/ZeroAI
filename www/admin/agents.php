<?php 
session_start();
$pageTitle = 'Agent Management - ZeroAI';
$currentPage = 'agents';

require_once __DIR__ . '/includes/autoload.php';

use ZeroAI\Core\DatabaseManager;

$db = DatabaseManager::getInstance();
$agents = $db->select('agents') ?: [];

include __DIR__ . '/includes/header.php';

$message = $_SESSION['import_message'] ?? '';
$error = $_SESSION['import_error'] ?? '';

// Clear session messages
unset($_SESSION['import_message'], $_SESSION['import_error']);

// Initialize agents table with proper structure
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

// Initialize companies table for CRM
$db->query("CREATE TABLE IF NOT EXISTS companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    status TEXT DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'create_agent') {
        $data = [
            'name' => $_POST['name'] ?? '',
            'role' => $_POST['role'] ?? '',
            'goal' => $_POST['goal'] ?? '',
            'backstory' => $_POST['backstory'] ?? ''
        ];
        
        if ($data['name'] && $data['role'] && $data['goal']) {
            if ($db->insert('agents', $data)) {
                $message = "Agent '{$data['name']}' created successfully!";
            } else {
                $error = "Failed to create agent.";
            }
        } else {
            $error = "Name, role, and goal are required.";
        }
    } elseif (($_POST['action'] ?? '') === 'update_agent') {
        $agentId = (int)($_POST['agent_id'] ?? 0);
        $updates = array_filter([
            'name' => $_POST['name'] ?? '',
            'role' => $_POST['role'] ?? '',
            'goal' => $_POST['goal'] ?? '',
            'backstory' => $_POST['backstory'] ?? '',
            'status' => $_POST['status'] ?? 'active',
            'llm_model' => $_POST['llm_model'] ?? 'local',
            'verbose' => isset($_POST['verbose']) ? 1 : 0,
            'allow_delegation' => isset($_POST['allow_delegation']) ? 1 : 0,
            'allow_code_execution' => isset($_POST['allow_code_execution']) ? 1 : 0,
            'memory' => isset($_POST['memory']) ? 1 : 0,
            'max_iter' => $_POST['max_iter'] ? (int)$_POST['max_iter'] : null,
            'max_rpm' => $_POST['max_rpm'] ? (int)$_POST['max_rpm'] : null,
            'max_execution_time' => $_POST['max_execution_time'] ? (int)$_POST['max_execution_time'] : null,
            'max_retry_limit' => $_POST['max_retry_limit'] ? (int)$_POST['max_retry_limit'] : 2,
            'learning_enabled' => isset($_POST['learning_enabled']) ? 1 : 0,
            'learning_rate' => $_POST['learning_rate'] ? (float)$_POST['learning_rate'] : 0.05,
            'feedback_incorporation' => $_POST['feedback_incorporation'] ?? 'immediate'
        ]);
        
        if ($db->update('agents', $updates, ['id' => $agentId])) {
            $message = 'Agent updated successfully!';
        } else {
            $error = 'Failed to update agent.';
        }
    } elseif (($_POST['action'] ?? '') === 'delete_agent') {
        $agentId = (int)($_POST['agent_id'] ?? 0);
        if ($db->delete('agents', ['id' => $agentId])) {
            $message = 'Agent deleted successfully!';
        } else {
            $error = 'Failed to delete agent.';
        }
    }
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1>🤖 Agent Management</h1>
    <div>
        <a href="/web/ai_center.php" class="btn btn-primary">AI Community Center</a>
        <a href="import_agents.php" class="btn btn-warning">Import Default Agents</a>
        <button onclick="showCreateForm()" class="btn btn-success">+ Create Agent</button>
    </div>
</div>

<?php if ($message): ?>
    <div style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724; margin-bottom: 15px;">
        ✅ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24; margin-bottom: 15px;">
        ❌ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="stats-grid" style="margin-bottom: 20px;">
    <div class="stat-card">
        <div class="stat-value"><?= count($agents) ?></div>
        <div class="stat-label">Total Agents</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count(array_filter($agents, fn($a) => $a['status'] === 'active')) ?></div>
        <div class="stat-label">Active Agents</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count(array_unique(array_column($agents, 'role'))) ?></div>
        <div class="stat-label">Unique Roles</div>
    </div>
</div>
    
<div id="createForm" class="card" style="display: none;">
    <h3>Create New Agent</h3>
    <form method="POST">
        <input type="hidden" name="action" value="create_agent">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <input type="text" name="name" placeholder="Agent Name" required class="form-input">
            <input type="text" name="role" placeholder="Agent Role" required class="form-input">
        </div>
        <textarea name="goal" placeholder="Agent Goal" rows="2" required class="form-input" style="margin-bottom: 15px;"></textarea>
        <textarea name="backstory" placeholder="Agent Backstory" rows="3" class="form-input" style="margin-bottom: 15px;"></textarea>
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-success">Create Agent</button>
            <button type="button" onclick="hideCreateForm()" class="btn btn-secondary">Cancel</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>🤖 AI Agents (<?= count($agents) ?>)</h3>
    
    <?php if (empty($agents)): ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <h4>No agents found</h4>
            <p>Create your first AI agent to get started.</p>
            <button onclick="showCreateForm()" class="btn btn-primary">Create First Agent</button>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 15px;">
            <?php foreach ($agents as $agent): ?>
                <div class="agent-card" style="border: 1px solid #ddd; padding: 20px; border-radius: 12px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); <?= $agent['is_core'] ? 'border-left: 4px solid #007cba;' : '' ?>">
                    <div style="margin-bottom: 15px;">
                        <h4 style="margin: 0 0 5px 0; color: #007cba; display: flex; align-items: center; gap: 8px;">
                            🤖 <?= htmlspecialchars($agent['name']) ?>
                            <?php if ($agent['is_core']): ?>
                                <span style="background: #007cba; color: white; padding: 2px 6px; border-radius: 12px; font-size: 10px;">CORE</span>
                            <?php endif; ?>
                        </h4>
                        <p style="margin: 0; font-weight: 600; color: #666; font-size: 14px;"><?= htmlspecialchars($agent['role']) ?></p>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <p style="margin: 0 0 8px 0; font-size: 13px; line-height: 1.4;">
                            <strong>Goal:</strong> <?= htmlspecialchars(substr($agent['goal'], 0, 80)) ?><?= strlen($agent['goal']) > 80 ? '...' : '' ?>
                        </p>
                        <p style="margin: 0; font-size: 12px; color: #888; line-height: 1.3;">
                            <?= htmlspecialchars(substr($agent['backstory'], 0, 100)) ?><?= strlen($agent['backstory']) > 100 ? '...' : '' ?>
                        </p>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span style="background: <?= $agent['status'] === 'active' ? '#28a745' : '#6c757d' ?>; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                            <?= strtoupper($agent['status']) ?>
                        </span>
                        <?php if (!empty($agent['tools'])): ?>
                        <span style="font-size: 11px; color: #007cba;">
                            🛠️ <?= count(json_decode($agent['tools'], true) ?: []) ?> tools
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                        <button onclick="editAgent(<?= $agent['id'] ?>)" class="btn btn-warning btn-sm">✏️ Edit</button>
                        <button onclick="chatWithAgent(<?= $agent['id'] ?>)" class="btn btn-success btn-sm">💬 Chat</button>
                        <button onclick="deleteAgent(<?= $agent['id'] ?>)" class="btn btn-danger btn-sm">🗑️ Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 12px; width: 600px; max-width: 95%; max-height: 90%; overflow-y: auto;">
        <h3 style="margin: 0 0 20px 0; color: #007cba;">Edit Agent</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update_agent">
            <input type="hidden" name="agent_id" id="editAgentId">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <input type="text" name="name" id="editName" placeholder="Agent Name" required style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                <input type="text" name="role" id="editRole" placeholder="Agent Role" required style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
            
            <textarea name="goal" id="editGoal" placeholder="Agent Goal" rows="2" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 15px; box-sizing: border-box;"></textarea>
            <textarea name="backstory" id="editBackstory" placeholder="Agent Backstory" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 15px; box-sizing: border-box;"></textarea>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <select name="status" id="editStatus" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select name="llm_model" id="editLlmModel" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    <option value="local">Local</option>
                    <option value="openai">OpenAI</option>
                    <option value="claude">Claude</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="submit" style="flex: 1; padding: 12px; background: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Update Agent</button>
                <button type="button" onclick="closeEditModal()" style="flex: 1; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showCreateForm() {
    document.getElementById('createForm').style.display = 'block';
    document.getElementById('createForm').scrollIntoView({behavior: 'smooth'});
}

function hideCreateForm() {
    document.getElementById('createForm').style.display = 'none';
}

function editAgent(agentId) {
    const agents = <?= json_encode($agents) ?>;
    const agent = agents.find(a => a.id == agentId);
    
    if (agent) {
        document.getElementById('editAgentId').value = agent.id;
        document.getElementById('editName').value = agent.name;
        document.getElementById('editRole').value = agent.role;
        document.getElementById('editGoal').value = agent.goal;
        document.getElementById('editBackstory').value = agent.backstory;
        document.getElementById('editStatus').value = agent.status;
        document.getElementById('editLlmModel').value = agent.llm_model || 'local';
        
        document.getElementById('editModal').style.display = 'block';
    }
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function chatWithAgent(agentId) {
    window.location.href = `/admin/agent_chat.php?id=${agentId}`;
}

function deleteAgent(agentId) {
    if (confirm('Are you sure you want to delete this agent?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_agent">
            <input type="hidden" name="agent_id" value="${agentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

<style>
.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-primary { background: #007cba; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-warning { background: #ffc107; color: #212529; }
.btn-danger { background: #dc3545; color: white; }
.btn-secondary { background: #6c757d; color: white; }

.btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.form-input {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.form-input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
}

.agent-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.agent-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>