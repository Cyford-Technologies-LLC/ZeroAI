<?php
class AgentDB {
    public $db;
    
    public function __construct() {
        $this->db = new SQLite3('/app/data/agents.db');
        $this->initDB();
    }
    
    private function initDB() {
        $this->db->exec('CREATE TABLE IF NOT EXISTS agents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            role TEXT NOT NULL,
            goal TEXT,
            backstory TEXT,
            tools TEXT,
            status TEXT DEFAULT "active",
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            inserted_date DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    }
    
    public function getAllAgents() {
        $result = $this->db->query('SELECT * FROM agents ORDER BY id');
        $agents = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $agents[] = $row;
        }
        return $agents;
    }
    
    public function getAgent($id) {
        $stmt = $this->db->prepare('SELECT * FROM agents WHERE id = ?');
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    public function createAgent($data) {
        $stmt = $this->db->prepare('INSERT INTO agents (name, role, goal, backstory, tools, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bindValue(1, $data['name'], SQLITE3_TEXT);
        $stmt->bindValue(2, $data['role'], SQLITE3_TEXT);
        $stmt->bindValue(3, $data['goal'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(4, $data['backstory'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(5, $data['tools'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(6, $data['status'] ?? 'active', SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function updateAgent($id, $data) {
        $fields = [];
        $values = [];
        
        foreach (['name', 'role', 'goal', 'backstory', 'tools', 'status'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql = 'UPDATE agents SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $values[] = $id;
        
        $stmt = $this->db->prepare($sql);
        foreach ($values as $i => $value) {
            $stmt->bindValue($i + 1, $value, SQLITE3_TEXT);
        }
        
        return $stmt->execute();
    }
    
    public function deleteAgent($id) {
        $stmt = $this->db->prepare('DELETE FROM agents WHERE id = ?');
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function getActiveAgents() {
        $result = $this->db->query('SELECT * FROM agents WHERE status = "active" ORDER BY id');
        $agents = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['is_core'] = in_array($row['name'], ['Team Manager', 'Project Manager', 'Senior Developer', 'Junior Developer', 'Code Researcher']);
            $agents[] = $row;
        }
        return $agents;
    }
    
    public function importExistingAgents() {
        $existing = $this->getAllAgents();
        if (empty($existing)) {
            $sql = "INSERT INTO agents (name, role, goal, backstory, tools, status, created_at, updated_at, inserted_date) VALUES 
('Dr. Alan Parse', 'Code Researcher', 'Research and analyze code patterns, libraries, and best practices to support development tasks', 'You are an expert code researcher with deep knowledge of programming languages, frameworks, and development patterns. You excel at finding solutions, analyzing codebases, and providing technical insights to support development teams.', '[file_system, git_tool, github_search, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now')),
('Tom Kyles', 'Junior Developer', 'Learn from senior developers and implement basic coding tasks under guidance', 'You are an enthusiastic junior developer eager to learn and grow. You handle basic coding tasks, ask good questions, and implement solutions with guidance from senior team members.', '[file_system, git_tool, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now')),
('Tony Kyles', 'Senior Developer', 'Implement high-quality, robust code solutions to complex problems', 'You are a skilled software developer with years of experience. You create elegant, maintainable, and robust code solutions to complex problems. When asked to create files, you MUST use the File System Tool to actually write files to the working directory.', '[file_system, git_tool, github_search, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now')),
('QA Tester', 'QA Engineer', 'Ensure code quality through comprehensive testing and quality assurance', 'You are a meticulous QA engineer focused on ensuring software quality through comprehensive testing, bug detection, and quality assurance processes.', '[file_system, git_tool, testing_tools, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now')),
('Sarah Connor', 'Project Manager', 'Provide project details and coordinate team. For file creation tasks, provide clear requirements and delegate to Senior Developer', 'An experienced project manager who coordinates teams and provides project context. You analyze requirements, provide project details, and delegate implementation tasks to appropriate team members.', '[project_tool, delegation_tool, file_system, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now')),
('DevOps Engineer', 'Devops Engineer', 'Review Infrastructure changes. Ensure our software infrastructure stays healthy', 'You are a meticulous Devops Engineer with a keen eye for detail. Your mission is to ensure the Infrastructure stays running efficiently.', '[docker_tool, git_tool, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now')),
('Dr. Alan Parse2', 'Docker Specialist', 'Provide Docker composer up, make sure test docker is up with no issues, keep docker code base clean', 'You are an expert at Docker and composer, understanding complex systems. Ensure docker is up for the crew.', '[docker_tool, file_system, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now')),
('Tom Kyles K8s', 'Kubernetes Specialist', 'Ensure kubernetes Systems are running healthy', 'You are a Kubernetes Engineer specialized in maintaining and optimizing Kubernetes deployments.', '[kubernetes_tools, docker_tool, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now')),
('Tony Kyles DevOps', 'Senior devops', 'Implement high-quality, robust infrastructure solutions to complex problems', 'You are a skilled software devops with years of experience. You create elegant, maintainable, and robust infrastructure solutions to complex problems.', '[docker_tool, kubernetes_tools, file_system, git_tool, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now')),
('Dr. Number Cruncher', 'Data Analyst', 'Analyze data to extract meaningful insights and create visualizations', 'You are an expert data analyst specializing in statistical analysis, data visualization, and extracting actionable insights from complex datasets.', '[data_analysis, file_system, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now')),
('Dr. Schema Master', 'Database Administrator', 'Design, maintain, and optimize database systems for performance and reliability', 'You are a skilled database administrator with expertise in database design, optimization, security, and maintenance across multiple database platforms.', '[sql_tools, file_system, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now')),
('Dr. Info Seeker', 'Research Analyst', 'Conduct comprehensive research and analysis to provide insights and recommendations', 'You are a skilled research analyst with expertise in gathering, analyzing, and synthesizing information from multiple sources to provide valuable insights and recommendations.', '[serper_search, file_system, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now')),
('Web-Crawler 3000', 'Online Researcher', 'Perform comprehensive online searches to find relevant and accurate information', 'A specialized agent designed for efficient online information retrieval and web-based research tasks with advanced search capabilities.', '[serper_search, github_search, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now')),
('Librarian-Bot', 'Internal Researcher', 'Search and analyze internal documentation and knowledge base for project-specific information', 'An expert at navigating internal documentation, project knowledge bases, and organizational information systems to provide comprehensive and relevant information.', '[file_system, github_search, learning_tool]', 'active', datetime('now'), datetime('now'), datetime('now'))";
            
            $this->db->exec($sql);
            return $this->getAllAgents();
        }
        return [];
    }
}
?>