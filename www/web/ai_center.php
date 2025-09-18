<?php
$pageTitle = 'AI Community Center - ZeroAI CRM';
$currentPage = 'ai_center';
include __DIR__ . '/includes/header.php';

$companyId = $_GET['company'] ?? $_SESSION['company_id'] ?? 1;

// Create agents table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS agents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        role VARCHAR(255),
        goal TEXT,
        backstory TEXT,
        status VARCHAR(20) DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS company_agents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER,
        agent_id INTEGER,
        assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id),
        FOREIGN KEY (agent_id) REFERENCES agents(id)
    )");
    
    // Insert sample agents if table is empty
    $count = $pdo->query("SELECT COUNT(*) FROM agents")->fetchColumn();
    if ($count == 0) {
        $sampleAgents = [
            ['Dr. Sarah Chen', 'Research Analyst', 'Analyze market trends and provide data-driven insights', 'Expert researcher with 10+ years in market analysis'],
            ['Alex Thompson', 'Content Creator', 'Create engaging content for marketing campaigns', 'Creative writer specializing in digital marketing'],
            ['Maya Patel', 'Customer Success Manager', 'Ensure customer satisfaction and retention', 'Customer service expert with proven track record'],
            ['David Kim', 'Sales Assistant', 'Support sales team with lead qualification', 'Sales professional with expertise in B2B markets']
        ];
        
        foreach ($sampleAgents as $agent) {
            $stmt = $pdo->prepare("INSERT INTO agents (name, role, goal, backstory) VALUES (?, ?, ?, ?)");
            $stmt->execute($agent);
        }
    }
} catch (Exception $e) {}

// Get available agents
$agents = $pdo->query("SELECT * FROM agents WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Get company's assigned agents
$assignedAgents = $pdo->prepare("SELECT a.*, ca.assigned_at FROM agents a 
                                 JOIN company_agents ca ON a.id = ca.agent_id 
                                 WHERE ca.company_id = ? AND a.status = 'active'");
$assignedAgents->execute([$companyId]);
$assignedAgents = $assignedAgents->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Get company data
$companyData = ['name' => 'Your Company'];
try {
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($company) {
        $companyData = $company;
    }
} catch (Exception $e) {}
?>

    <!-- Header Section -->
    <div class="header-section">
        <div style="background: #007cba; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button id="sidebarToggle" style="background: none; border: none; color: white; font-size: 18px; cursor: pointer; display: none;">☰</button>
                <h1 style="margin: 0; font-size: 1.5rem;">🤖 AI Community Center</h1>
            </div>
            <?= $menuSystem->renderHeaderMenu() ?>
            <div class="profile-dropdown">
                <span style="cursor: pointer; padding: 8px 12px; border-radius: 4px; background: rgba(255,255,255,0.1);">
                    <?= htmlspecialchars($currentUser) ?> (<?= htmlspecialchars($userOrgId) ?>) ▼
                </span>
                <div class="profile-dropdown-content">
                    <?php if ($isAdmin): ?>
                        <a href="/admin/dashboard.php">⚙️ Admin Panel</a>
                    <?php endif; ?>
                    <a href="/web/logout.php">🚪 Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Section -->
    <div class="sidebar-section">
        <?= $menuSystem->renderSidebar($currentPage) ?>
    </div>

    <!-- Main Content Section -->
    <div class="main-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>AI Community Center</h2>
            <div>
                <span style="background: #007cba; color: white; padding: 5px 10px; border-radius: 4px;">
                    <?= htmlspecialchars($companyData['name'] ?? 'Company') ?>
                </span>
            </div>
        </div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Available AI Agents -->
    <div class="card">
        <h3>🌟 Available AI Agents</h3>
        <p>Meet our AI team members. Each agent specializes in different areas to help your business.</p>
        
        <div id="agents-grid" style="display: grid; gap: 15px; margin-top: 15px;">
            <?php foreach ($agents as $agent): ?>
                <?php 
                $isAssigned = in_array($agent['id'], array_column($assignedAgents, 'id'));
                $cardStyle = $isAssigned ? 'border: 2px solid #28a745; background: #f8fff8;' : 'border: 1px solid #ddd;';
                ?>
                <div class="agent-card" style="<?= $cardStyle ?> padding: 15px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 5px 0; color: #007cba;">
                                <?= htmlspecialchars($agent['name']) ?>
                                <?php if ($isAssigned): ?>
                                    <span style="color: #28a745; font-size: 0.8em;">✓ Assigned</span>
                                <?php endif; ?>
                            </h4>
                            <p style="margin: 0; font-weight: bold; color: #666;"><?= htmlspecialchars($agent['role']) ?></p>
                            <p style="margin: 5px 0; font-size: 14px;"><?= htmlspecialchars($agent['goal']) ?></p>
                            <p style="margin: 0; font-size: 12px; color: #888;">
                                <?= htmlspecialchars(substr($agent['backstory'], 0, 80)) ?>...
                            </p>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 5px; margin-left: 10px;">
                            <?php if ($isAssigned): ?>
                                <button onclick="chatWithAgent(<?= $agent['id'] ?>)" class="btn btn-success btn-sm">💬 Chat</button>
                                <button onclick="removeAgent(<?= $agent['id'] ?>)" class="btn btn-danger btn-sm">Remove</button>
                            <?php else: ?>
                                <button onclick="assignAgent(<?= $agent['id'] ?>)" class="btn btn-primary btn-sm">+ Assign</button>
                                <button onclick="previewAgent(<?= $agent['id'] ?>)" class="btn btn-secondary btn-sm">Preview</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- My AI Team -->
    <div class="card">
        <h3>👥 My AI Team (<?= count($assignedAgents) ?>)</h3>
        
        <?php if (empty($assignedAgents)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <h4>No AI agents assigned yet</h4>
                <p>Select agents from the available list to build your AI team.</p>
            </div>
        <?php else: ?>
            <div style="margin-top: 15px;">
                <?php foreach ($assignedAgents as $agent): ?>
                    <div style="border: 1px solid #28a745; padding: 10px; margin: 10px 0; border-radius: 6px; background: #f8fff8;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?= htmlspecialchars($agent['name']) ?></strong>
                                <span style="color: #666; margin-left: 10px;"><?= htmlspecialchars($agent['role']) ?></span>
                            </div>
                            <div>
                                <button onclick="chatWithAgent(<?= $agent['id'] ?>)" class="btn btn-success btn-sm">💬 Chat</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-radius: 6px; border-left: 4px solid #007cba;">
            <h4 style="margin: 0 0 10px 0;">🚀 Upgrade to AI Pro</h4>
            <p style="margin: 0 0 10px 0; font-size: 14px;">
                Get unlimited access to all AI agents, priority support, and advanced features.
            </p>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: bold; color: #007cba;">$99/month per company</span>
                <button onclick="upgradeToPro()" class="btn btn-primary">Upgrade Now</button>
            </div>
        </div>
    </div>
</div>

<!-- Chat Modal -->
<div id="chatModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; width: 600px; max-width: 95%; height: 500px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 id="chatAgentName">Chat with Agent</h3>
            <button onclick="closeChatModal()" style="background: none; border: none; font-size: 20px; cursor: pointer;">×</button>
        </div>
        
        <div id="chatMessages" style="height: 350px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #f9f9f9;"></div>
        
        <div style="display: flex; gap: 10px;">
            <input type="text" id="chatInput" placeholder="Ask your AI agent anything..." style="flex: 1; padding: 8px;">
            <button onclick="sendMessage()" class="btn btn-primary">Send</button>
        </div>
    </div>
</div>

<script>
let currentAgentId = null;

function assignAgent(agentId) {
    fetch('/api/crm/assign-agent', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            company_id: <?= $companyId ?>,
            agent_id: agentId
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to assign agent: ' + data.error);
        }
    });
}

function removeAgent(agentId) {
    if (confirm('Remove this agent from your team?')) {
        fetch('/api/crm/remove-agent', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                company_id: <?= $companyId ?>,
                agent_id: agentId
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to remove agent: ' + data.error);
            }
        });
    }
}

