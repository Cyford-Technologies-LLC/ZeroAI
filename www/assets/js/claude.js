// Claude Chat Interface JavaScript

let chatHistory = [];
let currentMode = 'chat';

// Mode Management
function updateMode() {
    currentMode = document.getElementById('claude-mode').value;
    addSystemMessage(`Mode changed to: ${currentMode}`);
}

// Command Insertion
function insertCommand(command) {
    const input = document.getElementById('user-input');
    input.value += command;
    input.focus();
}

function quickCommand(command) {
    document.getElementById('user-input').value = command;
    sendMessage();
}

// Message Management
function addMessage(type, content, sender = '') {
    const container = document.getElementById('chat-container');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}-message`;
    
    const headerDiv = document.createElement('div');
    headerDiv.className = 'message-header';
    headerDiv.textContent = sender || type.charAt(0).toUpperCase() + type.slice(1);
    
    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content';
    contentDiv.textContent = content;
    
    messageDiv.appendChild(headerDiv);
    messageDiv.appendChild(contentDiv);
    container.appendChild(messageDiv);
    container.scrollTop = container.scrollHeight;
}

function addSystemMessage(content) {
    addMessage('system', content, 'System');
}

// Chat Functions
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
        document.getElementById('chat-container').innerHTML = '<div class="message system-message"><div class="message-header">🤖 System</div><div class="message-content">Claude is ready! Use @commands to interact with your ZeroAI system.</div></div>';
        chatHistory = [];
    }
}

// System Prompt Functions
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

// Scratch Pad Functions
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

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Enter key to send
    const userInput = document.getElementById('user-input');
    if (userInput) {
        userInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }
    
    // Load scratch pad on page load
    loadScratchPad();
});

// Load scratch pad on page load
async function loadScratchPad() {
    try {
        const response = await fetch('/admin/claude_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_scratch_pad'})
        });
        const result = await response.json();
        if (result.success && result.content) {
            const scratchPad = document.getElementById('scratch-pad');
            if (scratchPad) {
                scratchPad.value = result.content;
            }
        }
    } catch (error) {
        console.error('Failed to load scratch pad:', error);
    }
}