<?php
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
$autonomousMode = $input['autonomous'] ?? false;
$conversationHistory = $input['history'] ?? [];

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Message required']);
    exit;
}

// In autonomous mode, Claude can proactively analyze and modify files
if ($autonomousMode) {
    // Add autonomous context to message
    $message = "[AUTONOMOUS MODE ENABLED] You have full access to analyze, create, edit, and optimize files proactively. " . $message;
    
    // Auto-scan common directories if no specific commands given
    if (!preg_match('/\@(file|list|search|create|edit|append|delete)/', $message)) {
        $autoScan = "\n\nAuto-scanning key directories:\n";
        
        // Scan src directory
        if (is_dir('/app/src')) {
            $srcFiles = shell_exec('find /app/src -name "*.py" | head -10');
            $autoScan .= "\nSrc files:\n" . ($srcFiles ?: "No Python files found");
        }
        
        // Scan config directory
        if (is_dir('/app/config')) {
            $configFiles = scandir('/app/config');
            $autoScan .= "\nConfig files: " . implode(", ", array_filter($configFiles, function($f) { return $f !== '.' && $f !== '..'; }));
        }
        
        $message .= $autoScan;
    }
}

// Process file commands
if (preg_match('/\@file\s+(.+)/', $message, $matches)) {
    $filePath = trim($matches[1]);
    $cleanPath = ltrim($filePath, '/');
    if (strpos($cleanPath, 'app/') === 0) {
        $cleanPath = substr($cleanPath, 4);
    }
    $fullPath = '/app/' . $cleanPath;
    if (file_exists($fullPath)) {
        $fileContent = file_get_contents($fullPath);
        $message .= "\n\nFile content of " . $filePath . ":\n" . $fileContent;
    } else {
        $message .= "\n\nFile not found: " . $filePath;
    }
}

if (preg_match('/\@list\s+(.+)/', $message, $matches)) {
    $dirPath = trim($matches[1]);
    // Clean up path - remove leading /app/ if present to avoid double path
    $cleanPath = ltrim($dirPath, '/');
    if (strpos($cleanPath, 'app/') === 0) {
        $cleanPath = substr($cleanPath, 4);
    }
    $fullDirPath = '/app/' . $cleanPath;
    if (is_dir($fullDirPath)) {
        $files = scandir($fullDirPath);
        $listing = "Directory listing for " . $dirPath . ":\n" . implode("\n", array_filter($files, function($f) { return $f !== '.' && $f !== '..'; }));
        $message .= "\n\n" . $listing;
    } else {
        $message .= "\n\nDirectory not found: " . $dirPath . " (searched at: " . $fullDirPath . ")";
    }
}

if (preg_match('/\@search\s+(.+)/', $message, $matches)) {
    $pattern = trim($matches[1]);
    $output = shell_exec("find /app -name '*" . escapeshellarg($pattern) . "*' 2>/dev/null | head -20");
    $message .= "\n\nSearch results for '" . $pattern . "':\n" . ($output ?: "No files found");
}

// Handle agent management commands
if (preg_match('/\@agents/', $message)) {
    require_once __DIR__ . '/agent_db.php';
    $agentDB = new AgentDB();
    $agents = $agentDB->getAllAgents();
    $agentList = "Current Agents:\n";
    foreach ($agents as $agent) {
        $agentList .= "- ID: {$agent['id']}, Name: {$agent['name']}, Role: {$agent['role']}, Status: {$agent['status']}\n";
    }
    $message .= "\n\n" . $agentList;
}

// Handle crew status commands
if (preg_match('/\@crews/', $message)) {
    require_once __DIR__ . '/crew_context.php';
    $crewContext = new CrewContextManager();
    $runningCrews = $crewContext->getRunningCrews();
    $recentCrews = $crewContext->getRecentCrewExecutions(5);
    
    $crewInfo = "Crew Status:\n\n";
    
    if (!empty($runningCrews)) {
        $crewInfo .= "Currently Running Crews:\n";
        foreach ($runningCrews as $crew) {
            $crewInfo .= "- Task ID: {$crew['task_id']}, Project: {$crew['project_id']}, Prompt: {$crew['prompt']}\n";
        }
        $crewInfo .= "\n";
    }
    
    if (!empty($recentCrews)) {
        $crewInfo .= "Recent Crew Executions:\n";
        foreach ($recentCrews as $crew) {
            $crewInfo .= "- Task ID: {$crew['task_id']}, Status: {$crew['status']}, Project: {$crew['project_id']}\n";
        }
    }
    
    if (empty($runningCrews) && empty($recentCrews)) {
        $crewInfo .= "No crew executions found.\n";
    }
    
    $message .= "\n\n" . $crewInfo;
}

