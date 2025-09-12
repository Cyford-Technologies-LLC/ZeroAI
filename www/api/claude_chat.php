<?php
// Suppress all output until we're ready to send JSON
ob_start();

// Set timeout limits to prevent 504 errors
ini_set('max_execution_time', 300); // 5 minutes
ini_set('memory_limit', '512M');
set_time_limit(300);

error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/nginx/error.log');

// Clean any previous output and set JSON header
ob_clean();
header('Content-Type: application/json');

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$selectedModel = $input['model'] ?? 'claude-sonnet-4-20250514';
$claudeMode = $input['mode'] ?? 'hybrid';
$autonomousMode = ($claudeMode === 'autonomous');
$conversationHistory = $input['history'] ?? [];

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Message required']);
    exit;
}

// In autonomous mode, Claude can proactively analyze and modify files
if ($autonomousMode) {
    $message = "[AUTONOMOUS MODE ENABLED] You have full access to analyze, create, edit, and optimize files proactively. " . $message;
    
    if (!preg_match('/\@(file|list|search|create|edit|append|delete)/', $message)) {
        $autoScan = "\n\nAuto-scanning key directories:\n";
        if (is_dir('/app/src')) {
            $srcFiles = shell_exec('find /app/src -name "*.py" | head -10');
            $autoScan .= "\nSrc files:\n" . ($srcFiles ?: "No Python files found");
        }
        if (is_dir('/app/config')) {
            $configFiles = scandir('/app/config');
            $autoScan .= "\nConfig files: " . implode(", ", array_filter($configFiles, function($f) { return $f !== '.' && $f !== '..'; }));
        }
        $message .= $autoScan;
    }
}

// Capture command outputs separately from chat
require_once __DIR__ . '/file_commands.php';
require_once __DIR__ . '/claude_commands.php';

$originalMessage = $message;
$commandOutputs = '';

// Process commands silently - capture outputs for Claude context only
$tempMessage = $message;
processFileCommands($tempMessage);
processClaudeCommands($tempMessage);

// Extract ALL command outputs (file + exec)
if (strlen($tempMessage) > strlen($originalMessage)) {
    $commandOutputs = substr($tempMessage, strlen($originalMessage));
}

// Keep original message clean for chat
$message = $originalMessage;

// All messages go to Claude for processing

// Only use Claude API for complex queries or when explicitly needed
$envContent = file_get_contents('/app/.env');
preg_match('/ANTHROPIC_API_KEY=(.+)/', $envContent, $matches);
$apiKey = isset($matches[1]) ? trim($matches[1]) : '';

if (!$apiKey) {
    echo json_encode(['success' => false, 'error' => 'Anthropic API key not configured. Please set it up in Cloud Settings.']);
    exit;
}

require_once __DIR__ . '/claude_integration.php';
require_once __DIR__ . '/sqlite_manager.php';

try {
    $claude = new ClaudeIntegration($apiKey);
    
    // Get system prompt from SQLite
    $sql = "SELECT prompt FROM system_prompts WHERE id = 1 ORDER BY created_at DESC LIMIT 1";
    $result = SQLiteManager::executeSQL($sql);
    
    if (!empty($result[0]['data'])) {
        $systemPrompt = $result[0]['data'][0]['prompt'];
        // Ensure commands are always included
        if (strpos($systemPrompt, '@file') === false) {
            $systemPrompt .= "\n\nCOMMANDS:\n";
            $systemPrompt .= "- @file path/to/file.py - Read file contents\n";
            $systemPrompt .= "- @read path/to/file.py - Read file contents (alias)\n";
            $systemPrompt .= "- @list path/to/directory - List directory contents\n";
            $systemPrompt .= "- @search pattern - Find files matching pattern\n";
            $systemPrompt .= "- @create path/to/file.py ```content``` - Create file\n";
            $systemPrompt .= "- @edit path/to/file.py ```content``` - Replace file content\n";
            $systemPrompt .= "- @append path/to/file.py ```content``` - Add to file\n";
            $systemPrompt .= "- @delete path/to/file.py - Delete file\n";
            $systemPrompt .= "- @agents - List all agents\n";
            $systemPrompt .= "- @update_agent ID role=\"Role\" goal=\"Goal\" - Update agent\n";
            $systemPrompt .= "- @crews - Show crew status\n";
            $systemPrompt .= "- @analyze_crew task_id - Analyze crew execution\n";
            $systemPrompt .= "- @logs [days] [role] - Show crew logs\n";
            $systemPrompt .= "- @optimize_agents - Analyze agent performance\n";
            $systemPrompt .= "- @docker [command] - Execute Docker commands\n";
            $systemPrompt .= "- @compose [command] - Execute Docker Compose commands\n";
            $systemPrompt .= "- @ps - Show running containers\n";
            $systemPrompt .= "- @exec [container] [command] - Execute command in container\n";
            $systemPrompt .= "- @inspect [container] - Get container details\n";
            $systemPrompt .= "- @container_logs [container] [lines] - Get container logs\n";
        }
    } else {
        // Initialize complete system prompt
        require_once __DIR__ . '/init_claude_prompt.php';
        // Re-fetch after initialization
        $result = SQLiteManager::executeSQL($sql);
        $systemPrompt = $result[0]['data'][0]['prompt'];
    }
    
    // Add command outputs to message for Claude to see
    if ($commandOutputs) {
        $message .= $commandOutputs;
    }
    
    $response = $claude->chatWithClaude($message, $systemPrompt, $selectedModel, $conversationHistory);
    
    // Don't process commands in Claude's response to avoid showing exec outputs
    
    echo json_encode([
        'success' => true,
        'response' => $response['message'],
        'tokens' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0),
        'cost' => 0.0,
        'model' => $response['model'] ?? $selectedModel
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Claude error: ' . $e->getMessage()]);
}

?>