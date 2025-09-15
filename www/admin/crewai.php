<?php 
$pageTitle = 'CrewAI Overview - ZeroAI';
$currentPage = 'crews'; // Set to crews so sidebar shows CrewAI links
include __DIR__ . '/includes/header.php';
?>

<h1>CrewAI Overview</h1>

<div class="card">
    <h3>🚀 Quick Start</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="/admin/crew_chat" style="display: block; padding: 20px; background: #007bff; color: white; text-decoration: none; border-radius: 8px; text-align: center;">
            <h4>💬 Crew Chat</h4>
            <p>Talk to your AI crew</p>
        </a>
        <a href="/admin/agents" style="display: block; padding: 20px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; text-align: center;">
            <h4>🤖 Agents</h4>
            <p>Manage AI agents</p>
        </a>
        <a href="/admin/crews" style="display: block; padding: 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 8px; text-align: center;">
            <h4>👥 Crews</h4>
            <p>Organize agent teams</p>
        </a>
        <a href="/admin/claude" style="display: block; padding: 20px; background: #6f42c1; color: white; text-decoration: none; border-radius: 8px; text-align: center;">
            <h4>🧠 Claude AI</h4>
            <p>Advanced AI assistant</p>
        </a>
    </div>
</div>

<div class="card">
    <h3>📊 System Status</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #28a745;">
            <h4>🟢 Local Models</h4>
            <p>Ollama: Connected</p>
            <p>Model: llama3.2:1b</p>
        </div>
        <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;">
            <h4>☁️ Cloud AI</h4>
            <p>Claude: Available</p>
            <p>Status: Ready</p>
        </div>
        <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #17a2b8;">
            <h4>👥 Active Crews</h4>
            <p>DevOps: Ready</p>
            <p>Research: Ready</p>
        </div>
    </div>
</div>

<div class="card">
    <h3>🎯 Recent Activity</h3>
    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
        <p>No recent crew activities. <a href="/admin/crew_chat">Start a conversation</a> to see activity here.</p>
    </div>
</div>

        </div>
    </div>
<?php include __DIR__ . '/includes/footer.php'; ?>