// Handle crew analysis command
if (preg_match('/\@analyze_crew\s+(.+)/', $message, $matches)) {
    $taskId = trim($matches[1]);
    require_once __DIR__ . '/crew_context.php';
    $crewContext = new CrewContextManager();
    $execution = $crewContext->getCrewExecution($taskId);
    
    if ($execution) {
        $message .= "\n\nCrew Execution Details for Task {$taskId}:\n" . json_encode($execution, JSON_PRETTY_PRINT);
    } else {
        $message .= "\n\nCrew execution not found for Task ID: {$taskId}";
    }
}

// Test write permissions first
$testWrite = file_put_contents('/app/test_write_permission.tmp', 'test');
if ($testWrite === false) {
    $message .= "\n\n[ERROR] PHP cannot write to /app directory - permission denied";
} else {
    unlink('/app/test_write_permission.tmp');
    $message .= "\n\n[OK] PHP has write permissions to /app directory";
}

// Handle file creation command - support both formats
if (preg_match('/\@create\s+(.+?)(?:\s+```([\s\S]*?)```)?/', $message, $matches)) {
    $filePath = trim($matches[1]);
    $fileContent = isset($matches[2]) ? trim($matches[2]) : "# File created by Claude\nprint('Hello from Claude')\n";
    
    // Clean up path - remove leading /app/ if present to avoid double path
    $cleanPath = ltrim($filePath, '/');
    if (strpos($cleanPath, 'app/') === 0) {
        $cleanPath = substr($cleanPath, 4);
    }
    $fullPath = '/app/' . $cleanPath;
    $dir = dirname($fullPath);
    
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Debug info
    $message .= "\n\n[DEBUG] Original path: " . $filePath;
    $message .= "\n[DEBUG] Clean path: " . $cleanPath;
    $message .= "\n[DEBUG] Full path: " . $fullPath;
    $message .= "\n[DEBUG] Directory exists: " . (is_dir($dir) ? 'YES' : 'NO');
    $message .= "\n[DEBUG] Directory writable: " . (is_writable($dir) ? 'YES' : 'NO');
    
    $result = file_put_contents($fullPath, $fileContent);
    if ($result !== false) {
        $message .= "\n\nFile created successfully: " . $filePath . " (" . $result . " bytes written to " . $fullPath . ")";
        $message .= "\n[DEBUG] File exists after creation: " . (file_exists($fullPath) ? 'YES' : 'NO');
    } else {
        $error = error_get_last();
        $message .= "\n\nFailed to create file: " . $filePath . " at " . $fullPath . " - Error: " . ($error['message'] ?? 'Unknown error');
    }
}

// Handle file editing command - support both formats
if (preg_match('/\@edit\s+(.+?)(?:\s+```([\s\S]*?)```)?/', $message, $matches)) {
    $filePath = trim($matches[1]);
    $newContent = isset($matches[2]) ? trim($matches[2]) : "# File edited by Claude\nprint('Updated by Claude')\n";
    
    // Clean up path - remove leading /app/ if present to avoid double path
    $cleanPath = ltrim($filePath, '/');
    if (strpos($cleanPath, 'app/') === 0) {
        $cleanPath = substr($cleanPath, 4);
    }
    $fullPath = '/app/' . $cleanPath;
    
    if (file_exists($fullPath)) {
        $result = file_put_contents($fullPath, $newContent);
        if ($result !== false) {
            $message .= "\n\nFile updated successfully: " . $filePath . " (" . $result . " bytes written to " . $fullPath . ")";
        } else {
            $error = error_get_last();
            $message .= "\n\nFailed to update file: " . $filePath . " - Error: " . ($error['message'] ?? 'Unknown error');
        }
    } else {
        $message .= "\n\nFile not found: " . $filePath . " (searched at " . $fullPath . ")";
    }
}

