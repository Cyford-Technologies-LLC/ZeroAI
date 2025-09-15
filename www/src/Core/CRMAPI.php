<?php
namespace ZeroAI\Core;

class CRMAPI {
    public function __construct() {
        // Ensure CompanyAI table exists
        $this->initializeAITables();
    }
    
    private function initializeAITables() {
        $db = DatabaseManager::getInstance();
        $db->query("CREATE TABLE IF NOT EXISTS company_ai (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            ai_name VARCHAR(255) NOT NULL,
            base_prompt TEXT,
            model_preference VARCHAR(100),
            capabilities JSON,
            knowledge_sources JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id)
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
                
            // Company AI
            case 'company-ai':
                echo json_encode($this->handleCompanyAI());
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
    
    private function handleCompanyAI() {
        $method = $_SERVER['REQUEST_METHOD'];
        $companyAI = new CompanyAI();
        
        switch ($method) {
            case 'GET':
                $companyId = $_GET['company_id'] ?? null;
                $ai = $companyAI->getCompanyAI($companyId);
                return ['success' => true, 'ai' => $ai];
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $companyAI->createCompanyAI($data['company_id'], $data['config']);
                return ['success' => $result, 'message' => $result ? 'Company AI created' : 'Failed to create AI'];
                
            default:
                return ['success' => false, 'error' => 'Method not allowed'];
        }
    }
    
    private function handleAIChat() {
        $data = json_decode(file_get_contents('php://input'), true);
        $companyId = $data['company_id'] ?? null;
        $query = $data['query'] ?? '';
        
        if (!$companyId || !$query) {
            return ['success' => false, 'error' => 'Company ID and query required'];
        }
        
        $companyAI = new CompanyAI();
        return $companyAI->processQuery($companyId, $query);
    }
}