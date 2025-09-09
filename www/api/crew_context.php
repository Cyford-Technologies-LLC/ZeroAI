<?php
class CrewContextManager {
    private $contextDir = '/app/logs/crew_context';
    
    public function __construct() {
        if (!is_dir($this->contextDir)) {
            mkdir($this->contextDir, 0755, true);
        }
    }
    
    public function saveCrewExecution($taskId, $data) {
        $contextFile = $this->contextDir . "/crew_execution_{$taskId}.json";
        $context = [
            'task_id' => $taskId,
            'timestamp' => time(),
            'prompt' => $data['prompt'] ?? '',
            'project_id' => $data['project_id'] ?? '',
            'status' => $data['status'] ?? 'running',
            'agents' => $data['agents'] ?? [],
            'results' => $data['results'] ?? [],
            'logs' => $data['logs'] ?? []
        ];
        
        file_put_contents($contextFile, json_encode($context, JSON_PRETTY_PRINT));
        return $contextFile;
    }
    
    public function getCrewExecution($taskId) {
        $contextFile = $this->contextDir . "/crew_execution_{$taskId}.json";
        if (file_exists($contextFile)) {
            return json_decode(file_get_contents($contextFile), true);
        }
        return null;
    }
    
    public function getRecentCrewExecutions($limit = 10) {
        $files = glob($this->contextDir . "/crew_execution_*.json");
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $executions = [];
        foreach (array_slice($files, 0, $limit) as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $executions[] = $data;
            }
        }
        
        return $executions;
    }
    
    public function getRunningCrews() {
        $files = glob($this->contextDir . "/crew_execution_*.json");
        $running = [];
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['status'] === 'running') {
                $running[] = $data;
            }
        }
        
        return $running;
    }
    
    public function updateCrewStatus($taskId, $status, $results = null) {
        $context = $this->getCrewExecution($taskId);
        if ($context) {
            $context['status'] = $status;
            $context['updated_at'] = time();
            if ($results) {
                $context['results'] = $results;
            }
            
            $contextFile = $this->contextDir . "/crew_execution_{$taskId}.json";
            file_put_contents($contextFile, json_encode($context, JSON_PRETTY_PRINT));
        }
    }
}
?>