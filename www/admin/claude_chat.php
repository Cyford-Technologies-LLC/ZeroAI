<?php 
$pageTitle = 'Claude AI Chat - ZeroAI';
$currentPage = 'claude_chat';
include __DIR__ . '/includes/header.php';
?>

<h1>💬 Chat with Claude</h1>

<div class="card">
    <h3>Direct Claude AI Chat</h3>
    <p>Chat directly with Claude using your configured personality and ZeroAI context. Use @file, @list, @search commands to share project files.</p>
    
    <div style="margin-bottom: 15px;">
        <button onclick="togglePromptEditor()" class="btn-warning" style="margin-bottom: 10px;">✏️ Edit System Prompt</button>
        <div id="prompt-editor" style="display: none;">
            <p style="font-size: 12px; color: #666; margin-bottom: 10px;">📝 <strong>Note:</strong> Commands (@file, @create, @edit, etc.) are automatically added to Claude's prompt even if not included here.</p>
            <textarea id="system-prompt" rows="8" style="width: 100%; font-family: monospace; font-size: 12px;" placeholder="Loading system prompt..."></textarea>
            <div style="margin-top: 10px;">
                <button onclick="saveSystemPrompt()" class="btn-success">Save Prompt</button>
                <button onclick="resetSystemPrompt()" class="btn-danger">Reset to Default</button>
                <button onclick="togglePromptEditor()" class="btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
    
    <div style="display: flex; gap: 20px; margin-bottom: 10px; align-items: center;">
        <div>
            <label><strong>Claude Model:</strong></label>
            <select id="claude-model" style="width: 300px;">
                <option value="claude-sonnet-4-20250514" selected>Claude Sonnet 4 (High Performance - Default)</option>
                <option value="claude-opus-4.1-20250514">Claude Opus 4.1 (Most Capable)</option>
                <option value="claude-opus-4-20250514">Claude Opus 4 (Previous Flagship)</option>
                <option value="claude-sonnet-3.7-20250514">Claude Sonnet 3.7 (Extended Thinking)</option>
                <option value="claude-haiku-3.5-20250514">Claude Haiku 3.5 (Fastest)</option>
                <option value="claude-3-opus-20240229">Claude 3 Opus (Legacy)</option>
                <option value="claude-3-5-sonnet-20240620">Claude 3.5 Sonnet (Legacy)</option>
                <option value="claude-3-sonnet-20240229">Claude 3 Sonnet (Legacy)</option>
                <option value="claude-haiku-3-20240307">Claude Haiku 3 (Legacy)</option>
            </select>
        </div>
        <div>
            <label><strong>Claude Mode:</strong></label>
            <select id="claude-mode" onchange="changeClaudeMode()" style="width: 200px;">
                <option value="chat">💬 Chat Mode</option>
                <option value="autonomous">🤖 Autonomous Mode</option>
                <option value="hybrid" selected>⚡ Hybrid Mode</option>
            </select>
            <div id="mode-description" style="font-size: 12px; color: #666; margin-top: 5px;">Chat + background autonomous tasks</div>
        </div>
        <div>
            <label><strong>Message History:</strong></label>
            <select id="message-limit" style="width: 120px;">
                <option value="5">Last 5</option>
                <option value="10" selected>Last 10</option>
                <option value="15">Last 15</option>
                <option value="20">Last 20</option>
                <option value="0">All Messages</option>
            </select>
            <div style="font-size: 12px; color: #666; margin-top: 5px;">Messages sent to Claude</div>
        </div>
    </div>
    
    <div id="chat-container" style="height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; margin: 15px 0; background: #f8f9fa; border-radius: 8px;">
        <div id="chat-messages"></div>
    </div>
    
    <div style="display: flex; gap: 10px;">
        <textarea id="message-input" placeholder="Ask Claude about ZeroAI optimization, code review, or development help...

