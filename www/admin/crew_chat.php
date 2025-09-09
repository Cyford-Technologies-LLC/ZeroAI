<?php 
$pageTitle = 'Crew Chat - ZeroAI';
$currentPage = 'crew_chat';
include __DIR__ . '/includes/header.php';

// Handle crew chat
if ($_POST['action'] ?? '' === 'chat_crew') {
    $message = $_POST['message'] ?? '';
    $project = $_POST['project'] ?? 'zeroai';
    
    if ($message) {
        $escapedMessage = escapeshellarg($message);
        $escapedProject = escapeshellarg($project);
        
        $pythonCmd = 'export HOME=/tmp && cd /app && /app/venv/bin/python -c "'
            . 'import sys; sys.path.append("/app"); '
            . 'from src.ai_dev_ops_crew import run_ai_dev_ops_crew_securely; '
            . 'from src.distributed_router import DistributedRouter; '
            . 'router = DistributedRouter(); '
            . 'inputs = {\"prompt\": ' . $escapedMessage . ', \"project_id\": ' . $escapedProject . '}; '
            . 'result = run_ai_dev_ops_crew_securely(router, ' . $escapedProject . ', inputs); '
            . 'print(\"=== CREW RESULT ===\"); '
            . 'print(result.get(\"result\", \"No result\")) if result.get(\"success\") else print(\"Error:\", result.get(\"error\", \"Unknown error\")); '
            . 'print(\"=== MODEL USED ===\"); '
            . 'print(\"Model:\", result.get(\"model_used\", \"unknown\")); '
            . 'print(\"Peer:\", result.get(\"peer_used\", \"unknown\"))'
            . '" 2>&1';
        
        $output = shell_exec($pythonCmd);
        
        if ($output) {
            $crewResponse = trim($output);
        } else {
            $error = 'No response from crew';
        }
    } else {
        $error = 'Message required';
    }
}
?>

<h1>Crew Chat</h1>

<div class="card">
    <h3>Chat with Your ZeroAI Crew</h3>
    <p>Talk directly to your DevOps crew agents. They'll work together to help you with development tasks.</p>
    
    <?php if (isset($error)): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="action" value="chat_crew">
        
        <label for="project">Project:</label>
        <select name="project" id="project">
            <option value="zeroai">ZeroAI</option>
            <option value="testcorp">TestCorp</option>
            <option value="custom">Custom</option>
        </select>
        
        <label for="message">Task/Question:</label>
        <textarea name="message" id="message" placeholder="Ask your crew to help with development tasks, code reviews, or project management..." rows="4" required></textarea>
        
        <button type="submit" class="btn-success">Send to Crew</button>
    </form>
    
    <?php if (isset($crewResponse)): ?>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #28a745;">
            <h4>Crew Response:</h4>
            <div style="white-space: pre-wrap; font-family: monospace; font-size: 14px;">
                <?= htmlspecialchars($crewResponse) ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Quick Tasks</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_crew">
            <input type="hidden" name="project" value="zeroai">
            <input type="hidden" name="message" value="Show me the current project status and what containers are running">
            <button type="submit" class="btn-primary" style="width: 100%;">Project Status</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_crew">
            <input type="hidden" name="project" value="zeroai">
            <input type="hidden" name="message" value="Review the latest code changes and suggest improvements">
            <button type="submit" class="btn-primary" style="width: 100%;">Code Review</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_crew">
            <input type="hidden" name="project" value="zeroai">
            <input type="hidden" name="message" value="Help me debug any issues in the current setup">
            <button type="submit" class="btn-primary" style="width: 100%;">Debug Help</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_crew">
            <input type="hidden" name="project" value="zeroai">
            <input type="hidden" name="message" value="Create a simple Python function to demonstrate the system">
            <button type="submit" class="btn-primary" style="width: 100%;">Demo Task</button>
        </form>
        
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>