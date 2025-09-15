<?php
include __DIR__ . '/../admin/includes/header.php';

$pageTitle = 'Company AI: ' . $companyData['name'];

?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1>🤖 AI Assistant for <?= htmlspecialchars($companyData['name']) ?></h1>
    <?php if (!$aiConfig): ?>
        <button onclick="createAI()" class="btn btn-primary">Create AI Assistant</button>
    <?php endif; ?>
</div>

<?php if ($aiConfig): ?>
    <div class="card">
        <h3>💬 Chat with <?= htmlspecialchars($aiConfig['ai_name']) ?></h3>
        <div id="chat-messages" style="height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin: 10px 0; background: #f9f9f9;"></div>
        <div style="display: flex; gap: 10px;">
            <input type="text" id="chat-input" placeholder="Ask about projects, tasks, or company operations..." style="flex: 1; padding: 8px;">
            <button onclick="sendMessage()" class="btn btn-primary">Send</button>
        </div>
    </div>

    <div class="card">
        <h3>🔧 AI Configuration</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <strong>Name:</strong> <?= htmlspecialchars($aiConfig['ai_name']) ?><br>
                <strong>Model:</strong> <?= htmlspecialchars($aiConfig['model_preference']) ?><br>
                <strong>Created:</strong> <?= $aiConfig['created_at'] ?>
            </div>
            <div>
                <strong>Capabilities:</strong><br>
                <?php 
                $capabilities = json_decode($aiConfig['capabilities'], true) ?? [];
                foreach ($capabilities as $cap): ?>
                    <span style="background: #007cba; color: white; padding: 2px 6px; border-radius: 3px; margin: 2px; display: inline-block;">
                        <?= ucfirst(str_replace('_', ' ', $cap)) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <h3>🚀 Create AI Assistant</h3>
        <p>Create a custom AI assistant for <?= htmlspecialchars($companyData['name']) ?> that understands your projects, tasks, and team.</p>
        
        <form id="ai-form">
            <label>Assistant Name:</label>
            <input type="text" id="ai-name" value="<?= htmlspecialchars($companyData['name']) ?> Assistant" required>
            
            <label>Model Preference:</label>
            <select id="ai-model">
                <option value="llama3.2:1b">Llama 3.2 1B (Fast)</option>
                <option value="llama3.1:8b">Llama 3.1 8B (Balanced)</option>
                <option value="claude-sonnet">Claude Sonnet (Advanced)</option>
            </select>
            
            <label>Capabilities:</label>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin: 10px 0;">
                <label><input type="checkbox" value="project_management" checked> Project Management</label>
                <label><input type="checkbox" value="task_automation"> Task Automation</label>
                <label><input type="checkbox" value="team_coordination" checked> Team Coordination</label>
                <label><input type="checkbox" value="bug_tracking"> Bug Tracking</label>
                <label><input type="checkbox" value="reporting"> Reporting & Analytics</label>
                <label><input type="checkbox" value="client_communication"> Client Communication</label>
            </div>
            
            <button type="submit" class="btn btn-success">Create AI Assistant</button>
        </form>
    </div>
<?php endif; ?>

<script>
function createAI() {
    document.getElementById('ai-form').scrollIntoView();
}

document.getElementById('ai-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const capabilities = Array.from(document.querySelectorAll('input[type="checkbox"]:checked'))
        .map(cb => cb.value);
    
    const config = {
        company_id: <?= $companyId ?>,
        config: {
            name: document.getElementById('ai-name').value,
            model: document.getElementById('ai-model').value,
            capabilities: capabilities
        }
    };
    
    fetch('/api/crm/company-ai', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(config)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to create AI: ' + data.error);
        }
    });
});

function sendMessage() {
    const input = document.getElementById('chat-input');
    const messages = document.getElementById('chat-messages');
    const query = input.value.trim();
    
    if (!query) return;
    
    // Add user message
    messages.innerHTML += `<div style="margin: 5px 0; text-align: right;"><strong>You:</strong> ${query}</div>`;
    input.value = '';
    
    // Add loading message
    messages.innerHTML += `<div id="loading" style="margin: 5px 0; color: #666;"><strong>AI:</strong> Thinking...</div>`;
    messages.scrollTop = messages.scrollHeight;
    
    fetch('/api/crm/ai-chat', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            company_id: <?= $companyId ?>,
            query: query
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

document.getElementById('chat-input')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        sendMessage();
    }
});
</script>

<?php include __DIR__ . '/../admin/includes/footer.php'; ?>