Examples:
- @file src/main.py (to share a file)
- @list www/admin/ (to list directory contents)  
- @search config (to find files)
- @agents (to see all agents)
- @update_agent 5 role=&quot;Senior Developer&quot; goal=&quot;Write better code&quot;
- Help me optimize my ZeroAI configuration
- Review my agent performance and suggest improvements" rows="3" style="flex: 1;"></textarea>
        <button id="send-button" class="btn-success" style="height: fit-content;">Send</button>
    </div>
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
        <div id="status" style="color: #666; font-size: 12px;"></div>
        <div>
            <button onclick="toggleScratchPad()" class="btn-warning" style="padding: 4px 8px; font-size: 11px; margin-right: 5px;">📝 Scratch</button>
            <button onclick="clearChatHistory()" class="btn-danger" style="padding: 4px 8px; font-size: 11px;">Clear History</button>
        </div>
    </div>
    
    <div id="scratch-pad" style="display: none; margin-top: 15px; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; background: #fff9c4;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h4 style="margin: 0; color: #856404;">📝 Scratch Pad</h4>
            <div>
                <button onclick="saveScratchPad()" class="btn-success" style="padding: 2px 6px; font-size: 10px; margin-right: 5px;">Save</button>
                <button onclick="toggleScratchPad()" class="btn-secondary" style="padding: 2px 6px; font-size: 10px;">Close</button>
            </div>
        </div>
        <textarea id="scratch-text" placeholder="Save notes, code snippets, or any text here. It persists across page refreshes..." rows="8" style="width: 100%; border: 1px solid #ffc107; border-radius: 4px; padding: 8px; font-family: monospace; font-size: 12px; background: #fffbf0;"></textarea>
        <div style="font-size: 10px; color: #856404; margin-top: 5px;">Auto-saves every 10 seconds • Last saved: <span id="scratch-last-saved">Never</span></div>
    </div>
</div>

<script>
let chatHistory = JSON.parse(localStorage.getItem('claude_chat_history') || '[]');

async function sendMessage() {
    const messageInput = document.getElementById('message-input');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    const selectedModel = document.getElementById('claude-model').value;
    const claudeMode = document.getElementById('claude-mode').value;
    const autonomousMode = claudeMode === 'autonomous';
    const sendButton = document.getElementById('send-button');
    const status = document.getElementById('status');
    
    const filteredHistory = getFilteredHistory();
    const historyCount = filteredHistory.length;
    
    // Add user message to chat
    addMessageToChat('You', message, 'user');
    messageInput.value = '';
    
    // Disable send button and show loading
    sendButton.disabled = true;
    sendButton.textContent = 'Sending...';
    status.textContent = `Claude is thinking... (sending ${historyCount} previous messages)`;
    
    try {
        const response = await fetch('/admin/chat_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: message,
                model: selectedModel,
                autonomous: autonomousMode,
                history: filteredHistory
            })
        });
        
        const text = await response.text();
        if (!text.trim()) {
            throw new Error('Empty response from server');
        }
        
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            throw new Error('Invalid JSON response: ' + text.substring(0, 100));
        }
        
        if (result.success) {
            addMessageToChat('Claude', result.response, 'claude');
            const mode = document.getElementById('claude-mode').value;
            const messageLimit = document.getElementById('message-limit').value;
            const limitText = messageLimit === '0' ? 'All' : messageLimit;
            status.textContent = `Tokens: ${result.tokens} | Model: ${result.model} | Mode: ${mode} | History: ${limitText} msgs`;
        } else {
            addMessageToChat('System', 'Error: ' + result.error, 'error');
            status.textContent = 'Error occurred';
        }
    } catch (error) {
        addMessageToChat('System', 'Connection error: ' + error.message, 'error');
        status.textContent = 'Connection failed';
    }
    
    // Re-enable send button
    sendButton.disabled = false;
    sendButton.textContent = 'Send';
}

