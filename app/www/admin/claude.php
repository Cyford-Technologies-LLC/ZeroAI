<?php 
$pageTitle = 'Claude Assistant - ZeroAI';
$currentPage = 'claude';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../api/claude_integration.php';

$claude = new ClaudeIntegration();

// Handle chat with Claude
if ($_POST['action'] ?? '' === 'chat_claude') {
    try {
        $response = $claude->helpWithZeroAI($_POST['message'], [
            'user' => $_SESSION['admin_user'],
            'system_status' => 'online',
            'active_agents' => 3
        ]);
        $claudeResponse = $response['message'];
        $tokensUsed = $response['usage']['input_tokens'] ?? 0 + $response['usage']['output_tokens'] ?? 0;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Test connection
if ($_POST['action'] ?? '' === 'test_connection') {
    $testResult = $claude->testConnection();
}
?>

<h1>Claude AI Assistant</h1>

<div class="card">
    <h3>Claude Integration Status</h3>
    
    <form method="POST" style="display: inline;">
        <input type="hidden" name="action" value="test_connection">
        <button type="submit" class="btn-primary">Test Claude Connection</button>
    </form>
    
    <?php if (isset($testResult)): ?>
        <div class="message <?= $testResult['success'] ? '' : 'error' ?>">
            <?php if ($testResult['success']): ?>
                ✅ Claude connected successfully! Model: <?= $testResult['model'] ?>
            <?php else: ?>
                ❌ Connection failed: <?= htmlspecialchars($testResult['error']) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Chat with Claude</h3>
    <p>Ask Claude to help optimize your ZeroAI system, analyze agent performance, or assist with development.</p>
    
    <?php if (isset($error)): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="action" value="chat_claude">
        <textarea name="message" placeholder="Ask Claude about ZeroAI optimization, agent improvements, or development help..." rows="4" required></textarea>
        <button type="submit" class="btn-success">Ask Claude</button>
    </form>
    
    <?php if (isset($claudeResponse)): ?>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #007bff;">
            <h4>Claude's Response:</h4>
            <div style="white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <?= htmlspecialchars($claudeResponse) ?>
            </div>
            <small style="color: #666; margin-top: 10px; display: block;">
                Tokens used: <?= $tokensUsed ?> | Model: claude-3-5-sonnet-20241022
            </small>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Quick Actions</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_claude">
            <input type="hidden" name="message" value="Analyze my current ZeroAI configuration and suggest optimizations for better performance and cost efficiency.">
            <button type="submit" class="btn-primary" style="width: 100%;">Optimize My Config</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_claude">
            <input type="hidden" name="message" value="Review my agent performance data and suggest improvements for better task completion rates.">
            <button type="submit" class="btn-primary" style="width: 100%;">Analyze Agents</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_claude">
            <input type="hidden" name="message" value="Help me create a new specialized agent for my ZeroAI system. What should I consider?">
            <button type="submit" class="btn-primary" style="width: 100%;">Create New Agent</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_claude">
            <input type="hidden" name="message" value="What are the best practices for scaling my ZeroAI workforce and managing multiple crews efficiently?">
            <button type="submit" class="btn-primary" style="width: 100%;">Scaling Advice</button>
        </form>
        
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>