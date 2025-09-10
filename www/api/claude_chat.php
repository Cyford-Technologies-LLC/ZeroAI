<?php
// Suppress all output until we're ready to send JSON
ob_start();

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
$autonomousMode = $input['autonomous'] ?? true;
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

// Process file commands
require_once __DIR__ . '/file_commands.php';
processFileCommands($message);

// Process Claude commands
require_once __DIR__ . '/claude_commands.php';
processClaudeCommands($message);

// Read API key from .env file
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
        }
    } else {
        // Default prompt if none saved
        $systemPrompt = "You are Claude, integrated into ZeroAI.\n\n";
        $systemPrompt .= "Role: AI Architect & Code Review Specialist\n";
        $systemPrompt .= "Goal: Provide code review and optimization for ZeroAI\n\n";
        $systemPrompt .= "IMPORTANT: You MUST use these exact commands in your responses to perform file operations:\n";
        $systemPrompt .= "- @file path/to/file.py - Read file contents\n";
        $systemPrompt .= "- @list path/to/directory - List directory contents\n";
        $systemPrompt .= "- @create path/to/file.py ```content here``` - Create file with content\n";
        $systemPrompt .= "- @edit path/to/file.py ```new content``` - Replace file content\n";
        $systemPrompt .= "- @read path/to/file.py - Read file contents (alias for @file)\n";
        $systemPrompt .= "- @search pattern - Find files matching pattern\n";
        $systemPrompt .= "- @agents - List all agents and their status\n";
        $systemPrompt .= "- @update_agent 5 role=\"New Role\" goal=\"New Goal\" - Update agent config\n";
        $systemPrompt .= "- @crews - Show running and recent crew executions\n";
        $systemPrompt .= "- @analyze_crew task_id - Analyze specific crew execution\n";
        $systemPrompt .= "- @append path/to/file.py ```content``` - Add content to file\n";
        $systemPrompt .= "- @delete path/to/file.py - Delete file\n";
        $systemPrompt .= "- @logs [days] [agent_role] - Show crew conversation logs\n";
        $systemPrompt .= "- @optimize_agents - Analyze and suggest agent improvements\n";
        if ($autonomousMode) {
            $systemPrompt .= "\n\nAUTONOMOUS MODE: You have full permissions to proactively analyze, create, edit, and optimize files.\n";
        }
    }
    
    $response = $claude->chatWithClaude($message, $systemPrompt, $selectedModel, $conversationHistory);
    
    // Process Claude's response commands
    $claudeResponse = $response['message'];
    processClaudeResponseCommands($claudeResponse, $response['message']);
    
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