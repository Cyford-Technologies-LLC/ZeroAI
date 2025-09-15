<?php

namespace ZeroAI\Providers\AI\Local;

class LocalCommands {
    
    public function processLocalCommands(&$message) {
        $message = preg_replace_callback('/\@crew\s+(.+)/', [$this, 'runCrew'], $message);
        $message = preg_replace_callback('/\@agents/', [$this, 'listAgents'], $message);
        $message = preg_replace_callback('/\@status/', [$this, 'systemStatus'], $message);
        $message = preg_replace_callback('/\@models/', [$this, 'listModels'], $message);
    }
    
    private function runCrew($matches) {
        $task = trim($matches[1]);
        $result = shell_exec("cd /app && python run/basic_crew.py '{$task}' 2>&1");
        return "\n\n🤖 Crew Task: {$task}\n" . ($result ?: "No output") . "\n";
    }
    
    private function listAgents() {
        require_once __DIR__ . '/../../../Models/Agent.php';
        $agent = new \ZeroAI\Models\Agent();
        $agents = $agent->getAll();
        
        $output = "\n\n🤖 Local Agents:\n";
        foreach ($agents as $a) {
            $output .= "ID: {$a['id']} | Role: {$a['role']} | Status: Local\n";
        }
        return $output;
    }
    
    private function systemStatus() {
        $ollama = shell_exec('curl -s http://localhost:11434/api/tags 2>/dev/null');
        $docker = shell_exec('docker ps --format "{{.Names}}" 2>/dev/null');
        
        return "\n\n📊 System Status:\n" .
               "Ollama: " . ($ollama ? "Running" : "Offline") . "\n" .
               "Containers: " . ($docker ? trim($docker) : "None") . "\n";
    }
    
    private function listModels() {
        $result = shell_exec('curl -s http://localhost:11434/api/tags 2>/dev/null');
        if ($result) {
            $models = json_decode($result, true);
            $output = "\n\n🧠 Available Models:\n";
            foreach ($models['models'] ?? [] as $model) {
                $output .= "- {$model['name']}\n";
            }
            return $output;
        }
        return "\n❌ Ollama not available\n";
    }
}


