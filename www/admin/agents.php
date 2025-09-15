<?php 
session_start();
$pageTitle = 'Agent Management - ZeroAI';
$currentPage = 'agents';

require_once __DIR__ . '/includes/autoload.php';

use ZeroAI\Core\DatabaseManager;

$db = DatabaseManager::getInstance();

// Fallback to direct DB operations if Agent class not found
try {
    $agent = new \ZeroAI\Core\Agent();
    $agents = $agent->getAll();
} catch (Exception $e) {
    $agents = $db->select('agents') ?: [];
}

include __DIR__ . '/includes/header.php';

$message = '';
$error = '';

// Initialize agents table
$db->query("CREATE TABLE IF NOT EXISTS agents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    role TEXT NOT NULL,
    goal TEXT,
    backstory TEXT,
    status TEXT DEFAULT 'active',
    is_core BOOLEAN DEFAULT 0,
    llm_model TEXT DEFAULT 'local',
    verbose BOOLEAN DEFAULT 0,
    allow_delegation BOOLEAN DEFAULT 1,
    allow_code_execution BOOLEAN DEFAULT 0,
    memory BOOLEAN DEFAULT 0,
    max_iter INTEGER DEFAULT 25,
    max_rpm INTEGER,
    max_execution_time INTEGER,
    max_retry_limit INTEGER DEFAULT 2,
    learning_enabled BOOLEAN DEFAULT 0,
    learning_rate REAL DEFAULT 0.05,
    feedback_incorporation TEXT DEFAULT 'immediate',
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
            if ($agent->create($data)) {
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
        
        if ($agent->update($agentId, $updates)) {
            $message = 'Agent updated successfully!';
        } else {
            $error = 'Failed to update agent.';
        }
    } elseif (($_POST['action'] ?? '') === 'delete_agent') {
        $agentId = (int)($_POST['agent_id'] ?? 0);
        if ($agent->delete($agentId)) {
            $message = 'Agent deleted successfully!';
        } else {
            $error = 'Failed to delete agent.';
        }
    }
}

$agents = $agent->getAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1>🤖 Agent Management</h1>
    <div>
        <a href="/web/ai_center.php" class="btn btn-primary">AI Community Center</a>
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
                        <span style="font-size: 11px; color: #999;">
                            Model: <?= htmlspecialchars($agent['llm_model'] ?? 'local') ?>
                        </span>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                        <button onclick="editAgent(<?= $agent['id'] ?>)" class="btn btn-warning btn-sm" style="font-size: 11px; padding: 6px 8px;">✏️ Edit</button>
                        <button onclick="chatWithAgent(<?= $agent['id'] ?>)" class="btn btn-success btn-sm" style="font-size: 11px; padding: 6px 8px;">💬 Chat</button>
                        <button onclick="deleteAgent(<?= $agent['id'] ?>)" class="btn btn-danger btn-sm" style="font-size: 11px; padding: 6px 8px;">🗑️ Delete</button>
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
    fetch(`/api/admin/agents?id=${agentId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.agent) {
                const agent = data.agent;
                document.getElementById('editAgentId').value = agent.id;
                document.getElementById('editName').value = agent.name;
                document.getElementById('editRole').value = agent.role;
                document.getElementById('editGoal').value = agent.goal;
                document.getElementById('editBackstory').value = agent.backstory;
                document.getElementById('editStatus').value = agent.status;
                document.getElementById('editLlmModel').value = agent.llm_model || 'local';
                
                document.getElementById('editModal').style.display = 'block';
            }
        })
        .catch(e => {
            alert('Failed to load agent data');
        });
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
                        <button onclick="editAgent(<?= $agent['id'] ?>)" class="btn btn-warning btn-sm" style="font-size: 11px; padding: 6px 8px;">✏️ Edit</button>
                        <button onclick="chatWithAgent(<?= $agent['id'] ?>)" class="btn btn-success btn-sm" style="font-size: 11px; padding: 6px 8px;">💬 Chat</button>
                        <button onclick="deleteAgent(<?= $agent['id'] ?>)" class="btn btn-danger btn-sm" style="font-size: 11px; padding: 6px 8px;">🗑️ Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
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
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">Update Agent</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
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
    fetch(`/api/admin/agents?id=${agentId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.agent) {
                const agent = data.agent;
                document.getElementById('editAgentId').value = agent.id;
                document.getElementById('editName').value = agent.name;
                document.getElementById('editRole').value = agent.role;
                document.getElementById('editGoal').value = agent.goal;
                document.getElementById('editBackstory').value = agent.backstory;
                document.getElementById('editStatus').value = agent.status;
                document.getElementById('editLlmModel').value = agent.llm_model || 'local';
                document.getElementById('editVerbose').checked = agent.verbose;
                document.getElementById('editAllowDelegation').checked = agent.allow_delegation;
                document.getElementById('editAllowCodeExecution').checked = agent.allow_code_execution;
                document.getElementById('editMemory').checked = agent.memory;
                document.getElementById('editMaxIter').value = agent.max_iter || '';
                document.getElementById('editMaxRpm').value = agent.max_rpm || '';
                document.getElementById('editMaxExecutionTime').value = agent.max_execution_time || '';
                document.getElementById('editMaxRetryLimit').value = agent.max_retry_limit || 2;
                document.getElementById('editLearningEnabled').checked = agent.learning_enabled;
                document.getElementById('editLearningRate').value = agent.learning_rate || 0.05;
                document.getElementById('editFeedbackIncorporation').value = agent.feedback_incorporation || 'immediate';
                
                document.getElementById('editModal').style.display = 'block';
            }
        });
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

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function chatWithAgent(agentId) {
    window.location.href = `/admin/agent_chat.php?id=${agentId}`;
}

function viewTasks(agentId) {
    window.location.href = `/admin/agent_tasks.php?id=${agentId}`;
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>lect>
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