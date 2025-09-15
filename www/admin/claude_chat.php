<?php
try {
    $pageTitle = 'Claude Chat - ZeroAI';
    $currentPage = 'claude_chat';
    include __DIR__ . '/includes/header.php';
} catch (Exception $e) {
    try {
        require_once __DIR__ . '/../src/bootstrap.php';
        $logger = \ZeroAI\Core\Logger::getInstance();
        $logger->logClaude('Claude chat bootstrap failed: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    } catch (Exception $logError) {
        error_log('Claude Chat Bootstrap Error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo 'System error';
    exit;
}


// Block demo users from Claude chat
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'demo') {
    header('Location: /admin/dashboard.php?error=Demo users cannot access Claude chat');
    exit;
}


?>

<div class="chat-container">
    <div class="card mb-4">
        <div class="card-header">
            <h3>💬 Claude AI Assistant</h3>
            <p class="mb-0 text-muted">Your intelligent development companion</p>
        </div>
        <div class="card-body">
            <div class="row mb-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Model <span id="model-source-indicator"></span></label>
                    <select id="claude-model" class="form-select form-select-sm">
                        <option value="claude-opus-4-1-20250805">Opus 4.1</option>
                        <option value="claude-3-5-sonnet-20241022" selected>Sonnet 3.5</option>
                        <option value="claude-3-opus-20240229">Opus 3</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mode</label>
                    <select id="claude-mode" class="form-select form-select-sm">
                        <option value="chat">💬 Chat</option>
                        <option value="autonomous">🤖 Auto</option>
                        <option value="hybrid">⚡ Hybrid</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-2">
                        <button onclick="togglePromptEditor()" class="btn btn-outline-primary btn-sm">✏️ Prompt</button>
                        <button onclick="toggleScratchPad()" class="btn btn-outline-info btn-sm">📝 Scratch</button>
                        <a href="/admin/claude_settings.php" class="btn btn-outline-secondary btn-sm">⚙️ Settings</a>
                    </div>
                </div>
            </div>
            
            <!-- System Prompt Editor -->
            <div id="prompt-editor" style="display: none;">
                <hr>
                <h6>📝 System Prompt Editor</h6>
                <small class="text-muted">Commands (@file, @create, @edit, etc.) are automatically available</small>
                <div class="mt-2">
                    <textarea id="system-prompt" class="form-control form-control-lg" placeholder="Loading system prompt..."></textarea>
                    <div class="mt-3">
                        <button onclick="saveSystemPrompt()" class="btn btn-success btn-sm">✅ Save</button>
                        <button onclick="resetSystemPrompt()" class="btn btn-warning btn-sm">🔄 Reset</button>
                        <button onclick="togglePromptEditor()" class="btn btn-secondary btn-sm">❌ Cancel</button>
                    </div>
                </div>
            </div>
            
            <!-- Scratch Pad -->
            <div id="scratch-pad-editor" style="display: none;">
                <hr>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6>📝 Scratch Pad</h6>
                    <div class="btn-group" role="group">
                        <button onclick="saveScratchPad()" class="btn btn-outline-success btn-sm">💾 Save</button>
                        <button onclick="insertToChat()" class="btn btn-outline-primary btn-sm">➡️ To Chat</button>
                        <button onclick="clearScratchPad()" class="btn btn-outline-danger btn-sm">🗑️ Clear</button>
                    </div>
                </div>
                <textarea id="scratch-pad" class="form-control" rows="6" placeholder="Quick notes, code snippets, ideas..."></textarea>
            </div>
        </div>
    </div>
    
    <!-- Chat Messages -->
    <div class="card mb-4">
        <div class="card-body">
            <div id="chat-container" class="chat-messages">
                <div class="message assistant">
                    <strong>🤖 Claude:</strong> Ready! Use @commands to interact with your ZeroAI system.
                </div>
            </div>
            
            <hr>
            
            <!-- Chat Input -->
            <div class="mt-3">
                <label class="form-label">Message to Claude</label>
                <textarea id="user-input" class="form-control form-control-lg" rows="4" placeholder="Ask Claude about your ZeroAI system...\n\n• @file path/to/file.py\n• @list directory/\n• @agents\n• Help optimize my configuration"></textarea>
                <div class="d-flex align-items-center mt-3">
                    <button onclick="sendMessage()" class="btn btn-primary btn-lg me-auto">🚀 Send Message</button>
                    <div class="btn-group ms-auto" role="group">
                        <button onclick="clearChat()" class="btn btn-outline-danger btn-sm">🗑️</button>
                        <button onclick="insertCommand('@file ')" class="btn btn-outline-secondary btn-sm">@file</button>
                        <button onclick="insertCommand('@list ')" class="btn btn-outline-secondary btn-sm">@list</button>
                        <button onclick="insertCommand('@agents')" class="btn btn-outline-secondary btn-sm">@agents</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6>🤖 System Commands</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button onclick="quickCommand('@agents')" class="btn btn-outline-primary">🤖 List Agents</button>
                        <button onclick="quickCommand('@crews')" class="btn btn-outline-primary">👥 Show Crews</button>
                        <button onclick="quickCommand('@list /app/src')" class="btn btn-outline-info">📁 Browse Code</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6>🔍 Analysis & Optimization</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button onclick="quickCommand('Analyze my ZeroAI system and suggest optimizations')" class="btn btn-outline-success">🔍 System Analysis</button>
                        <button onclick="quickCommand('Review my agent performance and suggest improvements')" class="btn btn-outline-warning">📊 Performance Review</button>
                        <button onclick="quickCommand('@file /app/config/settings.yaml')" class="btn btn-outline-info">⚙️ Config Review</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>





<script>
function toggleScratchPad() {
    const scratchPad = document.getElementById('scratch-pad-editor');
    if (scratchPad) {
        scratchPad.style.display = scratchPad.style.display === 'none' ? 'block' : 'none';
    }
}

function togglePromptEditor() {
    const promptEditor = document.getElementById('prompt-editor');
    if (promptEditor) {
        if (promptEditor.style.display === 'none') {
            promptEditor.style.display = 'block';
            loadSystemPrompt();
        } else {
            promptEditor.style.display = 'none';
        }
    }
}

function sendMessage() {
    const input = document.getElementById('user-input');
    const message = input.value.trim();
    if (!message) return;
    
    const modelSelect = document.getElementById('claude-model');
    const selectedModel = modelSelect ? modelSelect.value : 'claude-3-5-sonnet-20241022';
    
    // Add user message to chat
    addMessageToChat('user', message);
    input.value = '';
    
    // Show typing indicator
    addMessageToChat('assistant', 'Claude is typing...');
    
    // Send to backend with model selection
    fetch('/admin/providers/claude/claude_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'chat', message: message, model: selectedModel})
    })
    .then(response => response.json())
    .then(data => {
        // Remove typing indicator
        const messages = document.querySelectorAll('.message.assistant');
        const lastMessage = messages[messages.length - 1];
        if (lastMessage && lastMessage.textContent.includes('typing')) {
            lastMessage.remove();
        }
        
        if (data.success) {
            addMessageToChat('assistant', data.response);
            if (data.usage && data.usage.input_tokens) {
                addUsageInfo(data.model, data.usage);
            }
        } else {
            addMessageToChat('assistant', 'Error: ' + data.error);
        }
    })
    .catch(error => {
        // Remove typing indicator
        const messages = document.querySelectorAll('.message.assistant');
        const lastMessage = messages[messages.length - 1];
        if (lastMessage && lastMessage.textContent.includes('typing')) {
            lastMessage.remove();
        }
        addMessageToChat('assistant', 'Connection error: ' + error.message);
    });
}

function addMessageToChat(sender, message) {
    const container = document.getElementById('chat-container');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message ' + sender;
    messageDiv.innerHTML = '<strong>' + (sender === 'user' ? '👤 You:' : '🤖 Claude:') + '</strong> ' + message;
    container.appendChild(messageDiv);
    container.scrollTop = container.scrollHeight;
}

function insertCommand(command) {
    const input = document.getElementById('user-input');
    input.value += command;
    input.focus();
}

function clearChat() {
    const container = document.getElementById('chat-container');
    container.innerHTML = '<div class="message assistant"><strong>🤖 Claude:</strong> Chat cleared. How can I help you?</div>';
}

function testConnection() {
    fetch('/admin/providers/claude/claude_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'test_connection'})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            addMessageToChat('assistant', '✅ Connection test successful: ' + data.message);
        } else {
            addMessageToChat('assistant', '❌ Connection test failed: ' + data.error);
        }
    })
    .catch(error => {
        addMessageToChat('assistant', '❌ Connection error: ' + error.message);
    });
}

