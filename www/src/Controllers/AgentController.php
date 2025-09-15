<?php
namespace Controllers;

use Models\Agent;

class AgentController extends BaseController {
    
    public function index() {
        $this->requireAuth();
        
        $agent = new Agent();
        $agents = $agent->getAll();
        
        $message = $_SESSION['message'] ?? null;
        unset($_SESSION['message']);
        
        $this->render('admin/agents', ['agents' => $agents, 'message' => $message]);
    }
    
    public function create() {
        $this->requireAuth();
        
        $name = $_POST['name'] ?? '';
        $role = $_POST['role'] ?? '';
        $goal = $_POST['goal'] ?? '';
        $backstory = $_POST['backstory'] ?? '';
        
        if ($name && $role && $goal && $backstory) {
            $agent = new Agent();
            if ($agent->create($name, $role, $goal, $backstory)) {
                $_SESSION['message'] = "Agent '$name' created successfully";
            } else {
                $_SESSION['message'] = "Failed to create agent - name may already exist";
            }
        }
        
        $this->redirect('/admin/agents');
    }
    
    private function requireAuth() {
        if (!isset($_SESSION['admin_logged_in'])) {
            $this->redirect('/admin');
        }
    }
}
?>
