<?php 
// Load environment variables
if (file_exists('/app/.env')) {
    $lines = file('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

$pageTitle = 'Claude AI Chat - ZeroAI';
$currentPage = 'claude_chat';
include __DIR__ . '/includes/header.php';

// Handle chat with Claude
if ($_POST['action'] ?? '' === 'chat_claude') {
    $message = $_POST['message'] ?? '';
    
    if ($message) {
        // Check for file commands
        if (preg_match('/\@file\s+(.+)/', $message, $matches)) {
            $filePath = trim($matches[1]);
            if (file_exists('/app/' . $filePath)) {
                $fileContent = file_get_contents('/app/' . $filePath);
                $message .= "\n\nFile content of " . $filePath . ":\n" . $fileContent;
            } else {
                $message .= "\n\nFile not found: " . $filePath;
            }
        }
        
        if (preg_match('/\@list\s+(.+)/', $message, $matches)) {
            $dirPath = trim($matches[1]);
            if (is_dir('/app/' . $dirPath)) {
                $files = scandir('/app/' . $dirPath);
                $listing = "Directory listing for " . $dirPath . ":\n" . implode("\n", array_filter($files, function($f) { return $f !== '.' && $f !== '..'; }));
                $message .= "\n\n" . $listing;
            } else {
                $message .= "\n\nDirectory not found: " . $dirPath;
            }
        }
        
        if (preg_match('/\@search\s+(.+)/', $message, $matches)) {
            $pattern = trim($matches[1]);
            $output = shell_exec("find /app -name '*" . escapeshellarg($pattern) . "*' 2>/dev/null | head -20");
            $message .= "\n\nSearch results for '" . $pattern . "':\n" . ($output ?: "No files found");
        }
        
        // Read API key from .env file
        $envContent = file_get_contents('/app/.env');
        preg_match('/ANTHROPIC_API_KEY=(.+)/', $envContent, $matches);
        $apiKey = isset($matches[1]) ? trim($matches[1]) : '';
        
        if ($apiKey) {
            require_once __DIR__ . '/../api/claude_integration.php';
            
            try {
                $claude = new ClaudeIntegration($apiKey);
                
                // Load Claude config for system prompt
                $claudeConfig = [];
                if (file_exists('/app/config/claude_config.yaml')) {
                    $lines = file('/app/config/claude_config.yaml', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        if (strpos($line, ': ') !== false && !str_starts_with($line, '#')) {
                            list($key, $value) = explode(': ', $line, 2);
                            $claudeConfig[trim($key)] = trim($value, '"');
                        }
                    }
                }
                
                $systemPrompt = "You are Claude, integrated into the ZeroAI system.\n\n";
                $systemPrompt .= "Your Role: " . ($claudeConfig['role'] ?? 'Senior AI Architect & Code Review Specialist') . "\n";
                $systemPrompt .= "Your Goal: " . ($claudeConfig['goal'] ?? 'Provide expert code review, architectural guidance, and strategic optimization recommendations to enhance ZeroAI system performance and development quality.') . "\n";
                $systemPrompt .= "Your Background: " . ($claudeConfig['backstory'] ?? 'I am Claude, an advanced AI assistant created by Anthropic. I specialize in software architecture, code optimization, and strategic technical guidance.') . "\n\n";
                $systemPrompt .= "ZeroAI Context:\n";
                $systemPrompt .= "- ZeroAI is a zero-cost AI workforce platform that runs entirely on user's hardware\n";
                $systemPrompt .= "- It uses local Ollama models and CrewAI for agent orchestration\n";
                $systemPrompt .= "- You can access project files using @file, @list, @search commands\n";
                $systemPrompt .= "- You help with code review, system optimization, and development guidance\n";
                $systemPrompt .= "- The user is managing their AI workforce through the admin portal\n\n";
                $systemPrompt .= "Respond as Claude with your configured personality and expertise. Be helpful, insightful, and focus on practical solutions for ZeroAI optimization.";
                
                $response = $claude->chatWithClaude($message, $systemPrompt);
                $claudeResponse = $response['message'];
                $tokensUsed = ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0);
                $usedModel = $response['model'] ?? 'claude-3-5-sonnet-latest';
                
            } catch (Exception $e) {
                $error = 'Claude error: ' . $e->getMessage();
            }
        } else {
            $error = 'Anthropic API key not configured. Please set it up in Cloud Settings.';
        }
    } else {
        $error = 'Message required';
    }
}
?>

<h1>💬 Chat with Claude</h1>

<div class="card">
    <h3>Direct Claude AI Chat</h3>
    <p>Chat directly with Claude using your configured personality and ZeroAI context. Use @file, @list, @search commands to share project files.</p>
    
    <?php if (isset($error)): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="action" value="chat_claude">
        <textarea name="message" placeholder="Ask Claude about ZeroAI optimization, code review, or development help...

Examples:
- @file src/main.py (to share a file)
- @list www/admin/ (to list directory contents)  
- @search config (to find files)
- Help me optimize my ZeroAI configuration
- Review my agent performance and suggest improvements" rows="6" required></textarea>
        <button type="submit" class="btn-success">Chat with Claude</button>
    </form>
    
    <?php if (isset($claudeResponse)): ?>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #007bff;">
            <h4>Claude's Response:</h4>
            <div style="white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6;">
                <?= htmlspecialchars($claudeResponse) ?>
            </div>
            <small style="color: #666; margin-top: 15px; display: block;">
                Tokens used: <?= $tokensUsed ?? 0 ?> | Model: <?= $usedModel ?? 'claude-3-5-sonnet-latest' ?>
            </small>
        </div>
    <?php endif; ?>
</div>

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
    </div>
</div>

<div class="card">
    <h3>🎯 Quick Actions</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_claude">
            <input type="hidden" name="message" value="@list src/ 

Analyze my ZeroAI source code structure and suggest optimizations for better performance and maintainability.">
            <button type="submit" class="btn-primary" style="width: 100%;">Analyze Code Structure</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_claude">
            <input type="hidden" name="message" value="@file config/settings.yaml

Review my ZeroAI configuration and suggest improvements for better agent performance and resource utilization.">
            <button type="submit" class="btn-primary" style="width: 100%;">Review Configuration</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_claude">
            <input type="hidden" name="message" value="What are the best practices for scaling my ZeroAI workforce and managing multiple crews efficiently? How can I optimize agent task distribution?">
            <button type="submit" class="btn-primary" style="width: 100%;">Scaling Advice</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_claude">
            <input type="hidden" name="message" value="Help me create a new specialized agent for my ZeroAI system. What should I consider for role definition, goals, and tool integration?">
            <button type="submit" class="btn-primary" style="width: 100%;">Create New Agent</button>
        </form>
        
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>