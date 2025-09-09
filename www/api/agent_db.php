<?php
class AgentDB {
    private $db;
    
    public function __construct() {
        $this->db = new SQLite3('/app/data/agents.db');
        $this->initDatabase();
    }
    
    private function initDatabase() {
        // Create agents table with all CrewAI options
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS agents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                role TEXT NOT NULL,
                goal TEXT NOT NULL,
                backstory TEXT NOT NULL,
                status TEXT DEFAULT "active",
                is_core BOOLEAN DEFAULT 0,
                llm_model TEXT DEFAULT "local",
                verbose BOOLEAN DEFAULT 1,
                allow_delegation BOOLEAN DEFAULT 0,
                max_iter INTEGER DEFAULT 25,
                max_rpm INTEGER DEFAULT NULL,
                max_execution_time INTEGER DEFAULT NULL,
                system_template TEXT DEFAULT NULL,
                prompt_template TEXT DEFAULT NULL,
                response_template TEXT DEFAULT NULL,
                allow_code_execution BOOLEAN DEFAULT 0,
                max_retry_limit INTEGER DEFAULT 2,
                use_system_prompt BOOLEAN DEFAULT 1,
                respect_context_window BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
        
        // Create agent_capabilities table
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS agent_capabilities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                agent_id INTEGER,
                capability TEXT NOT NULL,
                FOREIGN KEY (agent_id) REFERENCES agents (id)
            )
        ');
        
        // Create crews table with all CrewAI options
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS crews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                process TEXT DEFAULT "sequential",
                verbose BOOLEAN DEFAULT 1,
                memory BOOLEAN DEFAULT 0,
                cache BOOLEAN DEFAULT 1,
                max_rpm INTEGER DEFAULT NULL,
                language TEXT DEFAULT "en",
                language_file TEXT DEFAULT NULL,
                full_output BOOLEAN DEFAULT 0,
                step_callback TEXT DEFAULT NULL,
                task_callback TEXT DEFAULT NULL,
                share_crew BOOLEAN DEFAULT 0,
                max_execution_time INTEGER DEFAULT NULL,
                max_retry_limit INTEGER DEFAULT 2,
                embedder_config TEXT DEFAULT NULL,
                planning BOOLEAN DEFAULT 0,
                planning_llm TEXT DEFAULT NULL,
                status TEXT DEFAULT "active",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
        
