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

<div class="claude-header">
    <h1>💬 Claude AI Assistant</h1>
    <p class="subtitle">Your intelligent development companion</p>
</div>

<div class="claude-controls">
    <div class="control-group">
        <button onclick="togglePromptEditor()" class="btn-modern btn-edit">✏️ System Prompt</button>
        <select id="claude-mode" onchange="updateMode()" class="select-modern">
            <option value="chat">💬 Chat Mode</option>
            <option value="autonomous">🤖 Autonomous</option>
            <option value="hybrid">⚡ Hybrid</option>
        </select>
    </div>
    <div class="control-group">
        <a href="/admin/claude_settings.php" class="btn-modern btn-settings">⚙️ Settings</a>
        <button onclick="clearChat()" class="btn-modern btn-clear">🗑️ Clear</button>
    </div>
</div>

<div class="claude-main">
    <div id="prompt-editor" class="prompt-editor">
        <div class="prompt-note">
            📝 <strong>System Prompt Editor</strong> - Commands (@file, @create, @edit, etc.) are automatically available
        </div>
        <textarea id="system-prompt" class="prompt-textarea" placeholder="Loading system prompt..."></textarea>
        <div class="prompt-actions">
            <button onclick="saveSystemPrompt()" class="btn-modern btn-save">✅ Save</button>
            <button onclick="resetSystemPrompt()" class="btn-modern btn-reset">🔄 Reset</button>
            <button onclick="togglePromptEditor()" class="btn-modern btn-cancel">❌ Cancel</button>
        </div>
    </div>
    
    <div class="chat-section">
        <div id="chat-container" class="chat-container">
            <div class="message system-message">
                <div class="message-header">🤖 System</div>
                <div class="message-content">Claude is ready! Use @commands to interact with your ZeroAI system.</div>
            </div>
        </div>
        
        <div class="input-section">
            <div class="input-container">
                <textarea id="user-input" class="chat-input" placeholder="Ask Claude about your ZeroAI system...\n\n• @file path/to/file.py\n• @list directory/\n• @agents\n• Help optimize my configuration"></textarea>
                <div class="input-controls">
                    <button onclick="sendMessage()" class="btn-send">🚀 Send</button>
                    <div class="quick-commands">
                        <button onclick="insertCommand('@file ')" class="cmd-btn">@file</button>
                        <button onclick="insertCommand('@list ')" class="cmd-btn">@list</button>
                        <button onclick="insertCommand('@agents')" class="cmd-btn">@agents</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="scratch-section">
    <div class="section-header">
        <h3>📝 Scratch Pad</h3>
        <div class="scratch-controls">
            <button onclick="saveScratchPad()" class="btn-mini btn-save">💾 Save</button>
            <button onclick="insertToChat()" class="btn-mini btn-send">➡️ To Chat</button>
            <button onclick="clearScratchPad()" class="btn-mini btn-clear">🗑️ Clear</button>
        </div>
    </div>
    <textarea id="scratch-pad" class="scratch-textarea" placeholder="Quick notes, code snippets, ideas..."></textarea>
</div>

<div class="actions-grid">
    <div class="action-category">
        <h4>🤖 System Commands</h4>
        <div class="action-buttons">
            <button onclick="quickCommand('@agents')" class="action-btn primary">🤖 List Agents</button>
            <button onclick="quickCommand('@crews')" class="action-btn primary">👥 Show Crews</button>
            <button onclick="quickCommand('@list /app/src')" class="action-btn info">📁 Browse Code</button>
        </div>
    </div>
    
    <div class="action-category">
        <h4>🔍 Analysis & Optimization</h4>
        <div class="action-buttons">
            <button onclick="quickCommand('Analyze my ZeroAI system and suggest optimizations')" class="action-btn success">🔍 System Analysis</button>
            <button onclick="quickCommand('Review my agent performance and suggest improvements')" class="action-btn warning">📊 Performance Review</button>
            <button onclick="quickCommand('@file /app/config/settings.yaml')" class="action-btn info">⚙️ Config Review</button>
        </div>
    </div>
</div>

<style>
/* Modern Claude Interface */
.claude-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.claude-header h1 {
    margin: 0 0 8px 0;
    font-size: 2.2em;
    font-weight: 600;
}
.subtitle {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1em;
}

.claude-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 1px solid #e9ecef;
}
.control-group {
    display: flex;
    gap: 12px;
    align-items: center;
}

.btn-modern {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}
.btn-edit { background: #ffc107; color: #000; }
.btn-edit:hover { background: #e0a800; }
.btn-settings { background: #6c757d; color: white; }
.btn-settings:hover { background: #545b62; }
.btn-clear { background: #dc3545; color: white; }
.btn-clear:hover { background: #c82333; }
.btn-save { background: #28a745; color: white; }
.btn-save:hover { background: #218838; }
.btn-reset { background: #fd7e14; color: white; }
.btn-reset:hover { background: #e8650e; }
.btn-cancel { background: #6c757d; color: white; }
.btn-cancel:hover { background: #545b62; }

.select-modern {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    background: white;
    font-size: 14px;
}

.claude-main {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 25px;
}

.prompt-editor {
    display: none;
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}
.prompt-note {
    color: #6c757d;
    font-size: 13px;
    margin-bottom: 12px;
}
.prompt-textarea {
    width: 100%;
    height: 120px;
    padding: 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-family: 'Consolas', monospace;
    font-size: 13px;
    resize: vertical;
}
.prompt-actions {
    margin-top: 12px;
    display: flex;
    gap: 10px;
}

.chat-section {
    padding: 20px;
}
.chat-container {
    height: 400px;
    overflow-y: auto;
    padding: 15px;
    background: #fafbfc;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    margin-bottom: 20px;
}

.message {
    margin-bottom: 16px;
    padding: 12px 16px;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.message-header {
    font-weight: 600;
    margin-bottom: 6px;
    font-size: 14px;
}
.message-content {
    line-height: 1.5;
}
.user-message {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    border-left: 4px solid #2196f3;
}
.claude-message {
    background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
    border-left: 4px solid #9c27b0;
}
.system-message {
    background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
    border-left: 4px solid #ff9800;
}
.error-message {
    background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
    border-left: 4px solid #f44336;
}

.input-section {
    border-top: 1px solid #e9ecef;
    padding-top: 20px;
}
.input-container {
    display: flex;
    gap: 15px;
}
.chat-input {
    flex: 1;
    height: 100px;
    padding: 12px;
    border: 1px solid #ced4da;
    border-radius: 8px;
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
}
.input-controls {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.btn-send {
    padding: 12px 20px;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-send:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(40,167,69,0.3);
}
.quick-commands {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.cmd-btn {
    padding: 6px 10px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.2s;
}
.cmd-btn:hover {
    background: #e9ecef;
}

.scratch-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 25px;
}
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}
.section-header h3 {
    margin: 0;
    color: #495057;
}
.scratch-controls {
    display: flex;
    gap: 8px;
}
.btn-mini {
    padding: 4px 8px;
    border: none;
    border-radius: 4px;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.2s;
}
.scratch-textarea {
    width: 100%;
    height: 120px;
    padding: 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-family: 'Consolas', monospace;
    font-size: 13px;
    resize: vertical;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-top: 25px;
}
.action-category {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.action-category h4 {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 16px;
}
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.action-btn {
    padding: 12px 16px;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}
.action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}
.action-btn.primary { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; }
.action-btn.success { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); color: white; }
.action-btn.info { background: linear-gradient(135deg, #17a2b8 0%, #117a8b 100%); color: white; }
.action-btn.warning { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #000; }
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