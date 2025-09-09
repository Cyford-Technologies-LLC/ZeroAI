<?php
class AgentImporter {
    private $db;
    private $pythonPath = '/app/venv/bin/python';
    
    public function __construct() {
        $this->db = new PDO("sqlite:/app/data/zeroai.db");
    }
    
    public function importExistingAgents() {
        // Scan existing agent files and import them
        $agentPaths = [
            '/app/src/crews/internal/developer/',
            '/app/src/crews/internal/devops/',
            '/app/src/crews/internal/research/',
            '/app/src/crews/internal/documentation/',
            '/app/src/crews/internal/repo_manager/',
            '/app/src/agents/'
        ];
        
        $importedAgents = [];
        
        foreach ($agentPaths as $path) {
            if (is_dir($path)) {
                $agents = $this->scanAgentDirectory($path);
                $importedAgents = array_merge($importedAgents, $agents);
            }
        }
        
        // Import from Python crew definitions
        $pythonAgents = $this->importFromPythonCrews();
        $importedAgents = array_merge($importedAgents, $pythonAgents);
        
        return $importedAgents;
    }
    
    private function scanAgentDirectory($path) {
        $agents = [];
        $files = glob($path . '*.py');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $agent = $this->parseAgentFromPython($content, $file);
            if ($agent) {
                $this->saveAgentToDatabase($agent);
                $agents[] = $agent;
            }
        }
        
        return $agents;
    }
    
    private function parseAgentFromPython($content, $filepath) {
        // Extract agent definition from Python code
        preg_match('/Agent\s*\(\s*role\s*=\s*["\']([^"\']+)["\']/', $content, $roleMatch);
        preg_match('/goal\s*=\s*["\']([^"\']+)["\']/', $content, $goalMatch);
        preg_match('/backstory\s*=\s*["\']([^"\']+)["\']/', $content, $backstoryMatch);
        
        if (!$roleMatch || !$goalMatch) {
            return null;
        }
        
        $filename = basename($filepath, '.py');
        $name = ucwords(str_replace('_', ' ', $filename));
        
        return [
            'name' => $name,
            'role' => $roleMatch[1],
            'goal' => $goalMatch[1] ?? 'Assist with tasks',
            'backstory' => $backstoryMatch[1] ?? 'Experienced AI agent',
            'filepath' => $filepath,
            'is_core' => strpos($filepath, '/internal/') !== false ? 1 : 0,
            'status' => 'active'
        ];
    }
    
    private function importFromPythonCrews() {
        // Execute Python script to get live agent definitions
        $script = "
import sys
sys.path.append('/app')
sys.path.append('/app/src')

try:
    from src.crews.internal.developer.crew import DeveloperCrew
    from src.crews.internal.devops.crew import DevOpsCrew
    from src.crews.internal.research.crew import ResearchCrew
    import json
    
    agents = []
    
    # Get developer crew agents
    try:
        dev_crew = DeveloperCrew()
        for agent in dev_crew.agents():
            agents.append({
                'name': agent.role,
                'role': agent.role,
                'goal': agent.goal,
                'backstory': agent.backstory,
                'crew': 'developer',
                'is_core': True
            })
    except: pass
    
    # Get devops crew agents  
    try:
        devops_crew = DevOpsCrew()
        for agent in devops_crew.agents():
            agents.append({
                'name': agent.role,
                'role': agent.role,
                'goal': agent.goal,
                'backstory': agent.backstory,
                'crew': 'devops',
                'is_core': True
            })
    except: pass
    
    print(json.dumps(agents))
    
except Exception as e:
    print(json.dumps({'error': str(e)}))
";
        
        $tempFile = '/tmp/import_agents.py';
        file_put_contents($tempFile, $script);
        
        $output = shell_exec("{$this->pythonPath} {$tempFile} 2>&1");
        unlink($tempFile);
        
        $result = json_decode($output, true);
        
        if ($result && !isset($result['error'])) {
            foreach ($result as $agent) {
                $this->saveAgentToDatabase($agent);
            }
            return $result;
        }
        
        return [];
    }
    
    private function saveAgentToDatabase($agent) {
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO agents (name, role, goal, backstory, config, is_core, status, filepath) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $config = json_encode([
            'crew' => $agent['crew'] ?? null,
            'tools' => [],
            'memory' => true,
            'planning' => true
        ]);
        
        return $stmt->execute([
            $agent['name'],
            $agent['role'],
            $agent['goal'],
            $agent['backstory'],
            $config,
            $agent['is_core'] ?? 0,
            $agent['status'] ?? 'active',
            $agent['filepath'] ?? null
        ]);
    }
    
    public function getActiveAgents() {
        $stmt = $this->db->query("SELECT * FROM agents WHERE status = 'active' ORDER BY is_core DESC, name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateAgentRealtime($agentId, $updates) {
        // Update database
        $fields = [];
        $values = [];
        
        foreach ($updates as $field => $value) {
            if (in_array($field, ['name', 'role', 'goal', 'backstory', 'status'])) {
                $fields[] = "$field = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) return false;
        
        $values[] = $agentId;
        $sql = "UPDATE agents SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);
        
        // Update live Python agent if it exists
        if ($result) {
            $this->updateLiveAgent($agentId, $updates);
        }
        
        return $result;
    }
    
    private function updateLiveAgent($agentId, $updates) {
        // Send update to live Python system via API or file
        $agent = $this->getAgentById($agentId);
        if (!$agent) return false;
        
        $updateScript = "
import sys
sys.path.append('/app')
sys.path.append('/app/src')

try:
    # Update agent configuration in live system
    agent_config = {
        'id': {$agentId},
        'name': '{$agent['name']}',
        'role': '{$agent['role']}',
        'goal': '{$agent['goal']}',
        'backstory': '{$agent['backstory']}'
    }
    
    # Write to agent config file for live reload
    import json
    with open('/app/data/agent_updates.json', 'w') as f:
        json.dump(agent_config, f)
    
    print('Agent updated successfully')
    
except Exception as e:
    print(f'Error updating agent: {e}')
";
        
        $tempFile = '/tmp/update_agent_' . $agentId . '.py';
        file_put_contents($tempFile, $updateScript);
        shell_exec("{$this->pythonPath} {$tempFile} 2>&1");
        unlink($tempFile);
    }
    
    private function getAgentById($id) {
        $stmt = $this->db->prepare("SELECT * FROM agents WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>