function addMessageToChat(sender, message, type) {
    const chatMessages = document.getElementById('chat-messages');
    const messageDiv = document.createElement('div');
    
    let bgColor = '#ffffff';
    let borderColor = '#007bff';
    
    if (type === 'user') {
        bgColor = '#e3f2fd';
        borderColor = '#2196f3';
    } else if (type === 'claude') {
        bgColor = '#f8f9fa';
        borderColor = '#007bff';
    } else if (type === 'error') {
        bgColor = '#ffebee';
        borderColor = '#f44336';
    }
    
    messageDiv.innerHTML = `
        <div style="background: ${bgColor}; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid ${borderColor};">
            <div style="font-weight: bold; margin-bottom: 8px; color: ${borderColor};">${sender}:</div>
            <div style="white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6;">${escapeHtml(message)}</div>
            <div style="font-size: 11px; color: #666; margin-top: 8px;">${new Date().toLocaleTimeString()}</div>
        </div>
    `;
    
    chatMessages.appendChild(messageDiv);
    
    // Scroll to bottom
    const chatContainer = document.getElementById('chat-container');
    chatContainer.scrollTop = chatContainer.scrollHeight;
    
    // Store in history (exclude system messages from API history)
    chatHistory.push({sender, message, type, timestamp: new Date()});
    
    // Persist to localStorage
    localStorage.setItem('claude_chat_history', JSON.stringify(chatHistory));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function sendQuickMessage(message) {
    document.getElementById('message-input').value = message;
    sendMessage();
}

function enableAutonomousAndSend(message) {
    document.getElementById('autonomous-mode').checked = true;
    toggleAutonomousMode();
    setTimeout(() => {
        document.getElementById('message-input').value = message;
        sendMessage();
    }, 1000);
}

// Event listeners
document.getElementById('send-button').addEventListener('click', sendMessage);
document.getElementById('message-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

function changeClaudeMode() {
    const mode = document.getElementById('claude-mode').value;
    const description = document.getElementById('mode-description');
    const status = document.getElementById('status');
    
    switch(mode) {
        case 'chat':
            description.textContent = 'Normal chat with command execution';
            status.textContent = '💬 Chat Mode: Use @commands to interact';
            addMessageToChat('System', '💬 Chat Mode: Normal conversation with @command support', 'claude');
            break;
        case 'autonomous':
            description.textContent = 'Claude works continuously and proactively';
            status.textContent = '🤖 Autonomous Mode: Claude analyzing and improving system';
            addMessageToChat('System', '🤖 Autonomous Mode: Claude will continuously monitor and improve your system', 'claude');
            startAutonomousMode();
            break;
        case 'hybrid':
            description.textContent = 'Chat + background autonomous tasks';
            status.textContent = '⚡ Hybrid Mode: Chat available + background tasks running';
            addMessageToChat('System', '⚡ Hybrid Mode: You can chat while Claude works in background', 'claude');
            startHybridMode();
            break;
    }
}

// Load chat history from localStorage
function loadChatHistory() {
    if (chatHistory.length === 0) {
        // Load initial message only if no history
        addMessageToChat('Claude', 'Hello! I\'m Claude, integrated into your ZeroAI system. I can help you with code review, system optimization, and development guidance.\n\nFile Commands:\n- @file path/file.py (read file)\n- @create path/file.py ```code``` (create file)\n- @edit path/file.py ```code``` (edit file)\n- @append path/file.py ```code``` (append to file)\n- @delete path/file.py (delete file)\n\nSystem Commands:\n- @agents, @crews, @list, @search\n\n🤖 Toggle Autonomous Mode to let me proactively analyze and improve your codebase!', 'claude');
    } else {
        // Restore previous chat history
        chatHistory.forEach(msg => {
            addMessageToChatNoStore(msg.sender, msg.message, msg.type);
        });
    }
}

function addMessageToChatNoStore(sender, message, type) {
    const chatMessages = document.getElementById('chat-messages');
    const messageDiv = document.createElement('div');
    
    let bgColor = '#ffffff';
    let borderColor = '#007bff';
    
    if (type === 'user') {
        bgColor = '#e3f2fd';
        borderColor = '#2196f3';
    } else if (type === 'claude') {
        bgColor = '#f8f9fa';
        borderColor = '#007bff';
    } else if (type === 'error') {
        bgColor = '#ffebee';
        borderColor = '#f44336';
    }
    
    messageDiv.innerHTML = `
        <div style="background: ${bgColor}; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid ${borderColor};">
            <div style="font-weight: bold; margin-bottom: 8px; color: ${borderColor};">${sender}:</div>
            <div style="white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6;">${escapeHtml(message)}</div>
            <div style="font-size: 11px; color: #666; margin-top: 8px;">${new Date().toLocaleTimeString()}</div>
        </div>
    `;
    
    chatMessages.appendChild(messageDiv);
    
    // Scroll to bottom
    const chatContainer = document.getElementById('chat-container');
    chatContainer.scrollTop = chatContainer.scrollHeight;
}

function clearChatHistory() {
    chatHistory = [];
    localStorage.removeItem('claude_chat_history');
    document.getElementById('chat-messages').innerHTML = '';
    loadChatHistory();
}

function getFilteredHistory() {
    const messageLimit = parseInt(document.getElementById('message-limit').value);
    const validHistory = chatHistory.filter(h => h.type !== 'error' && h.sender !== 'System');
    
    console.log(`Total chat history: ${chatHistory.length}, Valid history: ${validHistory.length}, Limit: ${messageLimit}`);
    console.log('Valid history:', validHistory);
    
    if (messageLimit === 0) {
        console.log('Sending all messages to Claude');
        return validHistory; // All messages
    }
    
    // Return last N messages
    const filtered = validHistory.slice(-messageLimit);
    console.log(`Sending last ${filtered.length} messages to Claude:`, filtered);
    return filtered;
}

// Load chat history on page load
loadChatHistory();

function startAutonomousMode() {
    // Start autonomous worker
    fetch('/admin/autonomous_start.php', {method: 'POST'})
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                addMessageToChat('System', '🤖 Autonomous worker started. Claude is now monitoring your system.', 'claude');
                pollAutonomousUpdates();
            }
        });
}

