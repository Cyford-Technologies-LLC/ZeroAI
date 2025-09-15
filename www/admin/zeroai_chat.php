<?php
session_start();
require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/../src/Services/ZeroAIChatService.php';

use ZeroAI\Services\ZeroAIChatService;

$chat = new ZeroAIChatService();
$agentId = $_GET['agent'] ?? null;
$sessionId = $_GET['session'] ?? null;

if ($agentId && !$sessionId) {
    if ($agentId === 'claude') {
        $agents = $chat->getAvailableAgents();
        foreach ($agents as $agent) {
            if ($agent['name'] === 'Claude AI Assistant') {
                $agentId = $agent['id'];
                break;
            }
        }
        
        if (!isset($agentId) || $agentId === 'claude') {
            $agentData = [
                'name' => 'Claude AI Assistant',
                'role' => 'Senior AI Architect & Code Review Specialist',
                'goal' => 'Provide expert code review, architectural guidance, and strategic optimization recommendations',
                'backstory' => 'Advanced AI assistant created by Anthropic, specializing in software architecture and code optimization'
            ];
            
            $agent = new \ZeroAI\Models\Agent();
            $agentId = $agent->create($agentData);
        }
    }
    
    $sessionId = $chat->startChatSession($agentId, $_SESSION['admin_user']);
    header("Location: /admin/zeroai_chat.php?session=$sessionId");
    return;
}

if ($_POST['action'] ?? '' === 'send_message' && $sessionId) {
    $message = \ZeroAI\Core\InputValidator::sanitize($_POST['message']);
    $response = $chat->sendMessage($sessionId, $message);
    header("Location: /admin/zeroai_chat.php?session=$sessionId");
    return;
}

$pageTitle = 'ZeroAI Chat';
$currentPage = 'chat';
include __DIR__ . '/includes/header.php';

$agents = $chat->getAvailableAgents();
$sessions = $chat->getUserSessions($_SESSION['admin_user']);
$messages = $sessionId ? $chat->getChatHistory($sessionId) : [];
?>

<style>
.chat-container { display: grid; grid-template-columns: 250px 1fr; gap: 20px; height: 70vh; }
.chat-sidebar { background: white; border-radius: 8px; padding: 15px; overflow-y: auto; }
.chat-main { background: white; border-radius: 8px; display: flex; flex-direction: column; }
.chat-header { padding: 15px; border-bottom: 1px solid #eee; }
.chat-messages { flex: 1; padding: 15px; overflow-y: auto; }
.chat-input { padding: 15px; border-top: 1px solid #eee; }
.message { margin: 10px 0; padding: 10px; border-radius: 8px; }
.user-message { background: #007bff; color: white; margin-left: 20%; }
.agent-message { background: #f8f9fa; margin-right: 20%; }
.session-item { padding: 8px; margin: 5px 0; border-radius: 4px; cursor: pointer; }
.session-item:hover { background: #f8f9fa; }
.session-item.active { background: #007bff; color: white; }
</style>

<h1>ZeroAI Agent Chat</h1>

<div class="chat-container">
    <div class="chat-sidebar">
        <h4>Available Agents</h4>
        <?php foreach ($agents as $agent): ?>
            <div class="session-item" onclick="startChat(<?= $agent['id'] ?>)">
                <strong><?= htmlspecialchars($agent['name']) ?></strong><br>
                <small><?= htmlspecialchars($agent['role']) ?></small>
            </div>
        <?php endforeach; ?>
        
        <h4 style="margin-top: 20px;">Recent Chats</h4>
        <?php foreach ($sessions as $session): ?>
            <div class="session-item <?= $session['id'] == $sessionId ? 'active' : '' ?>" 
                 onclick="openSession(<?= $session['id'] ?>)">
                <strong><?= htmlspecialchars($session['agent_name']) ?></strong><br>
                <small><?= $session['message_count'] ?> messages</small>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="chat-main">
        <?php if ($sessionId): ?>
            <div class="chat-header">
                <h3>Chat with <?= htmlspecialchars($messages[0]['agent_name'] ?? 'Agent') ?></h3>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <?php foreach ($messages as $msg): ?>
                    <div class="message user-message">
                        <strong>You:</strong> <?= htmlspecialchars($msg['message']) ?>
                    </div>
                    <?php if ($msg['response']): ?>
                        <div class="message agent-message">
                            <strong>Agent:</strong> <?= htmlspecialchars($msg['response']) ?>
                            <small style="opacity: 0.7;">(<?= $msg['tokens_used'] ?> tokens)</small>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="chat-input">
                <form method="POST" style="display: flex; gap: 10px;">
                    <input type="hidden" name="action" value="send_message">
                    <input type="text" name="message" placeholder="Type your message..." 
                           style="flex: 1;" required autofocus>
                    <button type="submit" class="btn-success">Send</button>
                </form>
            </div>
        <?php else: ?>
            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">
                Select an agent to start chatting
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function startChat(agentId) {
    window.location.href = '/admin/zeroai_chat.php?agent=' + agentId;
}

function openSession(sessionId) {
    window.location.href = '/admin/zeroai_chat.php?session=' + sessionId;
}

document.addEventListener('DOMContentLoaded', function() {
    const messages = document.getElementById('chatMessages');
    if (messages) {
        messages.scrollTop = messages.scrollHeight;
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