// Handle file append command - support both formats
if (preg_match('/\@append\s+(.+?)(?:\s+```([\s\S]*?)```)?/', $message, $matches)) {
    $filePath = trim($matches[1]);
    $appendContent = isset($matches[2]) ? trim($matches[2]) : "\n# Appended by Claude\nprint('Added by Claude')\n";
    
    // Clean up path - remove leading /app/ if present to avoid double path
    $cleanPath = ltrim($filePath, '/');
    if (strpos($cleanPath, 'app/') === 0) {
        $cleanPath = substr($cleanPath, 4);
    }
    $fullPath = '/app/' . $cleanPath;
    
    if (file_exists($fullPath)) {
        if (file_put_contents($fullPath, "\n" . $appendContent, FILE_APPEND) !== false) {
            $message .= "\n\nContent appended to file: " . $filePath;
        } else {
            $message .= "\n\nFailed to append to file: " . $filePath;
        }
    } else {
        $message .= "\n\nFile not found: " . $filePath;
    }
}

// Handle delete file command
if (preg_match('/\@delete\s+(.+)/', $message, $matches)) {
    $filePath = trim($matches[1]);
    // Clean up path - remove leading /app/ if present to avoid double path
    $cleanPath = ltrim($filePath, '/');
    if (strpos($cleanPath, 'app/') === 0) {
        $cleanPath = substr($cleanPath, 4);
    }
    $fullPath = '/app/' . $cleanPath;
    
    if (file_exists($fullPath)) {
        if (unlink($fullPath)) {
            $message .= "\n\nFile deleted successfully: " . $filePath;
        } else {
            $message .= "\n\nFailed to delete file: " . $filePath;
        }
    } else {
        $message .= "\n\nFile not found: " . $filePath;
    }
}

// Handle crew logs command
if (preg_match('/\@logs(?:\s+(\d+))?(?:\s+(\w+))?/', $message, $matches)) {
    $days = isset($matches[1]) ? (int)$matches[1] : 7;
    $agentRole = isset($matches[2]) ? $matches[2] : null;
    
    $logDir = '/app/logs/crews';
    if (!is_dir($logDir)) {
        $message .= "\n\nNo crew logs found. Logs directory does not exist.";
    } else {
        $logFiles = glob($logDir . '/crew_conversations_*.jsonl');
        if (empty($logFiles)) {
            $message .= "\n\nNo crew conversation logs found.";
        } else {
            $recentLogs = [];
            $currentDate = new DateTime();
            
            for ($i = 0; $i < $days; $i++) {
                $date = clone $currentDate;
                $date->sub(new DateInterval('P' . $i . 'D'));
                $dateStr = $date->format('Y-m-d');
                $logFile = $logDir . '/crew_conversations_' . $dateStr . '.jsonl';
                
                if (file_exists($logFile)) {
                    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        $entry = json_decode($line, true);
                        if ($entry && (!$agentRole || $entry['agent_role'] === $agentRole)) {
                            $recentLogs[] = $entry;
                        }
                    }
                }
            }
            
            if (empty($recentLogs)) {
                $message .= "\n\nNo crew logs found for the specified criteria.";
            } else {
                usort($recentLogs, function($a, $b) {
                    return strcmp($b['timestamp'], $a['timestamp']);
                });
                
                $message .= "\n\nRecent Crew Conversation Logs (" . count($recentLogs) . " entries):\n";
                foreach (array_slice($recentLogs, 0, 20) as $log) {
                    $message .= "\n[{$log['timestamp']}] {$log['agent_role']}: {$log['prompt']}\n";
                    $message .= "Response: " . substr($log['response'], 0, 200) . (strlen($log['response']) > 200 ? '...' : '') . "\n";
                }
            }
        }
    }
}

// Handle agent optimization based on logs
if (preg_match('/\@optimize_agents/', $message)) {
    $logDir = '/app/logs/crews';
    if (is_dir($logDir)) {
        $logFiles = glob($logDir . '/crew_conversations_*.jsonl');
        $agentStats = [];
        
        foreach ($logFiles as $logFile) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry) {
                    $role = $entry['agent_role'];
                    if (!isset($agentStats[$role])) {
                        $agentStats[$role] = ['count' => 0, 'prompts' => [], 'avg_response_len' => 0, 'total_response_len' => 0];
                    }
                    $agentStats[$role]['count']++;
                    $agentStats[$role]['prompts'][] = $entry['prompt'];
                    $agentStats[$role]['total_response_len'] += strlen($entry['response']);
                }
            }
        }
        
        foreach ($agentStats as $role => &$stats) {
            $stats['avg_response_len'] = $stats['count'] > 0 ? $stats['total_response_len'] / $stats['count'] : 0;
        }
        
        $message .= "\n\nAgent Performance Analysis:\n" . json_encode($agentStats, JSON_PRETTY_PRINT);
    } else {
        $message .= "\n\nNo crew logs available for agent optimization.";
    }
}