function quickCommand(command) {
    document.getElementById('user-input').value = command;
    sendMessage();
}

function saveScratchPad() {
    const content = document.getElementById('scratch-pad').value;
    
    // Save to server using Claude class
    fetch('/admin/providers/claude/claude_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'save_scratch', content: content})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Also save locally as backup
            localStorage.setItem('claude-scratch-pad', content);
            alert('Scratch pad saved to server!');
        } else {
            alert('Error saving: ' + data.error);
        }
    })
    .catch(error => {
        // Fallback to local storage
        localStorage.setItem('claude-scratch-pad', content);
        alert('Saved locally (server unavailable)');
    });
}

function insertToChat() {
    const content = document.getElementById('scratch-pad').value;
    document.getElementById('user-input').value = content;
}

function clearScratchPad() {
    document.getElementById('scratch-pad').value = '';
}

function addUsageInfo(model, usage) {
    const container = document.getElementById('chat-container');
    const usageDiv = document.createElement('div');
    usageDiv.className = 'usage-info';
    usageDiv.style.cssText = 'font-size: 11px; color: #666; text-align: right; margin: 5px 0; padding: 5px; background: #f8f9fa; border-radius: 4px;';
    usageDiv.innerHTML = `Model: ${model} | Tokens: ${usage.input_tokens || 0} in, ${usage.output_tokens || 0} out`;
    container.appendChild(usageDiv);
}

