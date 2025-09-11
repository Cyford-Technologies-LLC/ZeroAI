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
}

function processClaudeResponseCommands($claudeResponse, &$responseMessage) {
    // @sql command
    if (preg_match('/\@sql\s+```([\s\S]*?)```/', $claudeResponse, $matches)) {
        require_once __DIR__ . '/sqlite_manager.php';
        $sql = trim($matches[1]);
        $results = SQLiteManager::executeSQL($sql);
        $responseMessage .= "\n\n✅ SQL executed:\n" . json_encode($results, JSON_PRETTY_PRINT);
    }
    
    // @create command
    if (preg_match('/\@create\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $claudeResponse, $matches)) {
        $filePath = trim($matches[1]);
        $fileContent = isset($matches[2]) ? trim($matches[2]) : "";
        $cleanPath = ltrim($filePath, '/');
        if (strpos($cleanPath, 'app/') === 0) $cleanPath = substr($cleanPath, 4);
        $fullPath = '/app/' . $cleanPath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $result = file_put_contents($fullPath, $fileContent);
        if ($result !== false) {
            $responseMessage .= "\n\n✅ File created: " . $cleanPath . " (" . $result . " bytes)";
        }
    }
    
    // @edit command
    if (preg_match('/\@edit\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $claudeResponse, $matches)) {
        $filePath = trim($matches[1]);
        $fileContent = isset($matches[2]) ? trim($matches[2]) : "";
        $cleanPath = ltrim($filePath, '/');
        if (strpos($cleanPath, 'app/') === 0) $cleanPath = substr($cleanPath, 4);
        $fullPath = '/app/' . $cleanPath;
        if (file_exists($fullPath)) {
            $result = file_put_contents($fullPath, $fileContent);
            if ($result !== false) {
                $responseMessage .= "\n\n✅ File edited: " . $cleanPath . " (" . $result . " bytes)";
            }
        }
    }

    // @append command
    if (preg_match('/\@append\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $claudeResponse, $matches)) {
        $filePath = trim($matches[1]);
        $fileContent = isset($matches[2]) ? trim($matches[2]) : "";
        $cleanPath = ltrim($filePath, '/');
        if (strpos($cleanPath, 'app/') === 0) $cleanPath = substr($cleanPath, 4);
        $fullPath = '/app/' . $cleanPath;
        if (file_exists($fullPath)) {
            $result = file_put_contents($fullPath, "\n" . $fileContent, FILE_APPEND);
            if ($result !== false) {
                $responseMessage .= "\n\n✅ Content appended: " . $cleanPath;
            }
        }
    }

    // @delete command
    if (preg_match('/\@delete\s+([^\s\n]+)/', $claudeResponse, $matches)) {
        $filePath = trim($matches[1]);
        $cleanPath = ltrim($filePath, '/');
        if (strpos($cleanPath, 'app/') === 0) $cleanPath = substr($cleanPath, 4);
        $fullPath = '/app/' . $cleanPath;
        if (file_exists($fullPath)) {
            if (unlink($fullPath)) {
                $responseMessage .= "\n\n✅ File deleted: " . $cleanPath;
            }
        }
    }

    // @docker command with timeout protection
    if (preg_match('/\@docker\s+(.+)/', $message, $matches)) {
        $dockerCmd = trim($matches[1]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @docker: $dockerCmd\n", FILE_APPEND);
        
        // Prevent dangerous commands
        if (preg_match('/(rm|kill|stop|restart)/', $dockerCmd)) {
            $message .= "\n\n❌ Docker: Dangerous command blocked for safety";
        } else {
            $output = shell_exec("timeout 10 docker $dockerCmd 2>&1");
            $message .= "\n\n🐳 Docker: $dockerCmd\n" . ($output ?: "Command executed");
        }
    }

    // @compose command with timeout protection
    if (preg_match('/\@compose\s+(.+)/', $message, $matches)) {
        $composeCmd = trim($matches[1]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @compose: $composeCmd\n", FILE_APPEND);
        
        // Prevent dangerous commands
        if (preg_match('/(down|kill|stop)/', $composeCmd)) {
            $message .= "\n\n❌ Compose: Dangerous command blocked for safety";
        } else {
            $output = shell_exec("timeout 30 bash -c 'cd /app && docker-compose $composeCmd' 2>&1");
            $message .= "\n\n🐙 Compose: $composeCmd\n" . ($output ?: "Command executed");
        }
    }

    // @ps command with timeout
    if (preg_match('/\@ps/', $message)) {
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @ps\n", FILE_APPEND);
        $output = shell_exec("timeout 5 docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' 2>&1");
        $message .= "\n\n📋 Containers:\n" . ($output ?: "No containers");
    }
}
?>