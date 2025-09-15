<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin/login.php');
    exit;
}

// Block demo users from Claude chat
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'demo') {
    header('Location: /admin/dashboard.php?error=Demo users cannot access Claude chat');
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
        <div style="display: flex; gap: 10px; align-items: center;">
            <button onclick="togglePromptEditor()" class="btn-warning">✏️ Edit Prompt</button>
            <select id="claude-mode" onchange="updateMode()">
                <option value="chat">Chat Mode</option>
                <option value="autonomous">Autonomous Mode</option>
                <option value="hybrid">Hybrid Mode</option>
            </select>
            <a href="/admin/claude_settings.php" class="btn-secondary">⚙️ Settings</a>
            <button onclick="clearChat()" class="btn-warning">Clear Chat</button>
        </div>
    </div>
    
    <div id="prompt-editor" style="display: none; margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
        <p style="font-size: 12px; color: #666; margin-bottom: 10px;">📝 <strong>Note:</strong> Commands (@file, @create, @edit, etc.) are automatically added to Claude's prompt even if not included here.</p>
        <textarea id="system-prompt" rows="6" style="width: 100%; font-family: monospace; font-size: 12px; border: 1px solid #ddd; border-radius: 4px; padding: 10px;" placeholder="Loading system prompt..."></textarea>
        <div style="margin-top: 10px; display: flex; gap: 10px;">
            <button onclick="saveSystemPrompt()" class="btn-success">Save Prompt</button>
            <button onclick="resetSystemPrompt()" class="btn-danger">Reset to Default</button>
            <button onclick="togglePromptEditor()" class="btn-secondary">Cancel</button>
        </div>
    </div>
    
    <div id="chat-container" style="height: 400px; border: 1px solid #ddd; padding: 15px; overflow-y: auto; background: #f9f9f9; margin-bottom: 15px;">
        <div class="message system-message">
            <strong>System:</strong> Claude is ready. You can use commands like @file, @list, @agents, etc.
        </div>
    </div>
    
    <div style="display: flex; gap: 10px;">
        <textarea id="user-input" placeholder="Ask Claude about ZeroAI optimization, code review, or development help...\n\nExamples:\n- @file src/main.py (to share a file)\n- @list www/admin/ (to list directory contents)\n- @search config (to find files)\n- @agents (to see all agents)\n- Help me optimize my ZeroAI configuration" style="flex: 1; height: 100px; resize: vertical; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
        <div style="display: flex; flex-direction: column; gap: 5px;">
            <button onclick="sendMessage()" class="btn-success" style="height: 40px;">Send</button>
            <button onclick="insertCommand('@file ')" class="btn-secondary" style="font-size: 11px; padding: 4px 8px;">@file</button>
            <button onclick="insertCommand('@list ')" class="btn-secondary" style="font-size: 11px; padding: 4px 8px;">@list</button>
            <button onclick="insertCommand('@agents')" class="btn-secondary" style="font-size: 11px; padding: 4px 8px;">@agents</button>
        </div>
    </div>
</div>

<div class="card">
    <h3>📝 Scratch Pad</h3>
    <textarea id="scratch-pad" placeholder="Use this area for notes, ideas, or temporary text..." style="width: 100%; height: 150px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; resize: vertical;"></textarea>
    <div style="margin-top: 10px; display: flex; gap: 10px;">
        <button onclick="saveScratchPad()" class="btn-success">Save Notes</button>
        <button onclick="clearScratchPad()" class="btn-warning">Clear</button>
        <button onclick="insertToChat()" class="btn-secondary">Send to Chat</button>
    </div>
</div>

<div class="card">
    <h3>🚀 Quick Actions</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        <button onclick="quickCommand('@agents')" class="btn-primary" style="padding: 12px; border-radius: 6px;">🤖 List Agents</button>
        <button onclick="quickCommand('@crews')" class="btn-primary" style="padding: 12px; border-radius: 6px;">👥 Show Crews</button>
        <button onclick="quickCommand('@list /app/src')" class="btn-info" style="padding: 12px; border-radius: 6px;">📁 List Source Code</button>
        <button onclick="quickCommand('@file /app/config/settings.yaml')" class="btn-info" style="padding: 12px; border-radius: 6px;">⚙️ Show Config</button>
        <button onclick="quickCommand('Analyze my ZeroAI system and suggest optimizations')" class="btn-success" style="padding: 12px; border-radius: 6px;">🔍 System Analysis</button>
        <button onclick="quickCommand('Review my agent performance and suggest improvements')" class="btn-warning" style="padding: 12px; border-radius: 6px;">📊 Performance Review</button>
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

// System prompt editing functions
let currentSystemPrompt = '';

function togglePromptEditor() {
    const editor = document.getElementById('prompt-editor');
    if (editor.style.display === 'none') {
        editor.style.display = 'block';
        loadSystemPrompt();
    } else {
        editor.style.display = 'none';
    }
}

async function loadSystemPrompt() {
    try {
        const response = await fetch('/admin/get_system_prompt.php');
        const result = await response.json();
        if (result.success) {
            currentSystemPrompt = result.prompt;
            document.getElementById('system-prompt').value = result.prompt;
        }
    } catch (error) {
        console.error('Failed to load system prompt:', error);
    }
}

async function saveSystemPrompt() {
    const prompt = document.getElementById('system-prompt').value;
    try {
        const response = await fetch('/admin/save_system_prompt.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({prompt: prompt})
        });
        const result = await response.json();
        if (result.success) {
            addSystemMessage('✅ System prompt updated successfully');
            togglePromptEditor();
        } else {
            addSystemMessage('❌ Failed to save prompt: ' + result.error);
        }
    } catch (error) {
        addSystemMessage('❌ Error saving prompt: ' + error.message);
    }
}

function resetSystemPrompt() {
    if (confirm('Reset to default system prompt? This will overwrite your custom prompt.')) {
        fetch('/admin/reset_system_prompt.php', {method: 'POST'})
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    loadSystemPrompt();
                    addSystemMessage('✅ System prompt reset to default');
                }
            });
    }
}

// Scratch pad functions
async function saveScratchPad() {
    const content = document.getElementById('scratch-pad').value;
    try {
        const response = await fetch('/admin/claude_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'save_scratch_pad', content: content})
        });
        const result = await response.json();
        if (result.success) {
            addSystemMessage('✅ Notes saved to Claude database');
        } else {
            addSystemMessage('❌ Failed to save notes: ' + result.error);
        }
    } catch (error) {
        addSystemMessage('❌ Error saving notes: ' + error.message);
    }
}

async function clearScratchPad() {
    if (confirm('Clear scratch pad?')) {
        document.getElementById('scratch-pad').value = '';
        try {
            await fetch('/admin/claude_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'save_scratch_pad', content: ''})
            });
            addSystemMessage('✅ Scratch pad cleared');
        } catch (error) {
            addSystemMessage('❌ Error clearing notes');
        }
    }
}

function insertToChat() {
    const content = document.getElementById('scratch-pad').value.trim();
    if (content) {
        document.getElementById('user-input').value = content;
        document.getElementById('user-input').focus();
    }
}

// Load scratch pad on page load
window.addEventListener('load', async function() {
    try {
        const response = await fetch('/admin/claude_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_scratch_pad'})
        });
        const result = await response.json();
        if (result.success && result.content) {
            document.getElementById('scratch-pad').value = result.content;
        }
    } catch (error) {
        console.error('Failed to load scratch pad:', error);
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>