function saveSystemPrompt() {
    try {
        const content = document.getElementById('system-prompt').value;
        console.log('SAVE: Content length:', content.length);
        
        if (!content.trim()) {
            alert('❌ Cannot save empty prompt');
            return;
        }
        
        fetch('/admin/providers/claude/claude_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'save_system_prompt', content: content})
        })
        .then(response => {
            console.log('SAVE: Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('SAVE: Response data:', data);
            if (data.success) {
                alert('✅ System prompt saved successfully!');
                document.getElementById('prompt-editor').style.display = 'none';
            } else {
                alert('❌ Error saving system prompt: ' + data.error);
                console.error('SAVE ERROR:', data);
            }
        })
        .catch(error => {
            console.error('SAVE FETCH ERROR:', error);
            alert('❌ Connection error: ' + error.message);
        });
    } catch (error) {
        console.error('SAVE JS ERROR:', error);
        alert('❌ JavaScript error: ' + error.message);
    }
}

function resetSystemPrompt() {
    if (confirm('Reset system prompt to default? This will delete your custom prompt.')) {
        fetch('/admin/providers/claude/claude_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'reset_system_prompt'})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ System prompt reset to default!');
                loadSystemPrompt();
            } else {
                alert('❌ Error resetting system prompt: ' + data.error);
            }
        })
        .catch(error => {
            alert('❌ Connection error: ' + error.message);
        });
    }
}

function loadSystemPrompt() {
    try {
        console.log('LOAD: Starting system prompt load');
        
        fetch('/admin/providers/claude/claude_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_system_prompt'})
        })
        .then(response => {
            console.log('LOAD: Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('LOAD: Response data:', data);
            console.log('LOAD: Content length:', (data.content || '').length);
            
            if (data.success) {
                document.getElementById('system-prompt').value = data.content || '';
                console.log('LOAD: Prompt loaded successfully');
            } else {
                console.error('LOAD ERROR:', data.error);
                alert('❌ Error loading system prompt: ' + data.error);
            }
        })
        .catch(error => {
            console.error('LOAD FETCH ERROR:', error);
            alert('❌ Connection error loading prompt: ' + error.message);
        });
    } catch (error) {
        console.error('LOAD JS ERROR:', error);
        alert('❌ JavaScript error loading prompt: ' + error.message);
    }
}

// Load models and scratch pad on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load real Claude models
    fetch('/admin/providers/claude/claude_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'get_models'})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.models) {
            const modelSelect = document.getElementById('claude-model');
            const currentValue = modelSelect.value;
            
            // Clear existing options
            modelSelect.innerHTML = '';
            
            // Update source indicator
            const sourceIndicator = document.getElementById('model-source-indicator');
            if (sourceIndicator) {
                const color = data.color === 'green' ? '#28a745' : '#dc3545';
                sourceIndicator.innerHTML = `<span style="color: ${color}; font-weight: bold;">(${data.source})</span>`;
            }
            
            // Add real models
            data.models.forEach(model => {
                const option = document.createElement('option');
                option.value = model;
                option.textContent = model;
                if (model === currentValue || model === 'claude-3-5-haiku-20241022') {
                    option.selected = true;
                }
                modelSelect.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.log('Failed to load models, using defaults');
    });
    
    // Load scratch pad
    fetch('/admin/providers/claude/claude_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'get_scratch'})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.content) {
            document.getElementById('scratch-pad').value = data.content;
        } else {
            // Fallback to local storage
            const saved = localStorage.getItem('claude-scratch-pad');
            if (saved) {
                document.getElementById('scratch-pad').value = saved;
            }
        }
    })
    .catch(error => {
        // Fallback to local storage
        const saved = localStorage.getItem('claude-scratch-pad');
        if (saved) {
            document.getElementById('scratch-pad').value = saved;
        }
    });
    
    // Don't auto-load system prompt on page load
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
