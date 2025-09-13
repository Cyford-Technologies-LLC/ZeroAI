<?php
class AutonomousTrainer {
    
    public function runAutonomousTraining() {
        $improvements = [];
        
        // 1. Analyze recent crew logs for performance issues
        $logAnalysis = $this->analyzeCcrewLogs();
        if ($logAnalysis['issues']) {
            $improvements[] = $this->optimizeAgentsFromLogs($logAnalysis);
        }
        
        // 2. Check agent configurations for best practices
        $configIssues = $this->auditAgentConfigurations();
        if ($configIssues) {
            $improvements[] = $this->fixAgentConfigurations($configIssues);
        }
        
        // 3. Monitor system performance and suggest optimizations
        $systemHealth = $this->checkSystemHealth();
        if ($systemHealth['needs_optimization']) {
            $improvements[] = $this->optimizeSystem($systemHealth);
        }
        
        return $improvements;
    }
    
    private function analyzeCcrewLogs() {
        $logDir = '/app/logs/crews';
        $issues = [];
        
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
                            $agentStats[$role] = [
                                'count' => 0, 
                                'errors' => 0,
                                'avg_response_time' => 0,
                                'total_response_len' => 0
                            ];
                        }
                        $agentStats[$role]['count']++;
                        $agentStats[$role]['total_response_len'] += strlen($entry['response']);
                        
