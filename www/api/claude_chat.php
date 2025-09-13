<?php
// Set timezone to EST
date_default_timezone_set('America/New_York');

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
        if (str_contains($line, '=') && !str_starts_with($line, '#')) {
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
// Check if debug mode is enabled via localStorage (passed from frontend)
$debugMode = $input['debug'] ?? false;

// Make mode available globally for permission checks
$GLOBALS['claudeMode'] = $claudeMode;
$GLOBALS['debugMode'] = $debugMode;

if ($debugMode) {
    error_log("DEBUG: Starting claude_chat.php - Mode: $claudeMode");
}

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Message required']);
    exit;
}

// All modes get read-only commands, autonomous gets write commands too
if ($autonomousMode) {
    $message = "[AUTONOMOUS MODE] You have FULL access including write commands: @create, @edit, @append, @delete. " . $message;
} elseif ($claudeMode === 'hybrid') {
    $message = "[HYBRID MODE] You have read-only access: @exec, @file, @list, @memory, @agents, @update_agent, @search, @crews, @logs. " . $message;
} elseif ($claudeMode === 'chat') {
    $message = "[CHAT MODE] You have read-only access: @exec, @file, @list, @memory, @agents, @update_agent, @search, @crews, @logs. " . $message;
}

// Auto-scan for autonomous mode only
if ($autonomousMode && !preg_match('/@(file|list|search|create|edit|append|delete)/u', $message)) {
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

// Capture command outputs separately from chat
require_once __DIR__ . '/file_commands.php';
require_once __DIR__ . '/claude_commands.php';

$originalMessage = $message;
$commandOutputs = '';

// Process commands silently - capture outputs for Claude context only
$tempMessage = $message;
$GLOBALS['executedCommands'] = []; // Global to capture commands
if ($debugMode) {
    error_log("DEBUG: Processing user commands - Message length: " . strlen($tempMessage));
}
processFileCommands($tempMessage);
processClaudeCommands($tempMessage);
if ($debugMode) {
    error_log("DEBUG: After user command processing - Message length: " . strlen($tempMessage));
}

// Extract ALL command outputs (file + exec)
if (strlen($tempMessage) > strlen($originalMessage)) {
    $commandOutputs = substr($tempMessage, strlen($originalMessage));
}

// Keep original message clean for chat
$message = $originalMessage;

// All messages go to Claude for processing

// Only use Claude API for complex queries or when explicitly needed
$envContent = file_get_contents('/app/.env');
preg_match('/ANTHROPIC_API_KEY=(.+)/u', $envContent, $matches);
$apiKey = isset($matches[1]) ? trim($matches[1]) : '';

if (!$apiKey) {
    echo json_encode(['success' => false, 'error' => 'Anthropic API key not configured. Please set it up in Cloud Settings.']);
    exit;
}

require_once __DIR__ . '/claude_integration.php';
require_once __DIR__ . '/sqlite_manager.php';

// Initialize system prompt early to prevent undefined variable errors
$systemPrompt = '';

// Initialize memory system with error handling
try {
    $memoryDir = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data';
    if (!mkdir($memoryDir, 0777, true) && !is_dir($memoryDir)) {
        throw new Exception('Failed to create directory: ' . $memoryDir);
    }
    
    $dbPath = $memoryDir . '/claude_memory.db';
    $memoryPdo = new PDO("sqlite:$dbPath");
    
    // Create tables if they don't exist
    $memoryPdo->exec("CREATE TABLE IF NOT EXISTS sessions (id INTEGER PRIMARY KEY AUTO_INCREMENT, start_time DATETIME DEFAULT CURRENT_TIMESTAMP, end_time DATETIME, primary_model TEXT, message_count INTEGER DEFAULT 0, command_count INTEGER DEFAULT 0)");
    $memoryPdo->exec("CREATE TABLE IF NOT EXISTS chat_history (id INTEGER PRIMARY KEY AUTO_INCREMENT, sender TEXT NOT NULL, message TEXT NOT NULL, model_used TEXT NOT NULL, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, session_id INTEGER)");
    $memoryPdo->exec("CREATE TABLE IF NOT EXISTS command_history (id INTEGER PRIMARY KEY AUTO_INCREMENT, command TEXT NOT NULL, output TEXT, status TEXT NOT NULL, model_used TEXT NOT NULL, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, session_id INTEGER)");
    $memoryPdo->exec("CREATE TABLE IF NOT EXISTS claude_config (id INTEGER PRIMARY KEY, system_prompt TEXT, goals TEXT, personality TEXT, capabilities TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $memoryPdo->exec("CREATE TABLE IF NOT EXISTS claude_sessions (id INTEGER PRIMARY KEY AUTO_INCREMENT, model_used TEXT, mode TEXT, start_time DATETIME DEFAULT CURRENT_TIMESTAMP, end_time DATETIME, message_count INTEGER DEFAULT 0, command_count INTEGER DEFAULT 0)");
    $memoryPdo->exec("CREATE TABLE IF NOT EXISTS system_prompts (id INTEGER PRIMARY KEY AUTO_INCREMENT, prompt TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $memoryPdo->exec("CREATE TABLE IF NOT EXISTS claude_settings (id INTEGER PRIMARY KEY, setting_name TEXT UNIQUE, setting_value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $memoryPdo->exec("CREATE TABLE IF NOT EXISTS claude_prompts (id INTEGER PRIMARY KEY AUTO_INCREMENT, prompt TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    
    // Start session
    $now = date('Y-m-d H:i:s');
    $memoryPdo->prepare("INSERT INTO sessions (start_time, primary_model) VALUES (?, ?)")->execute([$now, $selectedModel]);
    $sessionId = $memoryPdo->lastInsertId();
    
    // Save session info to claude_sessions
    $memoryPdo->prepare("INSERT INTO claude_sessions (model_used, mode, start_time) VALUES (?, ?, ?)")
             ->execute([$selectedModel, $claudeMode, $now]);
    
    // Migrate prompt from old database to new memory database if not exists
    $stmt = $memoryPdo->prepare("SELECT COUNT(*) FROM system_prompts");
    $stmt->execute();
    $promptCount = $stmt->fetchColumn();
    
    if ($promptCount == 0) {
        // Copy from old database
        try {
            $sql = "SELECT prompt FROM system_prompts WHERE id = 1 ORDER BY created_at DESC LIMIT 1";
            $result = SQLiteManager::executeSQL($sql);
            if (!empty($result[0]['data'])) {
                $oldPrompt = $result[0]['data'][0]['prompt'];
                $memoryPdo->prepare("INSERT INTO system_prompts (prompt) VALUES (?)")->execute([$oldPrompt]);
            }
        } catch (Exception $e) {}
    }
    
    // Save/update system prompt to claude_config
    $memoryPdo->prepare("DELETE FROM claude_config WHERE id = 1")->execute();
    $memoryPdo->prepare("INSERT INTO claude_config (id, system_prompt, updated_at) VALUES (1, ?, ?)")
             ->execute([$systemPrompt, date('Y-m-d H:i:s')]);
} catch (Exception $e) {
    // Memory system failed, continue without it
    $memoryPdo = null;
    $sessionId = null;
}

try {
    $claude = new ClaudeIntegration($apiKey);
    
    // Get system prompt from claude_prompts table
    if ($memoryPdo) {
        try {
            $stmt = $memoryPdo->prepare("SELECT prompt FROM claude_prompts ORDER BY created_at DESC LIMIT 1");
            $stmt->execute();
            $promptResult = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($promptResult) {
                $systemPrompt = $promptResult['prompt'];
            }
        } catch (Exception $e) {}
    }
    
    if ($systemPrompt) {
        // Ensure commands are always included
        if (!str_contains($systemPrompt, '@file')) {
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
            $systemPrompt .= "- @memory config - View your system prompt and configuration\n";
            $systemPrompt .= "- @memory sessions - View your recent session history\n";
            $systemPrompt .= "- @memory search \"keyword\" - Search memory for keyword\n";
        }
    } else {
        // Use default prompt as fallback
        try {
            $sql = "SELECT prompt FROM default_prompts WHERE id = 1";
            $result = SQLiteManager::executeSQL($sql);
            if (!empty($result[0]['data'])) {
                $systemPrompt = $result[0]['data'][0]['prompt'];
            } else {
                // Create default if none exists
                require_once __DIR__ . '/init_claude_prompt.php';
                $sql = "SELECT prompt FROM default_prompts WHERE id = 1";
                $result = SQLiteManager::executeSQL($sql);
                if (!empty($result[0]['data'])) {
                    $systemPrompt = $result[0]['data'][0]['prompt'];
                }
            }
        } catch (Exception $e) {
            $systemPrompt = "You are Claude, integrated into ZeroAI.";
        }
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
    
    // Execute Claude's commands and capture outputs
    $claudeResponse = $response['message'];
    $processedResponse = $claudeResponse;
    
    // Process Claude's individual commands
    if (!isset($GLOBALS['executedCommands'])) $GLOBALS['executedCommands'] = [];
    if ($debugMode) error_log("DEBUG: Processing Claude's commands - Response length: " . strlen($processedResponse));
    
    // Store original response length to detect command outputs
    $originalResponseLength = strlen($processedResponse);
    processFileCommands($processedResponse);
    processClaudeCommands($processedResponse);
    
    // Extract Claude's command outputs
    $claudeCommandOutputs = '';
    if (strlen($processedResponse) > $originalResponseLength) {
        $claudeCommandOutputs = substr($processedResponse, $originalResponseLength);
    }
    
    if ($debugMode) error_log("DEBUG: After Claude command processing - Response length: " . strlen($processedResponse));
    
    // Save Claude's response to memory
    if ($memoryPdo && $sessionId) {
        try {
            $memoryPdo->prepare("INSERT INTO chat_history (sender, message, model_used, session_id) VALUES (?, ?, ?, ?)")
                     ->execute(['Claude', $claudeResponse, $selectedModel, $sessionId]);
            
            // Save executed commands with their outputs
            if (isset($GLOBALS['executedCommands']) && !empty($GLOBALS['executedCommands'])) {
                foreach ($GLOBALS['executedCommands'] as $cmdData) {
                    $memoryPdo->prepare("INSERT INTO command_history (command, output, status, model_used, session_id) VALUES (?, ?, ?, ?, ?)")
                             ->execute([$cmdData['command'], $cmdData['output'], 'success', $selectedModel, $sessionId]);
                }
            }
        } catch (Exception $e) {
            error_log("Command save error: " . $e->getMessage());
        }
    }
    
    // Include command outputs in response for Claude to see
    if ($claudeCommandOutputs) {
        $claudeResponse .= $claudeCommandOutputs;
    }
    
    // Show @commands and preserve hyperlinks
    if ($debugMode) error_log("DEBUG: Filtering response - Original length: " . strlen($claudeResponse) . ", Processed length: " . strlen($processedResponse));
    $lines = explode("\n", $claudeResponse);
    $result = [];
    
    foreach ($lines as $line) {
        if (preg_match('/^\@(\w+)\s+(.*)/u', $line, $matches)) {
            $result[] = "[@{$matches[1]}: {$matches[2]}]";
        } elseif (preg_match('/\[View.*File\]/u', $line) || preg_match('/🧠 Memory:/u', $line)) {
            $result[] = $line; // Keep memory results and hyperlinks
        } elseif (preg_match('/^(File content|Search results|Current Agents|💻 Exec|🐳 Docker|Directory listing|\[SUCCESS\]|\[ERROR\]|\[RESTRICTED\]|🧠 Memory:|\[|\{)/u', $line)) {
            $result[] = $line; // Keep command outputs
        } else {
            $result[] = $line; // Keep other content
        }
    }
    
    $response['message'] = implode("\n", $result);
    

    
    // Log response length for debugging
    error_log("Claude response length: " . strlen($response['message']));
    error_log("Filtered response length: " . strlen($response['message']));
    
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