        // Create crew_agents junction table
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS crew_agents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                crew_id INTEGER,
                agent_id INTEGER,
                agent_order INTEGER DEFAULT 0,
                FOREIGN KEY (crew_id) REFERENCES crews (id),
                FOREIGN KEY (agent_id) REFERENCES agents (id)
            )
        ');
        
        // Create tasks table
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                crew_id INTEGER,
                agent_id INTEGER,
                name TEXT NOT NULL,
                description TEXT NOT NULL,
                expected_output TEXT,
                context TEXT DEFAULT NULL,
                output_json TEXT DEFAULT NULL,
                output_pydantic TEXT DEFAULT NULL,
                output_file TEXT DEFAULT NULL,
                callback TEXT DEFAULT NULL,
                human_input BOOLEAN DEFAULT 0,
                async_execution BOOLEAN DEFAULT 0,
                task_order INTEGER DEFAULT 0,
                status TEXT DEFAULT "pending",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (crew_id) REFERENCES crews (id),
                FOREIGN KEY (agent_id) REFERENCES agents (id)
            )
        ');
        
        // Insert default agents if table is empty
        $result = $this->db->query('SELECT COUNT(*) as count FROM agents');
        $row = $result->fetchArray();
        if ($row['count'] == 0) {
            $this->insertDefaultAgents();
        }
    }
    
    private function insertDefaultAgents() {
        $defaultAgents = [
            [
                'name' => 'Team Manager',
                'role' => 'Team Manager',
                'goal' => 'Coordinate the efforts of specialist agents and manage the workflow effectively',
                'backstory' => 'You are a highly experienced and strategic Team Manager responsible for overseeing the collaboration of multiple specialist teams.',
                'is_core' => 1,
                'capabilities' => ['task coordination', 'workflow management', 'team leadership']
            ],
            [
                'name' => 'Project Manager',
                'role' => 'Project Manager',
                'goal' => 'Manage project coordination and strategic planning',
                'backstory' => 'You are an experienced Project Manager who excels at breaking down complex projects into manageable tasks and coordinating resources.',
                'is_core' => 1,
                'capabilities' => ['project planning', 'resource management', 'stakeholder communication']
            ],
            [
                'name' => 'Senior Developer',
                'role' => 'Senior Developer',
                'goal' => 'Implement complex code solutions and architectural decisions',
                'backstory' => 'You are a senior software developer with extensive experience in system architecture and complex problem solving.',
                'is_core' => 1,
                'capabilities' => ['advanced coding', 'architecture design', 'code optimization', 'technical leadership']
            ],
            [
                'name' => 'Junior Developer',
                'role' => 'Junior Developer', 
                'goal' => 'Implement basic code solutions and assist with development tasks',
                'backstory' => 'You are an enthusiastic junior developer eager to learn and contribute to the team.',
                'is_core' => 1,
                'capabilities' => ['basic coding', 'code implementation', 'debugging assistance', 'learning support']
            ],
            [
                'name' => 'Code Researcher',
                'role' => 'Code Researcher',
                'goal' => 'Analyze and research code patterns and solutions',
                'backstory' => 'You are a meticulous code researcher who excels at analyzing codebases and finding optimal solutions.',
                'is_core' => 1,
                'capabilities' => ['code analysis', 'pattern research', 'solution finding']
            ]
        ];
        
        foreach ($defaultAgents as $agent) {
            $capabilities = $agent['capabilities'];
            unset($agent['capabilities']);
            
            $agentId = $this->createAgent($agent);
            foreach ($capabilities as $capability) {
                $this->addCapability($agentId, $capability);
            }
        }
    }
    
    public function createAgent($data) {
        $stmt = $this->db->prepare('
            INSERT INTO agents (name, role, goal, backstory, status, is_core, llm_model, verbose, allow_delegation, max_iter, max_rpm, max_execution_time, allow_code_execution, max_retry_limit)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->bindValue(1, $data['name']);
        $stmt->bindValue(2, $data['role']);
        $stmt->bindValue(3, $data['goal']);
        $stmt->bindValue(4, $data['backstory']);
        $stmt->bindValue(5, $data['status'] ?? 'active');
        $stmt->bindValue(6, $data['is_core'] ?? 0);
        $stmt->bindValue(7, $data['llm_model'] ?? 'local');
        $stmt->bindValue(8, $data['verbose'] ?? 1);
        $stmt->bindValue(9, $data['allow_delegation'] ?? 0);
        $stmt->bindValue(10, $data['max_iter'] ?? 25);
        $stmt->bindValue(11, $data['max_rpm'] ?? null);
        $stmt->bindValue(12, $data['max_execution_time'] ?? null);
        $stmt->bindValue(13, $data['allow_code_execution'] ?? 0);
        $stmt->bindValue(14, $data['max_retry_limit'] ?? 2);
        
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }
    
    public function updateAgent($id, $data) {
        $stmt = $this->db->prepare('
            UPDATE agents 
            SET name=?, role=?, goal=?, backstory=?, status=?, llm_model=?, updated_at=CURRENT_TIMESTAMP
            WHERE id=?
        ');
        
        $stmt->bindValue(1, $data['name']);
        $stmt->bindValue(2, $data['role']);
        $stmt->bindValue(3, $data['goal']);
        $stmt->bindValue(4, $data['backstory']);
        $stmt->bindValue(5, $data['status']);
        $stmt->bindValue(6, $data['llm_model']);
        $stmt->bindValue(7, $id);
        
        return $stmt->execute();
    }
    
    public function getAgent($id) {
        $stmt = $this->db->prepare('SELECT * FROM agents WHERE id = ?');
        $stmt->bindValue(1, $id);
        $result = $stmt->execute();
        
        $agent = $result->fetchArray(SQLITE3_ASSOC);
        if ($agent) {
            $agent['capabilities'] = $this->getCapabilities($id);
        }
        return $agent;
    }
    
    public function getAllAgents() {
        $result = $this->db->query('SELECT * FROM agents ORDER BY is_core DESC, name ASC');
        $agents = [];
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['capabilities'] = $this->getCapabilities($row['id']);
            $agents[] = $row;
        }
        
        return $agents;
    }
    
    public function getActiveAgents() {
        $result = $this->db->query('SELECT * FROM agents WHERE status = "active" ORDER BY is_core DESC, name ASC');
        $agents = [];
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['capabilities'] = $this->getCapabilities($row['id']);
            $agents[] = $row;
        }
        
        return $agents;
    }
    
    public function addCapability($agentId, $capability) {
        $stmt = $this->db->prepare('INSERT INTO agent_capabilities (agent_id, capability) VALUES (?, ?)');
        $stmt->bindValue(1, $agentId);
        $stmt->bindValue(2, $capability);
        return $stmt->execute();
    }
    
    public function getCapabilities($agentId) {
        $stmt = $this->db->prepare('SELECT capability FROM agent_capabilities WHERE agent_id = ?');
        $stmt->bindValue(1, $agentId);
        $result = $stmt->execute();
        
        $capabilities = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $capabilities[] = $row['capability'];
        }
        
        return $capabilities;
    }
    
    public function deleteAgent($id) {
        // Delete capabilities first
        $stmt = $this->db->prepare('DELETE FROM agent_capabilities WHERE agent_id = ?');
        $stmt->bindValue(1, $id);
        $stmt->execute();
        
        // Delete agent
        $stmt = $this->db->prepare('DELETE FROM agents WHERE id = ?');
        $stmt->bindValue(1, $id);
        return $stmt->execute();
    }
    
    // Crew Management Methods
    public function createCrew($data) {
        $stmt = $this->db->prepare('
            INSERT INTO crews (name, description, process, verbose, memory, cache, max_rpm, language, full_output, share_crew, max_execution_time, max_retry_limit, planning)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->bindValue(1, $data['name']);
        $stmt->bindValue(2, $data['description'] ?? '');
        $stmt->bindValue(3, $data['process'] ?? 'sequential');
        $stmt->bindValue(4, $data['verbose'] ?? 1);
        $stmt->bindValue(5, $data['memory'] ?? 0);
        $stmt->bindValue(6, $data['cache'] ?? 1);
        $stmt->bindValue(7, $data['max_rpm'] ?? null);
        $stmt->bindValue(8, $data['language'] ?? 'en');
        $stmt->bindValue(9, $data['full_output'] ?? 0);
        $stmt->bindValue(10, $data['share_crew'] ?? 0);
        $stmt->bindValue(11, $data['max_execution_time'] ?? null);
        $stmt->bindValue(12, $data['max_retry_limit'] ?? 2);
        $stmt->bindValue(13, $data['planning'] ?? 0);
        
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }
    
    public function getAllCrews() {
        $result = $this->db->query('SELECT * FROM crews ORDER BY name ASC');
        $crews = [];
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['agents'] = $this->getCrewAgents($row['id']);
            $row['tasks'] = $this->getCrewTasks($row['id']);
            $crews[] = $row;
        }
        
        return $crews;
    }
    
    public function addAgentToCrew($crewId, $agentId, $order = 0) {
        $stmt = $this->db->prepare('INSERT INTO crew_agents (crew_id, agent_id, agent_order) VALUES (?, ?, ?)');
        $stmt->bindValue(1, $crewId);
        $stmt->bindValue(2, $agentId);
        $stmt->bindValue(3, $order);
        return $stmt->execute();
    }
    
    public function getCrewAgents($crewId) {
        $stmt = $this->db->prepare('
            SELECT a.*, ca.agent_order 
            FROM agents a 
            JOIN crew_agents ca ON a.id = ca.agent_id 
            WHERE ca.crew_id = ? 
            ORDER BY ca.agent_order ASC
        ');
        $stmt->bindValue(1, $crewId);
        $result = $stmt->execute();
        
        $agents = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $agents[] = $row;
        }
        
        return $agents;
    }
    
    public function createTask($data) {
        $stmt = $this->db->prepare('
            INSERT INTO tasks (crew_id, agent_id, name, description, expected_output, context, human_input, async_execution, task_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->bindValue(1, $data['crew_id']);
        $stmt->bindValue(2, $data['agent_id']);
        $stmt->bindValue(3, $data['name']);
        $stmt->bindValue(4, $data['description']);
        $stmt->bindValue(5, $data['expected_output'] ?? '');
        $stmt->bindValue(6, $data['context'] ?? null);
        $stmt->bindValue(7, $data['human_input'] ?? 0);
        $stmt->bindValue(8, $data['async_execution'] ?? 0);
        $stmt->bindValue(9, $data['task_order'] ?? 0);
        
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }
    
    public function getCrewTasks($crewId) {
        $stmt = $this->db->prepare('
            SELECT t.*, a.name as agent_name, a.role as agent_role
            FROM tasks t 
            LEFT JOIN agents a ON t.agent_id = a.id 
            WHERE t.crew_id = ? 
            ORDER BY t.task_order ASC
        ');
        $stmt->bindValue(1, $crewId);
        $result = $stmt->execute();
        
        $tasks = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $tasks[] = $row;
        }
        
        return $tasks;
    }
    
    public function importExistingAgents() {
        $imported = [];
        $agentsDir = '/app/src/crews/internal';
        
        if (!is_dir($agentsDir)) {
            return $imported;
        }
        
        // Scan all crew directories
        $crewDirs = glob($agentsDir . '/*', GLOB_ONLYDIR);
        
        foreach ($crewDirs as $crewDir) {
            $agentsFile = $crewDir . '/agents.py';
            
            if (file_exists($agentsFile)) {
                $content = file_get_contents($agentsFile);
                $agents = $this->parseAgentsFromPython($content, basename($crewDir));
                
                foreach ($agents as $agent) {
                    // Check if agent already exists
                    $existing = $this->db->query("SELECT id FROM agents WHERE role = '" . SQLite3::escapeString($agent['role']) . "'");
                    if (!$existing->fetchArray()) {
                        $agentId = $this->createAgent($agent);
                        if (isset($agent['capabilities'])) {
                            foreach ($agent['capabilities'] as $capability) {
                                $this->addCapability($agentId, $capability);
                            }
                        }
                        $imported[] = $agent;
                    }
                }
            }
        }
        
        return $imported;
    }
    
    private function parseAgentsFromPython($content, $crewName) {
        $agents = [];
        
        // Extract agent creation functions with more comprehensive parsing
        preg_match_all('/def create_(\w+)_agent\([^)]*\):[\s\S]*?return Agent\([\s\S]*?\)/m', $content, $matches);
        
        foreach ($matches[0] as $index => $match) {
            $agentType = $matches[1][$index];
            
            // Extract all Agent parameters
            $agent = [
                'name' => $this->extractParameter($match, 'role') ?? ucwords(str_replace('_', ' ', $agentType)),
                'role' => $this->extractParameter($match, 'role') ?? ucwords(str_replace('_', ' ', $agentType)),
                'goal' => $this->extractParameter($match, 'goal') ?? "Specialized agent for $crewName crew",
                'backstory' => $this->extractParameter($match, 'backstory') ?? "You are a specialized agent working as part of the $crewName crew.",
                'status' => 'active',
                'is_core' => 1,
                'llm_model' => 'local',
                'verbose' => $this->extractBooleanParameter($match, 'verbose') ?? 1,
                'allow_delegation' => $this->extractBooleanParameter($match, 'allow_delegation') ?? 0,
                'max_iter' => $this->extractIntParameter($match, 'max_iter') ?? 25,
                'max_rpm' => $this->extractIntParameter($match, 'max_rpm'),
                'max_execution_time' => $this->extractIntParameter($match, 'max_execution_time'),
                'allow_code_execution' => $this->extractBooleanParameter($match, 'allow_code_execution') ?? 0,
                'max_retry_limit' => $this->extractIntParameter($match, 'max_retry_limit') ?? 2,
                'capabilities' => $this->extractCapabilities($match, $crewName)
            ];
            
            $agents[] = $agent;
        }
        
        // Also parse AVAILABLE_AGENTS dictionary if present
        if (preg_match('/AVAILABLE_AGENTS\s*=\s*{([\s\S]*?)}/m', $content, $availableMatch)) {
            $agents = array_merge($agents, $this->parseAvailableAgents($availableMatch[1]));
        }
        
        return $agents;
    }
    
    private function extractParameter($content, $param) {
        // Handle both single and double quotes, and multiline strings
        if (preg_match('/' . $param . '\s*=\s*["\']([^"\']*)["\']/s', $content, $match)) {
            return trim($match[1]);
        }
        // Handle triple quotes for multiline
        if (preg_match('/' . $param . '\s*=\s*["\'\']{3}([\s\S]*?)["\'\']{3}/s', $content, $match)) {
            return trim($match[1]);
        }
        return null;
    }
    
    private function extractBooleanParameter($content, $param) {
        if (preg_match('/' . $param . '\s*=\s*(True|False|true|false|1|0)/i', $content, $match)) {
            return in_array(strtolower($match[1]), ['true', '1']) ? 1 : 0;
        }
        return null;
    }
    
    private function extractIntParameter($content, $param) {
        if (preg_match('/' . $param . '\s*=\s*(\d+)/', $content, $match)) {
            return (int)$match[1];
        }
        return null;
    }
    
    private function extractCapabilities($content, $crewName) {
        $capabilities = [$crewName . ' operations'];
        
        // Look for comments or docstrings that might contain capabilities
        if (preg_match_all('/["\']([^"\']*(capabilit|skill|expert|speciali)[^"\']*)["\']/i', $content, $matches)) {
            foreach ($matches[1] as $match) {
                if (strlen($match) > 10 && strlen($match) < 100) {
                    $capabilities[] = trim($match);
                }
            }
        }
        
        return array_unique($capabilities);
    }
    
    private function parseAvailableAgents($availableAgentsContent) {
        $agents = [];
        
        // Parse each agent entry in AVAILABLE_AGENTS
        preg_match_all('/["\']([^"\']*)["\']: {([^}]*)}/s', $availableAgentsContent, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $name = $match[1];
            $details = $match[2];
            
            // Extract description
            preg_match('/["\']description["\']\s*:\s*["\']([^"\']*)["\']/s', $details, $descMatch);
            $description = $descMatch[1] ?? '';
            
            // Extract capabilities
            preg_match('/["\']capabilities["\']\s*:\s*\[([^\]]*)\]/s', $details, $capMatch);
            $capabilities = [];
            if (isset($capMatch[1])) {
                preg_match_all('/["\']([^"\']*)["\']/s', $capMatch[1], $capMatches);
                $capabilities = $capMatches[1];
            }
            
            $agents[] = [
                'name' => $name,
                'role' => $name,
                'goal' => $description,
                'backstory' => "You are a $name. $description",
                'status' => 'active',
                'is_core' => 1,
                'llm_model' => 'local',
                'verbose' => 1,
                'allow_delegation' => strpos(strtolower($name), 'manager') !== false ? 1 : 0,
                'max_iter' => 25,
                'max_rpm' => null,
                'max_execution_time' => null,
                'allow_code_execution' => strpos(strtolower($name), 'developer') !== false ? 1 : 0,
                'max_retry_limit' => 2,
                'capabilities' => $capabilities
            ];
        }
        
        return $agents;
    }
}
?>