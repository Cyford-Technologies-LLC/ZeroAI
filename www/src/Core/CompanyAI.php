<?php
namespace ZeroAI\Core;

class CompanyAI {
    private $db;
    private $company;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    public function createCompanyAI($companyId, $config) {
        // Create AI configuration for company
        $aiConfig = [
            'company_id' => $companyId,
            'ai_name' => $config['name'] ?? 'Company Assistant',
            'base_prompt' => $this->buildBasePrompt($companyId, $config),
            'model_preference' => $config['model'] ?? 'llama3.2:1b',
            'capabilities' => json_encode($config['capabilities'] ?? ['project_management', 'task_automation']),
            'knowledge_sources' => json_encode($this->buildKnowledgeSources($companyId)),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('company_ai', $aiConfig);
    }
    
    private function buildBasePrompt($companyId, $config) {
        $company = (new Company())->findById($companyId);
        $projects = (new Project())->findByCompany($companyId);
        
        $prompt = "You are {$config['name']}, an AI assistant for {$company['name']}.\n\n";
        $prompt .= "COMPANY CONTEXT:\n";
        $prompt .= "- Industry: {$company['industry']}\n";
        $prompt .= "- Description: {$company['description']}\n";
        $prompt .= "- Active Projects: " . count($projects) . "\n\n";
        
        $prompt .= "YOUR CAPABILITIES:\n";
        foreach ($config['capabilities'] ?? [] as $capability) {
            $prompt .= "- " . ucfirst(str_replace('_', ' ', $capability)) . "\n";
        }
        
        $prompt .= "\nYou have access to company projects, tasks, employees, and can help with:\n";
        $prompt .= "- Project planning and management\n";
        $prompt .= "- Task creation and assignment\n";
        $prompt .= "- Progress tracking and reporting\n";
        $prompt .= "- Team coordination\n";
        $prompt .= "- Bug tracking and resolution\n\n";
        
        $prompt .= "Always provide helpful, accurate information based on the company's current data.";
        
        return $prompt;
    }
    
    private function buildKnowledgeSources($companyId) {
        return [
            'projects' => "/api/crm/projects?company_id={$companyId}",
            'employees' => "/api/crm/employees?company_id={$companyId}",
            'tasks' => "/api/crm/tasks?company_id={$companyId}",
            'clients' => "/api/crm/clients?company_id={$companyId}",
            'company_data' => "/api/crm/companies/{$companyId}"
        ];
    }
    
    public function getCompanyAI($companyId) {
        $result = $this->db->select('company_ai', ['company_id' => $companyId]);
        return $result ? $result[0] : null;
    }
    
    public function processQuery($companyId, $query) {
        $aiConfig = $this->getCompanyAI($companyId);
        if (!$aiConfig) {
            return ['error' => 'No AI configured for this company'];
        }
        
        // Build context from knowledge sources
        $context = $this->gatherContext($companyId);
        
        // Create enhanced prompt
        $fullPrompt = $aiConfig['base_prompt'] . "\n\nCURRENT CONTEXT:\n" . $context . "\n\nUSER QUERY: " . $query;
        
        // Process with AI (using existing crew system)
        return $this->processWithCrew($companyId, $fullPrompt, $aiConfig);
    }
    
    private function gatherContext($companyId) {
        $context = "";
        
        // Get recent projects
        $projects = $this->db->executeSQL("SELECT name, status, progress FROM projects WHERE company_id = ? ORDER BY updated_at DESC LIMIT 5", [$companyId]);
        if ($projects) {
            $context .= "Recent Projects:\n";
            foreach ($projects as $project) {
                $context .= "- {$project['name']}: {$project['status']} ({$project['progress']}%)\n";
            }
        }
        
        // Get active tasks
        $tasks = $this->db->executeSQL("SELECT name, status, priority FROM tasks t JOIN projects p ON t.project_id = p.id WHERE p.company_id = ? AND t.status != 'done' LIMIT 10", [$companyId]);
        if ($tasks) {
            $context .= "\nActive Tasks:\n";
            foreach ($tasks as $task) {
                $context .= "- {$task['name']}: {$task['status']} (Priority: {$task['priority']})\n";
            }
        }
        
        return $context;
    }
    
    private function processWithCrew($companyId, $prompt, $aiConfig) {
        // Create dynamic crew configuration similar to your existing system
        $crewConfig = [
            'project_id' => "company_{$companyId}",
            'prompt' => $prompt,
            'category' => 'company_ai',
            'model_preference' => $aiConfig['model_preference'],
            'capabilities' => json_decode($aiConfig['capabilities'], true)
        ];
        
        // This would integrate with your existing crew system
        // For now, return a mock response
        return [
            'success' => true,
            'response' => "AI Assistant Response: I understand your query about the company. Based on the current context, I can help you with project management and task coordination.",
            'model_used' => $aiConfig['model_preference'],
            'context_used' => true
        ];
    }
}
