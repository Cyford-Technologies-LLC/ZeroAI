<?php
namespace ZeroAI\Core;

class CRMAPI {
    public function __construct() {
        // Ensure CompanyAI table exists
        $this->initializeAITables();
    }
    
    private function initializeAITables() {
        $db = DatabaseManager::getInstance();
        $db->query("CREATE TABLE IF NOT EXISTS company_agents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            agent_id INTEGER NOT NULL,
            assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (agent_id) REFERENCES agents(id),
            UNIQUE(company_id, agent_id)
        )");
    }
    public function handle($endpoint) {
        header('Content-Type: application/json');
        
        switch ($endpoint) {
            // Tenant Management
            case 'tenants':
                echo json_encode($this->handleTenants());
                break;
                
            case 'companies':
                echo json_encode($this->handleCompanies());
                break;
                
            case 'projects':
                echo json_encode($this->handleProjects());
                break;
                
            case 'employees':
                echo json_encode($this->handleEmployees());
                break;
                
            case 'clients':
                echo json_encode($this->handleClients());
                break;
                
            case 'tasks':
                echo json_encode($this->handleTasks());
                break;
                
            case 'bugs':
                echo json_encode($this->handleBugs());
                break;
                
            case 'milestones':
                echo json_encode($this->handleMilestones());
                break;
                
            // AI Optimization
            case 'ai-optimize':
                echo json_encode($this->handleAIOptimize());
                break;
                
            // Company AI Selection
            case 'available-agents':
                echo json_encode($this->getAvailableAgents());
                break;
                
            case 'assign-agent':
                echo json_encode($this->assignAgentToCompany());
                break;
                
            case 'company-agents':
                echo json_encode($this->getCompanyAgents());
                break;
                
            case 'remove-agent':
                echo json_encode($this->removeAgentFromCompany());
                break;
                
            case 'ai-chat':
                echo json_encode($this->handleAIChat());
                break;
                
            // Time Tracking
            case 'time-entries':
                echo json_encode($this->handleTimeEntries());
                break;
                
            // Reports
            case 'reports':
                echo json_encode($this->handleReports());
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['error' => 'CRM endpoint not found']);
        }
    }
    
    private function handleTenants() {
        $method = $_SERVER['REQUEST_METHOD'];
        $tenant = new Tenant();
        
        switch ($method) {
            case 'GET':
                return ['success' => true, 'tenants' => $tenant->getAll()];
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $tenant->create($data);
                return ['success' => $result, 'message' => $result ? 'Tenant created' : 'Failed to create tenant'];
                
            default:
                return ['success' => false, 'error' => 'Method not allowed'];
        }
    }
    
    private function handleCompanies() {
        $method = $_SERVER['REQUEST_METHOD'];
        $company = new Company();
        
        switch ($method) {
            case 'GET':
                $tenantId = $_GET['tenant_id'] ?? null;
                $companies = $tenantId ? $company->findByTenant($tenantId) : [];
                return ['success' => true, 'companies' => $companies];
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $company->create($data);
                return ['success' => $result, 'message' => $result ? 'Company created' : 'Failed to create company'];
                
            default:
                return ['success' => false, 'error' => 'Method not allowed'];
        }
    }
    
    private function handleProjects() {
        $method = $_SERVER['REQUEST_METHOD'];
        $project = new Project();
        
        switch ($method) {
            case 'GET':
                $companyId = $_GET['company_id'] ?? null;
                $projects = $companyId ? $project->findByCompany($companyId) : [];
                return ['success' => true, 'projects' => $projects];
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $project->create($data);
                return ['success' => $result, 'message' => $result ? 'Project created' : 'Failed to create project'];
                
            default:
                return ['success' => false, 'error' => 'Method not allowed'];
        }
    }
    
    private function handleEmployees() {
        $method = $_SERVER['REQUEST_METHOD'];
        $db = DatabaseManager::getInstance();
        
        switch ($method) {
            case 'GET':
                $companyId = $_GET['company_id'] ?? null;
                $employees = $companyId ? $db->select('employees', ['company_id' => $companyId]) : [];
                return ['success' => true, 'employees' => $employees];
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $db->insert('employees', $data);
                return ['success' => $result, 'message' => $result ? 'Employee created' : 'Failed to create employee'];
                
            default:
                return ['success' => false, 'error' => 'Method not allowed'];
        }
    }
    
    private function handleClients() {
        $method = $_SERVER['REQUEST_METHOD'];
        $db = DatabaseManager::getInstance();
        
        switch ($method) {
            case 'GET':
                $companyId = $_GET['company_id'] ?? null;
                $clients = $companyId ? $db->select('clients', ['company_id' => $companyId]) : [];
                return ['success' => true, 'clients' => $clients];
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $db->insert('clients', $data);
                return ['success' => $result, 'message' => $result ? 'Client created' : 'Failed to create client'];
                
            default:
                return ['success' => false, 'error' => 'Method not allowed'];
        }
    }
    
    private function handleTasks() {
        $method = $_SERVER['REQUEST_METHOD'];
        $db = DatabaseManager::getInstance();
        
        switch ($method) {
            case 'GET':
                $projectId = $_GET['project_id'] ?? null;
                $tasks = $projectId ? $db->select('tasks', ['project_id' => $projectId]) : [];
                return ['success' => true, 'tasks' => $tasks];
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $db->insert('tasks', $data);
                return ['success' => $result, 'message' => $result ? 'Task created' : 'Failed to create task'];
                
            case 'PUT':
                $taskId = $_GET['id'] ?? null;
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $db->update('tasks', $data, ['id' => $taskId]);
                return ['success' => $result, 'message' => $result ? 'Task updated' : 'Failed to update task'];
                
            default:
                return ['success' => false, 'error' => 'Method not allowed'];
        }
    }
    
    private function handleBugs() {
        $method = $_SERVER['REQUEST_METHOD'];
        $db = DatabaseManager::getInstance();
        
        switch ($method) {
            case 'GET':
                $projectId = $_GET['project_id'] ?? null;
                $bugs = $projectId ? $db->select('bugs', ['project_id' => $projectId]) : [];
                return ['success' => true, 'bugs' => $bugs];
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $db->insert('bugs', $data);
                return ['success' => $result, 'message' => $result ? 'Bug created' : 'Failed to create bug'];
                
            default:
                return ['success' => false, 'error' => 'Method not allowed'];
        }
    }
    
    private function handleMilestones() {
        $method = $_SERVER['REQUEST_METHOD'];
        $db = DatabaseManager::getInstance();
        
        switch ($method) {
            case 'GET':
                $projectId = $_GET['project_id'] ?? null;
                $milestones = $projectId ? $db->select('milestones', ['project_id' => $projectId]) : [];
                return ['success' => true, 'milestones' => $milestones];
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $db->insert('milestones', $data);
                return ['success' => $result, 'message' => $result ? 'Milestone created' : 'Failed to create milestone'];
                
            default:
                return ['success' => false, 'error' => 'Method not allowed'];
        }
    }
    
    private function handleAIOptimize() {
        $data = json_decode(file_get_contents('php://input'), true);
        $type = $data['type'] ?? '';
        $id = $data['id'] ?? 0;
        
        switch ($type) {
            case 'project':
                $project = new Project();
                $result = $project->aiOptimizeDescription($id);
                return ['success' => $result, 'message' => $result ? 'Project optimized' : 'Optimization failed'];
                
            case 'company':
                $company = new Company();
                $result = $company->aiOptimizeDescription($id);
                return ['success' => $result, 'message' => $result ? 'Company optimized' : 'Optimization failed'];
                
            default:
                return ['success' => false, 'error' => 'Invalid optimization type'];
        }
    }
    
    private function handleTimeEntries() {
        $method = $_SERVER['REQUEST_METHOD'];
        $db = DatabaseManager::getInstance();
        
        switch ($method) {
            case 'GET':
                $projectId = $_GET['project_id'] ?? null;
                $entries = $projectId ? $db->select('time_entries', ['project_id' => $projectId]) : [];
                return ['success' => true, 'time_entries' => $entries];
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $db->insert('time_entries', $data);
                return ['success' => $result, 'message' => $result ? 'Time entry created' : 'Failed to create time entry'];
                
            default:
                return ['success' => false, 'error' => 'Method not allowed'];
        }
    }
    
    private function handleReports() {
        $type = $_GET['type'] ?? '';
        $db = DatabaseManager::getInstance();
        
        switch ($type) {
            case 'project-summary':
                $projectId = $_GET['project_id'] ?? null;
                $project = new Project();
                $stats = $project->getStats($projectId);
                return ['success' => true, 'report' => $stats];
                
            case 'time-summary':
                $projectId = $_GET['project_id'] ?? null;
                $entries = $db->query("SELECT SUM(hours) as total_hours, COUNT(*) as entries FROM time_entries WHERE project_id = ?", [$projectId]);
                return ['success' => true, 'report' => $entries[0] ?? []];
                
            default:
                return ['success' => false, 'error' => 'Invalid report type'];
        }
    }
    
    private function getAvailableAgents() {
        $db = DatabaseManager::getInstance();
        $agents = $db->select('agents', ['status' => 'active']);
        return ['success' => true, 'agents' => $agents];
    }
    
    private function assignAgentToCompany() {
        $data = json_decode(file_get_contents('php://input'), true);
        $db = DatabaseManager::getInstance();
        
        $assignment = [
            'company_id' => $data['company_id'],
            'agent_id' => $data['agent_id'],
            'assigned_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $db->insert('company_agents', $assignment);
        return ['success' => $result, 'message' => $result ? 'Agent assigned to company' : 'Failed to assign agent'];
    }
    
    private function getCompanyAgents() {
        $companyId = $_GET['company_id'] ?? null;
        $db = DatabaseManager::getInstance();
        
        $agents = $db->query(
            "SELECT a.*, ca.assigned_at FROM agents a 
             JOIN company_agents ca ON a.id = ca.agent_id 
             WHERE ca.company_id = ? AND a.status = 'active'",
            [$companyId]
        );
        
        return ['success' => true, 'agents' => $agents];
    }
    
    private function removeAgentFromCompany() {
        $data = json_decode(file_get_contents('php://input'), true);
        $db = DatabaseManager::getInstance();
        
        $result = $db->delete('company_agents', [
            'company_id' => $data['company_id'],
            'agent_id' => $data['agent_id']
        ]);
        
        return ['success' => $result, 'message' => $result ? 'Agent removed from company' : 'Failed to remove agent'];
    }
    
    private function handleAIChat() {
        $data = json_decode(file_get_contents('php://input'), true);
        $companyId = $data['company_id'] ?? null;
        $agentId = $data['agent_id'] ?? null;
        $query = $data['query'] ?? '';
        
        if (!$companyId || !$agentId || !$query) {
            return ['success' => false, 'error' => 'Company ID, Agent ID and query required'];
        }
        
        // Get agent info
        $db = DatabaseManager::getInstance();
        $agent = $db->select('agents', ['id' => $agentId]);
        
        if (!$agent) {
            return ['success' => false, 'error' => 'Agent not found'];
        }
        
        $agent = $agent[0];
        
        // Build company context
        $company = (new Company())->findById($companyId);
        $context = "Company: {$company['name']} | Industry: {$company['industry']}\n";
        $context .= "Agent Role: {$agent['role']}\n";
        $context .= "Agent Goal: {$agent['goal']}\n";
        
        // Simple AI response (integrate with your crew system later)
        $response = "Hello! I'm {$agent['name']}, your {$agent['role']}. I understand you're asking: '{$query}'. Based on my role and your company context, I'm here to help with {$agent['goal']}. How can I assist you further?";
        
        return [
            'success' => true,
            'response' => $response,
            'agent' => $agent['name'],
            'context_used' => true
        ];
    }
}


