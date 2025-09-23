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
    messageDiv.className = `message ${type}`;
    
    // Decode HTML entities and preserve formatting
    const decodedContent = decodeHtmlEntities(content);
    
    const senderName = sender || (type === 'claude' ? '🤖 Claude' : type.charAt(0).toUpperCase() + type.slice(1));
    
    messageDiv.innerHTML = `
        <strong>${senderName}:</strong>
        <div class="message-text">${formatMessage(decodedContent)}</div>
    `;
    
    container.appendChild(messageDiv);
    container.scrollTop = container.scrollHeight;
}

function decodeHtmlEntities(text) {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
}

function formatMessage(text) {
    // Convert triple backtick code blocks
    text = text.replace(/```([a-zA-Z0-9]*)\n([\s\S]*?)```/g, (match, lang, code) => {
        return `<pre><code class="language-${lang}">${escapeHtml(code)}</code></pre>`;
    });

    // Convert inline code
    text = text.replace(/`([^`]+)`/g, '<code>$1</code>');

    // Convert newlines to <br>
    return text.replace(/\n/g, '<br>').replace(/\t/g, '&nbsp;&nbsp;&nbsp;&nbsp;');
}

function escapeHtml(str) {
    return str.replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
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
    
    // Send to Claude API with CSRF protection
    const selectedModel = document.getElementById('claude-model').value;
    const csrfToken = getCSRFToken();
    
    fetch('/admin/chat_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            message: message,
            model: selectedModel,
            mode: currentMode,
            history: chatHistory,
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        // Remove loading message
        const container = document.getElementById('chat-container');
        container.removeChild(container.lastChild);
        
        if (data.success) {
            addMessage('claude', data.response);
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
        const response = await fetch('/admin/get_system_prompt.php', {
            headers: {
                'X-CSRF-Token': getCSRFToken()
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        if (result.success) {
            currentSystemPrompt = result.prompt;
            document.getElementById('system-prompt').value = result.prompt;
        }
    } catch (error) {
        console.error('Failed to load system prompt:', error);
        addSystemMessage('❌ Failed to load system prompt');
    }
}

async function saveSystemPrompt() {
    const prompt = document.getElementById('system-prompt').value;
    const csrfToken = getCSRFToken();
    
    try {
        const response = await fetch('/admin/save_system_prompt.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                prompt: prompt,
                csrf_token: csrfToken
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
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
        const csrfToken = getCSRFToken();
        
        fetch('/admin/reset_system_prompt.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                csrf_token: csrfToken
            })
        })
        .then(r => {
            if (!r.ok) {
                throw new Error(`HTTP error! status: ${r.status}`);
            }
            return r.json();
        })
        .then(result => {
            if (result.success) {
                loadSystemPrompt();
                addSystemMessage('✅ System prompt reset to default');
            } else {
                addSystemMessage('❌ Failed to reset prompt');
            }
        })
        .catch(error => {
            addSystemMessage('❌ Error resetting prompt: ' + error.message);
        });
    }
}

// Scratch Pad Functions
async function saveScratchPad() {
    const content = document.getElementById('scratch-pad').value;
    const csrfToken = getCSRFToken();
    
    try {
        const response = await fetch('/admin/chat_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                action: 'save_scratch_pad',
                content: content,
                csrf_token: csrfToken
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
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
        const csrfToken = getCSRFToken();
        
        try {
            const response = await fetch('/admin/chat_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    action: 'save_scratch_pad',
                    content: '',
                    csrf_token: csrfToken
                })
            });
            
            if (response.ok) {
                addSystemMessage('✅ Scratch pad cleared');
            } else {
                addSystemMessage('❌ Error clearing notes');
            }
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

// CSRF Token Management
function getCSRFToken() {
    // Try to get from meta tag first
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        return metaToken.getAttribute('content');
    }
    
    // Fallback to session storage or generate new one
    let token = sessionStorage.getItem('csrf_token');
    if (!token) {
        // Generate a simple token for client-side (server should validate properly)
        token = 'csrf_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
        sessionStorage.setItem('csrf_token', token);
    }
    return token;
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
        const csrfToken = getCSRFToken();
        
        const response = await fetch('/admin/chat_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                action: 'get_scratch_pad',
                csrf_token: csrfToken
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
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