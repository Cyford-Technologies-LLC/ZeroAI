<?php
namespace ZeroAI\Core;

trait AgentMethods {
    
    public function getAllAgents($bypassCache = false) {
        return $this->query('SELECT * FROM agents ORDER BY id', [], $bypassCache);
    }
    
    public function getAgent($id, $bypassCache = false) {
        return $this->query('SELECT * FROM agents WHERE id = ?', [$id], $bypassCache)[0] ?? null;
    }
    
    public function createAgent($data, $bypassQueue = false) {
        return $this->insert('agents', $data, $bypassQueue);
    }
    
    public function updateAgent($id, $data, $bypassQueue = false) {
        return $this->update('agents', $data, ['id' => $id], $bypassQueue);
    }
    
    public function deleteAgent($id, $bypassQueue = false) {
        return $this->delete('agents', ['id' => $id], $bypassQueue);
    }
    
    public function getActiveAgents($bypassCache = false) {
        $agents = $this->query('SELECT * FROM agents WHERE status = "active" ORDER BY id', [], $bypassCache);
        foreach ($agents as &$agent) {
            $agent['is_core'] = in_array($agent['name'], ['Team Manager', 'Project Manager', 'Senior Developer', 'Junior Developer', 'Code Researcher']);
        }
        return $agents;
    }
    
    public function importExistingAgents($bypassQueue = false) {
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
            
            $this->query($sql, [], false, $bypassQueue);
            return $this->getAllAgents();
        }
        return [];
    }
}
?>