if (preg_match('/\@update_agent\s+(\d+)\s+(.+)/', $message, $matches)) {
    $agentId = trim($matches[1]);
    $updates = trim($matches[2]);
    
    require_once __DIR__ . '/agent_db.php';
    $agentDB = new AgentDB();
    
    // Parse update parameters (role="new role" goal="new goal" etc.)
    $updateData = [];
    if (preg_match('/role="([^"]+)"/', $updates, $roleMatch)) {
        $updateData['role'] = $roleMatch[1];
    }
    if (preg_match('/goal="([^"]+)"/', $updates, $goalMatch)) {
        $updateData['goal'] = $goalMatch[1];
    }
    if (preg_match('/backstory="([^"]+)"/', $updates, $backstoryMatch)) {
        $updateData['backstory'] = $backstoryMatch[1];
    }
    if (preg_match('/status="([^"]+)"/', $updates, $statusMatch)) {
        $updateData['status'] = $statusMatch[1];
    }
    
    if (!empty($updateData)) {
        $agentDB->updateAgent($agentId, $updateData);
        $message .= "\n\nAgent {$agentId} updated successfully with: " . json_encode($updateData);
    } else {
        $message .= "\n\nNo valid update parameters found. Use format: role=\"new role\" goal=\"new goal\"";
    }
}

// Read API key from .env file
$envContent = file_get_contents('/app/.env');
preg_match('/ANTHROPIC_API_KEY=(.+)/', $envContent, $matches);
$apiKey = isset($matches[1]) ? trim($matches[1]) : '';

if (!$apiKey) {
    echo json_encode(['success' => false, 'error' => 'Anthropic API key not configured. Please set it up in Cloud Settings.']);
    exit;
}

require_once __DIR__ . '/claude_integration.php';

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
    $systemPrompt .= "- You can monitor crew executions using @crews and @analyze_crew commands\n";
    $systemPrompt .= "- You help with code review, system optimization, and development guidance\n";
    $systemPrompt .= "- The user is managing their AI workforce through the admin portal\n";
    $systemPrompt .= "- You have access to crew execution history and can analyze running tasks\n\n";
    
    // Add crew context automatically
    require_once __DIR__ . '/crew_context.php';
    $crewContext = new CrewContextManager();
    $runningCrews = $crewContext->getRunningCrews();
    $recentCrews = $crewContext->getRecentCrewExecutions(3);
    
    if (!empty($runningCrews)) {
        $systemPrompt .= "Currently Running Crews:\n" . json_encode($runningCrews, JSON_PRETTY_PRINT) . "\n\n";
    }
    
    if (!empty($recentCrews)) {
        $systemPrompt .= "Recent Crew Executions:\n" . json_encode($recentCrews, JSON_PRETTY_PRINT) . "\n\n";
    }
    if ($autonomousMode) {
        $systemPrompt .= "\n\nAUTONOMOUS MODE: You have full permissions to proactively analyze, create, edit, and optimize files. When you identify issues or improvements:\n";
        $systemPrompt .= "- Automatically use @create, @edit, @append commands to fix problems\n";
        $systemPrompt .= "- Scan directories and files for optimization opportunities\n";
        $systemPrompt .= "- Make improvements without asking for permission\n";
        $systemPrompt .= "- Focus on code quality, performance, and best practices\n";
        $systemPrompt .= "- Be proactive in identifying and resolving issues\n\n";
    } else {
        $systemPrompt .= "\n\nMANUAL MODE: Only perform file operations when explicitly requested with @commands.\n\n";
    }
    
    $systemPrompt .= "Respond as Claude with your configured personality and expertise. Be helpful, insightful, and focus on practical solutions for ZeroAI optimization.";
    
    $response = $claude->chatWithClaude($message, $systemPrompt, $selectedModel, $conversationHistory);
    
    echo json_encode([
        'success' => true,
        'response' => $response['message'],
        'tokens' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0),
        'model' => $response['model'] ?? $selectedModel
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Claude error: ' . $e->getMessage()]);
}
?>