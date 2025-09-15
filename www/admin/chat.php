<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'Agent Chat - ZeroAI';
$currentPage = 'chat';
include __DIR__ . '/includes/header.php';
?>

<h1>🤖 Agent Chat</h1>

<div class="card">
    <h3>Chat Interface</h3>
    <p>Select a chat option from the sidebar to start chatting with AI agents.</p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 20px;">
        <a href="/admin/crew_chat.php" style="text-decoration: none;">
            <div style="border: 2px solid #28a745; border-radius: 8px; padding: 20px; text-align: center; transition: background 0.3s;">
                <h4 style="margin: 0 0 10px 0; color: #28a745;">👥 Crew Chat</h4>
                <p style="margin: 0; color: #666;">Chat with your AI crew teams</p>
            </div>
        </a>
        
        <a href="/admin/claude_chat.php" style="text-decoration: none;">
            <div style="border: 2px solid #9c27b0; border-radius: 8px; padding: 20px; text-align: center; transition: background 0.3s;">
                <h4 style="margin: 0 0 10px 0; color: #9c27b0;">🔮 Claude Chat</h4>
                <p style="margin: 0; color: #666;">Direct chat with Claude AI</p>
            </div>
        </a>
        
        <div style="border: 2px solid #6c757d; border-radius: 8px; padding: 20px; text-align: center; opacity: 0.6;">
            <h4 style="margin: 0 0 10px 0; color: #6c757d;">🤖 Individual Agents</h4>
            <p style="margin: 0; color: #666;">Coming soon - Chat with specific agents</p>
        </div>
    </div>
</div>

<div class="card">
    <h3>Quick Actions</h3>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="/admin/crew_chat.php" class="btn-success">Start Crew Chat</a>
        <a href="/admin/claude_chat.php" class="btn-secondary">Chat with Claude</a>
        <a href="/admin/agents.php" class="btn-secondary">Manage Agents</a>
        <a href="/admin/crews.php" class="btn-secondary">View Crews</a>
    </div>
</div>

<style>
a div:hover {
    background: #f8f9fa;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
