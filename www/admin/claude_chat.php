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
        <select id="claude-model" class="select-modern">
            <option value="claude-opus-4-1-20250805">Claude Opus 4.1 (Most Advanced)</option>
            <option value="claude-opus-4-20250514">Claude Opus 4</option>
            <option value="claude-sonnet-4-20250514">Claude Sonnet 4</option>
            <option value="claude-3-7-sonnet-20250219">Claude 3.7 Sonnet</option>
            <option value="claude-haiku-3.5-20250514">Claude Haiku 3.5</option>
            <option value="claude-3-haiku-20240307">Claude Haiku 3</option>
            <option value="claude-3-5-sonnet-20241022" selected>Claude 3.5 Sonnet (Default)</option>
            <option value="claude-3-opus-20240229">Claude 3 Opus (Legacy)</option>
            <option value="claude-3-sonnet-20240229">Claude 3 Sonnet (Legacy)</option>
        </select>
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





<?php include __DIR__ . '/includes/footer.php'; ?>