function chatWithAgent(agentId) {
    currentAgentId = agentId;
    
    // Get agent name
    fetch(`/api/admin/agents?id=${agentId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.agent) {
                document.getElementById('chatAgentName').textContent = `Chat with ${data.agent.name}`;
                document.getElementById('chatMessages').innerHTML = `
                    <div style="color: #666; font-style: italic; margin-bottom: 10px;">
                        Connected to ${data.agent.name} - ${data.agent.role}
                    </div>
                `;
                document.getElementById('chatModal').style.display = 'block';
            }
        });
}

function previewAgent(agentId) {
    chatWithAgent(agentId);
}

function closeChatModal() {
    document.getElementById('chatModal').style.display = 'none';
    currentAgentId = null;
}

function sendMessage() {
    const input = document.getElementById('chatInput');
    const messages = document.getElementById('chatMessages');
    const message = input.value.trim();
    
    if (!message || !currentAgentId) return;
    
    // Add user message
    messages.innerHTML += `<div style="margin: 5px 0; text-align: right;"><strong>You:</strong> ${message}</div>`;
    input.value = '';
    
    // Add loading
    messages.innerHTML += `<div id="loading" style="margin: 5px 0; color: #666;"><strong>AI:</strong> Thinking...</div>`;
    messages.scrollTop = messages.scrollHeight;
    
    // Send to AI
    fetch('/api/crm/ai-chat', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            company_id: <?= $companyId ?>,
            agent_id: currentAgentId,
            query: message
        })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('loading').remove();
        
        if (data.success) {
            messages.innerHTML += `<div style="margin: 5px 0;"><strong>AI:</strong> ${data.response}</div>`;
        } else {
            messages.innerHTML += `<div style="margin: 5px 0; color: red;"><strong>Error:</strong> ${data.error}</div>`;
        }
        messages.scrollTop = messages.scrollHeight;
    });
}

function upgradeToPro() {
    alert('Upgrade feature coming soon! Contact support for early access.');
}

document.getElementById('chatInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        sendMessage();
    }
});

// Close modal when clicking outside
document.getElementById('chatModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeChatModal();
    }
});
</script>

    </div>

    <!-- Footer Section -->
    <div class="footer-section">
        <div style="padding: 15px 20px; text-align: center; color: #666;">
            © 2024 ZeroAI CRM. All rights reserved.
        </div>
    </div>
</div>

<script>
// Mobile sidebar toggle
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    const container = document.getElementById('layoutContainer');
    const sidebar = document.querySelector('.sidebar-section');
    
    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('mobile-open');
    } else {
        container.classList.toggle('sidebar-closed');
    }
});

// Show mobile toggle on small screens
function updateSidebarToggle() {
    const toggle = document.getElementById('sidebarToggle');
    if (toggle) {
        toggle.style.display = window.innerWidth <= 768 ? 'block' : 'none';
    }
}

window.addEventListener('resize', updateSidebarToggle);
updateSidebarToggle();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

