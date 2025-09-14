<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'Claude Chat - ZeroAI';
$currentPage = 'claude_chat';
include __DIR__ . '/includes/header.php';
?>

<h1>💬 Claude Direct Chat</h1>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3>Chat with Claude</h3>
        <div>
            <select id="claude-mode" onchange="updateMode()">
                <option value="chat">Chat Mode</option>
                <option value="autonomous">Autonomous Mode</option>
                <option value="hybrid">Hybrid Mode</option>
            </select>
            <a href="/admin/claude_settings.php" class="btn-secondary" style="margin-left: 10px;">⚙️ Settings</a>
            <button onclick="clearChat()" class="btn-warning" style="margin-left: 10px;">Clear Chat</button>
        </div>
    </div>
    
    <div id="chat-container" style="height: 400px; border: 1px solid #ddd; padding: 15px; overflow-y: auto; background: #f9f9f9; margin-bottom: 15px;">
        <div class="message system-message">
            <strong>System:</strong> Claude is ready. You can use commands like @file, @list, @agents, etc.
        </div>
    </div>
    
    <div style="display: flex; gap: 10px;">
        <textarea id="user-input" placeholder="Type your message to Claude..." style="flex: 1; height: 80px; resize: vertical;"></textarea>
        <div style="display: flex; flex-direction: column; gap: 5px;">
            <button onclick="sendMessage()" class="btn-success">Send</button>
            <button onclick="insertCommand('@file ')" class="btn-secondary" style="font-size: 12px;">@file</button>
            <button onclick="insertCommand('@list ')" class="btn-secondary" style="font-size: 12px;">@list</button>
            <button onclick="insertCommand('@agents')" class="btn-secondary" style="font-size: 12px;">@agents</button>
        </div>
    </div>
</div>

<div class="card">
    <h3>Quick Commands</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
        <button onclick="quickCommand('@agents')" class="btn-secondary">List Agents</button>
        <button onclick="quickCommand('@crews')" class="btn-secondary">Show Crews</button>
        <button onclick="quickCommand('@ps')" class="btn-secondary">Docker Status</button>
        <button onclick="quickCommand('@list /app/src')" class="btn-secondary">List Source</button>
        <button onclick="quickCommand('@file /app/config/settings.yaml')" class="btn-secondary">Show Config</button>
        <button onclick="quickCommand('@logs 1')" class="btn-secondary">Recent Logs</button>
    </div>
</div>

<style>
.message {
    margin-bottom: 15px;
    padding: 10px;
    border-radius: 8px;
}
.user-message {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
}
.claude-message {
    background: #f3e5f5;
    border-left: 4px solid #9c27b0;
}
.system-message {
    background: #fff3e0;
    border-left: 4px solid #ff9800;
    font-style: italic;
}
.error-message {
    background: #ffebee;
    border-left: 4px solid #f44336;
}
</style>

<script>
let chatHistory = [];
let currentMode = 'chat';

function updateMode() {
    currentMode = document.getElementById('claude-mode').value;
    addSystemMessage(`Mode changed to: ${currentMode}`);
}

function insertCommand(command) {
    const input = document.getElementById('user-input');
    input.value += command;
    input.focus();
}

function quickCommand(command) {
    document.getElementById('user-input').value = command;
    sendMessage();
}

function addMessage(type, content, sender = '') {
    const container = document.getElementById('chat-container');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}-message`;
    
    if (sender) {
        messageDiv.innerHTML = `<strong>${sender}:</strong> ${content}`;
    } else {
        messageDiv.innerHTML = content;
    }
    
    container.appendChild(messageDiv);
    container.scrollTop = container.scrollHeight;
}

function addSystemMessage(content) {
    addMessage('system', content, 'System');
}

function sendMessage() {
    const input = document.getElementById('user-input');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Add user message to chat
    addMessage('user', message, 'You');
    chatHistory.push({role: 'user', content: message});
    
    // Clear input
    input.value = '';
    
    // Show loading
    addSystemMessage('Claude is thinking...');
    
    // Send to Claude API
    fetch('/admin/claude_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            message: message,
            mode: currentMode,
            history: chatHistory
        })
    })
    .then(response => response.json())
    .then(data => {
        // Remove loading message
        const container = document.getElementById('chat-container');
        container.removeChild(container.lastChild);
        
        if (data.success) {
            addMessage('claude', data.response, 'Claude');
            chatHistory.push({role: 'assistant', content: data.response});
        } else {
            addMessage('error', data.error || 'Failed to get response from Claude', 'Error');
        }
    })
    .catch(error => {
        // Remove loading message
        const container = document.getElementById('chat-container');
        container.removeChild(container.lastChild);
        
        addMessage('error', 'Network error: ' + error.message, 'Error');
    });
}

function clearChat() {
    if (confirm('Clear chat history?')) {
        document.getElementById('chat-container').innerHTML = '<div class="message system-message"><strong>System:</strong> Claude is ready. You can use commands like @file, @list, @agents, etc.</div>';
        chatHistory = [];
    }
}

// Enter key to send
document.getElementById('user-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>