function startHybridMode() {
    // Start background tasks but keep chat active
    fetch('/admin/hybrid_start.php', {method: 'POST'})
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                addMessageToChat('System', '⚡ Hybrid mode active. Background tasks running.', 'claude');
                pollHybridUpdates();
            }
        });
}

function pollAutonomousUpdates() {
    const mode = document.getElementById('claude-mode').value;
    if (mode !== 'autonomous') return;
    
    fetch('/admin/autonomous_status.php')
        .then(r => r.json())
        .then(result => {
            if (result.updates && result.updates.length > 0) {
                result.updates.forEach(update => {
                    addMessageToChat('Claude (Auto)', update, 'claude');
                });
            }
            setTimeout(pollAutonomousUpdates, 5000);
        });
}

function pollHybridUpdates() {
    const mode = document.getElementById('claude-mode').value;
    if (mode !== 'hybrid') return;
    
    fetch('/admin/hybrid_status.php')
        .then(r => r.json())
        .then(result => {
            if (result.background_tasks) {
                document.getElementById('status').textContent += ` | BG Tasks: ${result.background_tasks}`;
            }
            setTimeout(pollHybridUpdates, 10000);
        });
}

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
        const response = await fetch('/admin/system_prompt_handler.php?action=get');
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
        const response = await fetch('/admin/system_prompt_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'save', prompt: prompt})
        });
        const result = await response.json();
        if (result.success) {
            addMessageToChat('System', '✅ System prompt updated successfully', 'claude');
            togglePromptEditor();
        } else {
            addMessageToChat('System', '❌ Failed to save prompt: ' + result.error, 'error');
        }
    } catch (error) {
        addMessageToChat('System', '❌ Error saving prompt: ' + error.message, 'error');
    }
}

function resetSystemPrompt() {
    if (confirm('Reset to default system prompt? This will overwrite your custom prompt.')) {
        fetch('/admin/reset_system_prompt.php', {method: 'POST'})
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    loadSystemPrompt();
                    addMessageToChat('System', '✅ System prompt reset to default', 'claude');
                }
            });
    }
}

function toggleScratchPad() {
    const scratchPad = document.getElementById('scratch-pad');
    if (scratchPad.style.display === 'none') {
        scratchPad.style.display = 'block';
        loadScratchPad();
    } else {
        scratchPad.style.display = 'none';
    }
}

function loadScratchPad() {
    const savedText = localStorage.getItem('claude_scratch_pad');
    const lastSaved = localStorage.getItem('claude_scratch_last_saved');
    if (savedText) document.getElementById('scratch-text').value = savedText;
    if (lastSaved) document.getElementById('scratch-last-saved').textContent = new Date(lastSaved).toLocaleString();
}

function saveScratchPad() {
    const text = document.getElementById('scratch-text').value;
    const now = new Date().toISOString();
    localStorage.setItem('claude_scratch_pad', text);
    localStorage.setItem('claude_scratch_last_saved', now);
    document.getElementById('scratch-last-saved').textContent = new Date(now).toLocaleString();
}

setInterval(() => {
    const scratchPad = document.getElementById('scratch-pad');
    if (scratchPad && scratchPad.style.display !== 'none') {
        const text = document.getElementById('scratch-text').value;
        if (text.trim()) saveScratchPad();
    }
}, 10000);
</script>

