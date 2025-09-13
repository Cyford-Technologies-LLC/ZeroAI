<?php
function processClaudeCommandsToContext(&$message, &$context) {
    // @agents command
    if (preg_match('/\@agents/', $message)) {
        require_once __DIR__ . '/agent_db.php';
        $agentDB = new AgentDB();
        $agents = $agentDB->getAllAgents();
        $agentList = "AGENTS:\n";
        foreach ($agents as $agent) {
            $agentList .= "- ID: {$agent['id']}, Name: {$agent['name']}, Role: {$agent['role']}, Status: {$agent['status']}\n";
        }
        $context .= "\n\n" . $agentList;
    }

    // @crews command
    if (preg_match('/\@crews/', $message)) {
        require_once __DIR__ . '/crew_context.php';
        $crewContext = new CrewContextManager();
        $runningCrews = $crewContext->getRunningCrews();
        $recentCrews = $crewContext->getRecentCrewExecutions(5);
        $crewInfo = "CREW STATUS:\n";
        if (!empty($runningCrews)) {
            $crewInfo .= "Running Crews:\n";
            foreach ($runningCrews as $crew) {
                $crewInfo .= "- Task ID: {$crew['task_id']}, Project: {$crew['project_id']}\n";
            }
        }
        if (!empty($recentCrews)) {
            $crewInfo .= "Recent Executions:\n";
            foreach ($recentCrews as $crew) {
                $crewInfo .= "- Task ID: {$crew['task_id']}, Status: {$crew['status']}\n";
            }
        }
        $context .= "\n\n" . $crewInfo;
    }

    // @logs command
    if (preg_match('/\@logs(?:\s+(\d+))?(?:\s+(\w+))?/', $message, $matches)) {
        $days = isset($matches[1]) ? (int)$matches[1] : 7;
        $agentRole = isset($matches[2]) ? $matches[2] : null;
        
        $logDir = '/app/logs/crews';
        if (is_dir($logDir)) {
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
                $context .= "\n\nCREW LOGS (" . count($recentLogs) . " entries):\n";
                foreach (array_slice($recentLogs, 0, 10) as $log) {
                    $context .= "[{$log['timestamp']}] {$log['agent_role']}: {$log['prompt']}\n";
                }
            }
        }
    }

    // @docker command
    if (preg_match('/\@docker\s+(.+)/', $message, $matches)) {
        $dockerCmd = trim($matches[1]);
        $output = shell_exec("docker $dockerCmd 2>&1");
        $context .= "\n\nDOCKER: $dockerCmd\n" . ($output ?: "Command executed");
    }

    // @compose command
    if (preg_match('/\@compose\s+(.+)/', $message, $matches)) {
        $composeCmd = trim($matches[1]);
        $output = shell_exec("cd /app && docker-compose $composeCmd 2>&1");
        $context .= "\n\nCOMPOSE: $composeCmd\n" . ($output ?: "Command executed");
    }

    // @ps command
    if (preg_match('/\@ps/', $message)) {
        $output = shell_exec("docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' 2>&1");
        $context .= "\n\nCONTAINERS:\n" . ($output ?: "No containers");
    }
}
?>