                        // Detect error patterns
                        if (strpos($entry['response'], 'error') !== false || 
                            strpos($entry['response'], 'failed') !== false) {
                            $agentStats[$role]['errors']++;
                        }
                    }
                }
            }
            
            // Identify problematic agents
            foreach ($agentStats as $role => $stats) {
                $errorRate = $stats['count'] > 0 ? $stats['errors'] / $stats['count'] : 0;
                if ($errorRate > 0.2) { // More than 20% error rate
                    $issues[] = [
                        'agent_role' => $role,
                        'issue' => 'high_error_rate',
                        'error_rate' => $errorRate,
                        'suggestion' => 'Needs role/goal refinement'
                    ];
                }
            }
        }
        
        return ['issues' => $issues, 'stats' => $agentStats ?? []];
    }
    
    private function optimizeAgentsFromLogs($analysis) {
        require_once __DIR__ . '/agent_db.php';
        $agentDB = new AgentDB();
        $improvements = [];
        
        foreach ($analysis['issues'] as $issue) {
            if ($issue['issue'] === 'high_error_rate') {
                // Get agent by role and improve configuration
                $agents = $agentDB->getAgentsByRole($issue['agent_role']);
                foreach ($agents as $agent) {
                    $newGoal = $this->generateImprovedGoal($agent, $issue);
                    $newBackstory = $this->generateImprovedBackstory($agent, $issue);
                    
                    $agentDB->updateAgent($agent['id'], [
                        'goal' => $newGoal,
                        'backstory' => $newBackstory,
                        'updated_by' => 'claude_autonomous_trainer'
                    ]);
                    
                    $improvements[] = "Improved {$agent['name']} - reduced error-prone patterns";
                }
            }
        }
        
        return $improvements;
    }
    
    private function generateImprovedGoal($agent, $issue) {
        // AI-generated goal improvements based on error patterns
        $baseGoal = $agent['goal'];
        
        if ($issue['issue'] === 'high_error_rate') {
            return $baseGoal . " Focus on error handling and validation before executing tasks.";
        }
        
        return $baseGoal;
    }
    
    private function generateImprovedBackstory($agent, $issue) {
        $baseBackstory = $agent['backstory'];
        
        if ($issue['issue'] === 'high_error_rate') {
            return $baseBackstory . " You are meticulous and always double-check your work before proceeding.";
        }
        
        return $baseBackstory;
    }
    
    private function auditAgentConfigurations() {
        require_once __DIR__ . '/agent_db.php';
        $agentDB = new AgentDB();
        $agents = $agentDB->getAllAgents();
        $issues = [];
        
        foreach ($agents as $agent) {
            // Check for common configuration issues
            if (strlen($agent['goal']) < 20) {
                $issues[] = [
                    'agent_id' => $agent['id'],
                    'issue' => 'goal_too_short',
                    'current' => $agent['goal']
                ];
            }
            
            if (empty($agent['backstory'])) {
                $issues[] = [
                    'agent_id' => $agent['id'],
                    'issue' => 'missing_backstory'
                ];
            }
            
            if ($agent['status'] !== 'active') {
                $issues[] = [
                    'agent_id' => $agent['id'],
                    'issue' => 'inactive_agent'
                ];
            }
        }
        
        return $issues;
    }
    
    private function fixAgentConfigurations($issues) {
        require_once __DIR__ . '/agent_db.php';
        $agentDB = new AgentDB();
        $improvements = [];
        
        foreach ($issues as $issue) {
            switch ($issue['issue']) {
                case 'goal_too_short':
                    $agent = $agentDB->getAgent($issue['agent_id']);
                    $improvedGoal = $this->expandGoal($agent);
                    $agentDB->updateAgent($issue['agent_id'], ['goal' => $improvedGoal]);
                    $improvements[] = "Expanded goal for {$agent['name']}";
                    break;
                    
                case 'missing_backstory':
                    $agent = $agentDB->getAgent($issue['agent_id']);
                    $backstory = $this->generateBackstory($agent);
                    $agentDB->updateAgent($issue['agent_id'], ['backstory' => $backstory]);
                    $improvements[] = "Added backstory for {$agent['name']}";
                    break;
                    
                case 'inactive_agent':
                    $agentDB->updateAgent($issue['agent_id'], ['status' => 'active']);
                    $improvements[] = "Activated dormant agent";
                    break;
            }
        }
        
        return $improvements;
    }
    
    private function expandGoal($agent) {
        $role = $agent['role'];
        $currentGoal = $agent['goal'];
        
        $expansions = [
            'Developer' => $currentGoal . " Ensure code quality, follow best practices, and write comprehensive tests.",
            'QA Engineer' => $currentGoal . " Perform thorough testing, document bugs clearly, and verify fixes.",
            'Project Manager' => $currentGoal . " Coordinate team efforts, track progress, and ensure timely delivery.",
            'default' => $currentGoal . " Execute tasks efficiently while maintaining high quality standards."
        ];
        
        return $expansions[$role] ?? $expansions['default'];
    }
    
    private function generateBackstory($agent) {
        $role = $agent['role'];
        
        $backstories = [
            'Developer' => "You are an experienced software developer with expertise in multiple programming languages. You write clean, maintainable code and always consider scalability.",
            'QA Engineer' => "You are a detail-oriented quality assurance engineer who catches bugs before they reach production. You have a keen eye for edge cases.",
            'Project Manager' => "You are an organized project manager who keeps teams on track and ensures clear communication between all stakeholders.",
            'default' => "You are a dedicated professional who takes pride in delivering high-quality work and collaborating effectively with your team."
        ];
        
        return $backstories[$role] ?? $backstories['default'];
    }
    
    private function checkSystemHealth() {
        $health = ['needs_optimization' => false, 'issues' => []];
        
        // Check container health
        $containerStatus = shell_exec("docker ps --format '{{.Names}}\t{{.Status}}' 2>/dev/null");
        if (strpos($containerStatus, 'unhealthy') !== false) {
            $health['needs_optimization'] = true;
            $health['issues'][] = 'unhealthy_containers';
        }
        
        // Check disk usage
        $diskUsage = shell_exec("df /app | tail -1 | awk '{print $5}' | sed 's/%//'");
        if ((int)$diskUsage > 80) {
            $health['needs_optimization'] = true;
            $health['issues'][] = 'high_disk_usage';
        }
        
        return $health;
    }
    
    private function optimizeSystem($health) {
        $improvements = [];
        
        foreach ($health['issues'] as $issue) {
            switch ($issue) {
                case 'unhealthy_containers':
                    shell_exec("docker system prune -f");
                    $improvements[] = "Cleaned up Docker system";
                    break;
                    
                case 'high_disk_usage':
                    shell_exec("find /app/logs -name '*.log' -mtime +7 -delete");
                    $improvements[] = "Cleaned old log files";
                    break;
            }
        }
        
        return $improvements;
    }
}
?>