<div class="card">
    <h3>🛠️ File Access Commands</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
        <div>
            <h4>@file command</h4>
            <p><code>@file path/to/file.py</code></p>
            <p>Shares the content of a specific file with Claude for analysis</p>
        </div>
        <div>
            <h4>@list command</h4>
            <p><code>@list directory/</code></p>
            <p>Lists all files in a directory to help Claude understand your project structure</p>
        </div>
        <div>
            <h4>@search command</h4>
            <p><code>@search pattern</code></p>
            <p>Finds files matching a pattern to locate specific files or configurations</p>
        </div>
        <div>
            <h4>@agents command</h4>
            <p><code>@agents</code></p>
            <p>Lists all agents with their IDs, names, roles, and status</p>
        </div>
        <div>
            <h4>@update_agent command</h4>
            <p><code>@update_agent 5 role="New Role" goal="New Goal"</code></p>
            <p>Updates agent configuration (role, goal, backstory, status)</p>
        </div>
        <div>
            <h4>@crews command</h4>
            <p><code>@crews</code></p>
            <p>Shows currently running crews and recent crew executions</p>
        </div>
        <div>
            <h4>@analyze_crew command</h4>
            <p><code>@analyze_crew task_id_123</code></p>
            <p>Analyzes a specific crew execution with detailed information</p>
        </div>
        <div>
            <h4>@create command</h4>
            <p><code>@create path/to/file.py ```code here```</code></p>
            <p>Creates a new file with the specified content</p>
        </div>
        <div>
            <h4>@edit command</h4>
            <p><code>@edit path/to/file.py ```new content```</code></p>
            <p>Replaces entire file content with new content</p>
        </div>
        <div>
            <h4>@append command</h4>
            <p><code>@append path/to/file.py ```additional code```</code></p>
            <p>Adds content to the end of an existing file</p>
        </div>
        <div>
            <h4>@delete command</h4>
            <p><code>@delete path/to/file.py</code></p>
            <p>Deletes the specified file</p>
        </div>
        <div>
            <h4>@logs command</h4>
            <p><code>@logs [days] [agent_role]</code></p>
            <p>Shows recent crew conversation logs for analysis</p>
        </div>
        <div>
            <h4>@optimize_agents command</h4>
            <p><code>@optimize_agents</code></p>
            <p>Analyzes crew logs and suggests agent improvements</p>
        </div>
    </div>
</div>

<div class="card">
    <h3>🎯 Quick Actions</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        
        <button onclick="sendQuickMessage('@list src/\n\nAnalyze my ZeroAI source code structure and suggest optimizations for better performance and maintainability.')" class="btn-primary" style="width: 100%;">Analyze Code Structure</button>
        
        <button onclick="sendQuickMessage('@file config/settings.yaml\n\nReview my ZeroAI configuration and suggest improvements for better agent performance and resource utilization.')" class="btn-primary" style="width: 100%;">Review Configuration</button>
        
        <button onclick="sendQuickMessage('What are the best practices for scaling my ZeroAI workforce and managing multiple crews efficiently? How can I optimize agent task distribution?')" class="btn-primary" style="width: 100%;">Scaling Advice</button>
        
        <button onclick="sendQuickMessage('@agents\n\nAnalyze my current agents and suggest improvements to their roles, goals, and configurations for better performance.')" class="btn-primary" style="width: 100%;">Analyze & Improve Agents</button>
        
        <button onclick="sendQuickMessage('@file src/crews/internal/developer/crew.py\n\nReview this crew file and suggest optimizations. If you find issues, use @edit to fix them.')" class="btn-warning" style="width: 100%;">Review & Fix Crew Code</button>
        
        <button onclick="sendQuickMessage('@list src/agents/\n\nAnalyze my agent files and create improved versions using @create or @edit commands.')" class="btn-warning" style="width: 100%;">Optimize Agent Files</button>
        
        <button onclick="enableAutonomousAndSend('Perform a comprehensive analysis of my ZeroAI system. Scan all files, identify issues, and proactively fix them.')" class="btn-success" style="width: 100%;">🤖 Full System Optimization</button>
        
        <button onclick="sendQuickMessage('@logs 7\n\nAnalyze recent crew conversation logs and identify patterns in agent performance.')" class="btn-info" style="width: 100%;">📊 Analyze Crew Logs</button>
        
        <button onclick="sendQuickMessage('@optimize_agents\n\nReview agent performance data and update agent configurations for better results.')" class="btn-warning" style="width: 100%;">⚡ Optimize Agents from Logs</button>
        
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>