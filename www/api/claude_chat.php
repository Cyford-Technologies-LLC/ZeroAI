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

// Initialize memory system with error handling
try {
    $memoryDir = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data';
    if (!is_dir($memoryDir)) mkdir($memoryDir, 0777, true);
    
    $dbPath = $memoryDir . '/claude_memory.db';
    $memoryPdo = new PDO("sqlite:$dbPath");
    
    // Create tables if they don't exist
    $memoryPdo->exec("CREATE TABLE IF NOT EXISTS sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, start_time DATETIME DEFAULT CURRENT_TIMESTAMP, end_time DATETIME, primary_model TEXT, message_count INTEGER DEFAULT 0, command_count INTEGER DEFAULT 0)");
    $memoryPdo->exec("CREATE TABLE IF NOT EXISTS chat_history (id INTEGER PRIMARY KEY AUTOINCREMENT, sender TEXT NOT NULL, message TEXT NOT NULL, model_used TEXT NOT NULL, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, session_id INTEGER)");
    $memoryPdo->exec("CREATE TABLE IF NOT EXISTS command_history (id INTEGER PRIMARY KEY AUTOINCREMENT, command TEXT NOT NULL, output TEXT, status TEXT NOT NULL, model_used TEXT NOT NULL, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, session_id INTEGER)");
    
    // Start session
    $memoryPdo->prepare("INSERT INTO sessions (start_time, primary_model) VALUES (datetime('now'), ?)") ->execute([$selectedModel]);
    $sessionId = $memoryPdo->lastInsertId();
} catch (Exception $e) {
    // Memory system failed, continue without it
    $memoryPdo = null;
    $sessionId = null;
}

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
            $systemPrompt .= "- @memory chat 30min - View recent chat history\n";
            $systemPrompt .= "- @memory commands 5min - View recent command history\n";
            $systemPrompt .= "- @memory search \"keyword\" - Search memory for keyword\n";
        }
    } else {
        // Initialize complete system prompt
        require_once __DIR__ . '/init_claude_prompt.php';
        // Re-fetch after initialization
        $result = SQLiteManager::executeSQL($sql);
        $systemPrompt = $result[0]['data'][0]['prompt'];
    }
    
    // Save user message to memory
    if ($memoryPdo && $sessionId) {
        try {
            $memoryPdo->prepare("INSERT INTO chat_history (sender, message, model_used, session_id) VALUES (?, ?, ?, ?)")
                     ->execute(['User', $originalMessage, $selectedModel, $sessionId]);
        } catch (Exception $e) {}
    }
    
    // Add command outputs to message for Claude to see
    if ($commandOutputs) {
        $message .= $commandOutputs;
    }
    
    $response = $claude->chatWithClaude($message, $systemPrompt, $selectedModel, $conversationHistory);
    
    // Execute Claude's commands but hide them from chat
    $claudeResponse = $response['message'];
    $processedResponse = $claudeResponse;
    
    processFileCommands($processedResponse);
    processClaudeCommands($processedResponse);
    
    // Save Claude's response to memory
    if ($memoryPdo && $sessionId) {
        try {
            $memoryPdo->prepare("INSERT INTO chat_history (sender, message, model_used, session_id) VALUES (?, ?, ?, ?)")
                     ->execute(['Claude', $claudeResponse, $selectedModel, $sessionId]);
        } catch (Exception $e) {}
    }
    
    // Show @commands and preserve hyperlinks
    $lines = explode("\n", $claudeResponse);
    $result = [];
    
    foreach ($lines as $line) {
        if (preg_match('/^\@(\w+)\s+(.*)/', $line, $matches)) {
            $result[] = "[@{$matches[1]}: {$matches[2]}]";
        } elseif (preg_match('/\[View.*File\]/', $line) || preg_match('/🧠 Memory:/', $line)) {
            $result[] = $line; // Keep memory results and hyperlinks
        } elseif (!preg_match('/^(File content|Search results|Current Agents|💻 Exec|🐳 Docker)/', $line)) {
            $result[] = $line; // Keep non-command output
        }
    }
    
    $response['message'] = implode("\n", $result);
    
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