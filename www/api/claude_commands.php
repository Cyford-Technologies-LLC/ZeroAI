<?php
function processClaudeCommands(&$message) {
    // @read command (alias for @file)
    if (preg_match('/\@read\s+(.+)/', $message, $matches)) {
        $filePath = trim($matches[1]);
        $cleanPath = ltrim($filePath, '/');
        if (strpos($cleanPath, 'app/') === 0) $cleanPath = substr($cleanPath, 4);
        $fullPath = '/app/' . $cleanPath;
        if (file_exists($fullPath)) {
            $fileContent = file_get_contents($fullPath);
            $message .= "\n\nFile content of " . $filePath . ":\n" . $fileContent;
        } else {
            $message .= "\n\nFile not found: " . $filePath;
        }
    }

    // @search command
    if (preg_match('/\@search\s+(.+)/', $message, $matches)) {
        $pattern = trim($matches[1]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @search: $pattern\n", FILE_APPEND);
        $output = shell_exec("find /app -name '*" . escapeshellarg($pattern) . "*' 2>/dev/null | head -20");
        $message .= "\n\nSearch results for '" . $pattern . "':\n" . ($output ?: "No files found");
    }

    // @agents command
    if (preg_match('/\@agents/', $message)) {
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @agents\n", FILE_APPEND);
        require_once __DIR__ . '/agent_db.php';
        $agentDB = new AgentDB();
        $agents = $agentDB->getAllAgents();
        $agentList = "Current Agents:\n";
        foreach ($agents as $agent) {
            $agentList .= "- ID: {$agent['id']}, Name: {$agent['name']}, Role: {$agent['role']}, Status: {$agent['status']}\n";
        }
        $message .= "\n\n" . $agentList;
    }

    // @update_agent command
    if (preg_match('/\@update_agent\s+(\d+)\s+(.+)/', $message, $matches)) {
        $agentId = trim($matches[1]);
        $updates = trim($matches[2]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @update_agent: $agentId $updates\n", FILE_APPEND);
        require_once __DIR__ . '/agent_db.php';
        $agentDB = new AgentDB();
        $updateData = [];
        if (preg_match('/role="([^"]+)"/', $updates, $roleMatch)) $updateData['role'] = $roleMatch[1];
        if (preg_match('/goal="([^"]+)"/', $updates, $goalMatch)) $updateData['goal'] = $goalMatch[1];
        if (preg_match('/backstory="([^"]+)"/', $updates, $backstoryMatch)) $updateData['backstory'] = $backstoryMatch[1];
        if (preg_match('/status="([^"]+)"/', $updates, $statusMatch)) $updateData['status'] = $statusMatch[1];
        if (!empty($updateData)) {
            $agentDB->updateAgent($agentId, $updateData);
            $message .= "\n\nAgent {$agentId} updated: " . json_encode($updateData);
        }
    }

    // @crews command
    if (preg_match('/\@crews/', $message)) {
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @crews\n", FILE_APPEND);
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
        }
        if (!empty($recentCrews)) {
            $crewInfo .= "Recent Crew Executions:\n";
            foreach ($recentCrews as $crew) {
                $crewInfo .= "- Task ID: {$crew['task_id']}, Status: {$crew['status']}, Project: {$crew['project_id']}\n";
            }
        }
        $message .= "\n\n" . $crewInfo;
    }

    // @analyze_crew command
    if (preg_match('/\@analyze_crew\s+(.+)/', $message, $matches)) {
        $taskId = trim($matches[1]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @analyze_crew: $taskId\n", FILE_APPEND);
        require_once __DIR__ . '/crew_context.php';
        $crewContext = new CrewContextManager();
        $execution = $crewContext->getCrewExecution($taskId);
        if ($execution) {
            $message .= "\n\nCrew Execution Details for {$taskId}:\n" . json_encode($execution, JSON_PRETTY_PRINT);
        } else {
            $message .= "\n\nCrew execution not found: {$taskId}";
        }
    }

    // @logs command
    if (preg_match('/\@logs(?:\s+(\d+))?(?:\s+(\w+))?/', $message, $matches)) {
        $days = isset($matches[1]) ? (int)$matches[1] : 7;
        $agentRole = isset($matches[2]) ? $matches[2] : null;
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @logs: $days days" . ($agentRole ? " role=$agentRole" : "") . "\n", FILE_APPEND);
        $logDir = '/app/logs/crews';
        if (is_dir($logDir)) {
            $logFiles = glob($logDir . '/crew_conversations_*.jsonl');
            $recentLogs = [];
            $currentDate = new DateTime();
            for ($i = 0; $i < $days; $i++) {
                $date = clone $currentDate;
                $date->sub(new DateInterval('P' . $i . 'D'));
                $logFile = $logDir . '/crew_conversations_' . $date->format('Y-m-d') . '.jsonl';
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
            if (!empty($recentLogs)) {
                usort($recentLogs, function($a, $b) { return strcmp($b['timestamp'], $a['timestamp']); });
                $message .= "\n\nRecent Crew Logs (" . count($recentLogs) . " entries):\n";
                foreach (array_slice($recentLogs, 0, 20) as $log) {
                    $message .= "\n[{$log['timestamp']}] {$log['agent_role']}: {$log['prompt']}\n";
                }
            } else {
                $message .= "\n\nNo crew logs found.";
            }
        }
    }

    // @optimize_agents command
    if (preg_match('/\@optimize_agents/', $message)) {
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @optimize_agents\n", FILE_APPEND);
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
                            $agentStats[$role] = ['count' => 0, 'avg_response_len' => 0, 'total_response_len' => 0];
                        }
                        $agentStats[$role]['count']++;
                        $agentStats[$role]['total_response_len'] += strlen($entry['response']);
                    }
                }
            }
            foreach ($agentStats as $role => &$stats) {
                $stats['avg_response_len'] = $stats['count'] > 0 ? $stats['total_response_len'] / $stats['count'] : 0;
            }
            $message .= "\n\nAgent Performance Analysis:\n" . json_encode($agentStats, JSON_PRETTY_PRINT);
        }
    }

    // @train_agents command - Autonomous training
    if (preg_match('/\@train_agents/', $message)) {
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @train_agents\n", FILE_APPEND);
        $response = file_get_contents('http://localhost/api/claude_autonomous.php', false, stream_context_create([
            'http' => ['method' => 'POST']
        ]));
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            $message .= "\n\nAutonomous Training Results:\n" . implode("\n", $result['improvements']);
        } else {
            $message .= "\n\nAutonomous training failed: " . ($result['error'] ?? 'Unknown error');
        }
    }

    // @docker command
    if (preg_match('/\@docker\s+(.+)/', $message, $matches)) {
        $dockerCmd = trim($matches[1]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @docker: $dockerCmd\n", FILE_APPEND);
        $output = shell_exec("docker $dockerCmd 2>&1");
        $message .= "\n\n🐳 Docker: $dockerCmd\n" . ($output ?: "Command executed");
    }

    // @compose command
    if (preg_match('/\@compose\s+(.+)/', $message, $matches)) {
        $composeCmd = trim($matches[1]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @compose: $composeCmd\n", FILE_APPEND);
        $output = shell_exec("cd /app && docker-compose $composeCmd 2>&1");
        $message .= "\n\n🐙 Compose: $composeCmd\n" . ($output ?: "Command executed");
    }

    // @ps command
    if (preg_match('/\@ps/', $message)) {
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @ps\n", FILE_APPEND);
        $output = shell_exec("docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' 2>&1");
        $message .= "\n\n📋 Containers:\n" . ($output ?: "No containers");
    }

    // @exec command - Check permissions first
    if (preg_match_all('/\@exec\s+([^\s]+)\s+([^\n]+)/m', $message, $matches, PREG_SET_ORDER)) {
        // Get current mode from global or default to hybrid
        global $claudeMode;
        $currentMode = $claudeMode ?? 'hybrid';
        
        // Check permission
        require_once __DIR__ . '/check_command_permission.php';
        if (!checkCommandPermission('exec', $currentMode)) {
            $message .= "\n\n" . getPermissionError('exec', $currentMode);
        } else {
        foreach ($matches as $match) {
            $containerName = trim($match[1]);
            $command = trim($match[2]);
            // Log directly to Claude's database instead of file
            try {
                $dbPath = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data/claude_memory.db';
                $logPdo = new PDO("sqlite:$dbPath");
                $logPdo->prepare("INSERT INTO command_history (command, output, status, model_used, timestamp) VALUES (?, ?, ?, ?, datetime('now'))")
                       ->execute(["@exec $containerName $command", 'Executing...', 'running', 'claude', time()]);
            } catch (Exception $e) {}
            
            @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @exec: $containerName $command\n", FILE_APPEND);
            // Use base64 encoding to safely pass complex commands
            $encodedCommand = base64_encode($command);
            $output = shell_exec("timeout 15 docker exec $containerName bash -c 'echo $encodedCommand | base64 -d | bash' 2>&1");
            error_log("EXEC DEBUG - Command: $command, Output length: " . strlen($output ?: ''));
            error_log("EXEC DEBUG - Raw output: " . ($output ?: 'EMPTY'));
            $message .= "\n\n💻 Exec [$containerName]: $command\n" . ($output ?: "Command executed");
            
            // Capture for database
            if (!isset($GLOBALS['executedCommands'])) $GLOBALS['executedCommands'] = [];
            $GLOBALS['executedCommands'][] = [
                'command' => "@exec $containerName $command",
                'output' => $output ?: "Command executed"
            ];
        }
        }
    }

    // @inspect command
    if (preg_match('/\@inspect\s+([^\s]+)/', $message, $matches)) {
        $containerName = trim($matches[1]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @inspect: $containerName\n", FILE_APPEND);
        $output = shell_exec("timeout 10 docker inspect $containerName --format='{{.State.Status}} {{.Config.Image}} {{.NetworkSettings.IPAddress}}' 2>&1");
        $message .= "\n\n🔍 Container Info [$containerName]:\n" . ($output ?: "Container not found");
    }

    // @container_logs command
    if (preg_match('/\@container_logs\s+([^\s]+)(?:\s+(\d+))?/', $message, $matches)) {
        $containerName = trim($matches[1]);
        $lines = isset($matches[2]) ? (int)$matches[2] : 50;
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @container_logs: $containerName $lines\n", FILE_APPEND);
        $output = shell_exec("timeout 10 docker logs --tail=$lines $containerName 2>&1");
        $message .= "\n\n📜 Container Logs [$containerName] (last $lines lines):\n" . ($output ?: "No logs available");
    }
    
    // @memory command - Query database and create file with hyperlink
    if (preg_match('/\@memory\s+(chat|commands|search|config|sessions)\s*(.*)/', $message, $matches)) {
        $action = $matches[1];
        $params = trim($matches[2]);
        
        
        $memoryData = [];
        
        // Query database based on action
        try {
            $dbPath = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data/claude_memory.db';
            $pdo = new PDO("sqlite:$dbPath");
            
            if ($action === 'chat' && preg_match('/(\d+)min/', $params, $timeMatch)) {
                $minutes = (int)$timeMatch[1];
                $stmt = $pdo->prepare("SELECT sender, message, model_used, timestamp FROM chat_history WHERE timestamp >= datetime('now', '-{$minutes} minutes') ORDER BY timestamp DESC");
                $stmt->execute();
                $memoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($action === 'commands' && preg_match('/(\d+)min/', $params, $timeMatch)) {
                $minutes = (int)$timeMatch[1];
                $stmt = $pdo->prepare("SELECT command, output, status, model_used, timestamp FROM command_history WHERE datetime(timestamp) >= datetime('now', '-{$minutes} minutes') ORDER BY timestamp DESC");
                $stmt->execute();
                $memoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($action === 'config') {
                $stmt = $pdo->prepare("SELECT system_prompt, goals, personality, capabilities, updated_at FROM claude_config WHERE id = 1");
                $stmt->execute();
                $memoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($action === 'sessions') {
                $stmt = $pdo->prepare("SELECT model_used, mode, start_time, message_count, command_count FROM claude_sessions ORDER BY start_time DESC LIMIT 10");
                $stmt->execute();
                $memoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $memoryData = [['error' => 'Database query failed: ' . $e->getMessage()]];
        }
        
        // Create memory file in sessions directory
        $sessionsDir = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions';
        if (!is_dir($sessionsDir)) mkdir($sessionsDir, 0777, true);
        
        $filename = 'memory.json';
        $memoryFile = $sessionsDir . '/' . $filename;
        
        // Save to file
        file_put_contents($memoryFile, json_encode($memoryData, JSON_PRETTY_PRINT));
        
        // Auto-read file content for Claude
        $fileContent = file_get_contents($memoryFile);
        $message .= "\n\n🧠 Memory: Found " . count($memoryData) . " $action records\n";
        $message .= "File content of sessions/memory.json:\n" . $fileContent . "\n";
        $message .= "[View Memory File](../knowledge/internal_crew/agent_learning/self/claude/sessions/memory.json)";
    }
}

function processClaudeResponseCommands($claudeResponse, &$responseMessage) {
    // Universal command processing - reuse the same function for Claude's responses
    $tempMessage = $claudeResponse;
    processClaudeCommands($tempMessage);
    
    // Extract any command results that were added to the message
    if (strlen($tempMessage) > strlen($claudeResponse)) {
        $responseMessage = substr($tempMessage, strlen($claudeResponse));
    }
}
?>