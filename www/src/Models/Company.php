<?php
namespace ZeroAI\Models;

class Company {
    private $db;
    
    public function __construct() {
        require_once __DIR__ . '/../../config/database.php';
        $this->db = new \Database();
    }
    
    public function create($data) {
        $data['secret_key'] = bin2hex(random_bytes(32));
        $data['slug'] = $this->generateSlug($data['name']);
        return $this->db->insert('companies', $data);
    }
    
    public function findById($id) {
        $result = $this->db->select('companies', ['id' => $id]);
        return $result ? $result[0] : null;
    }
    
    public function findByTenant($orgId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE organization_id = ?");
        $stmt->execute([$orgId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAll() {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->executeSQL("SELECT * FROM companies ORDER BY created_at DESC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('companies', $data, ['id' => $id]);
    }
    
    public function aiOptimizeDescription($id) {
        $company = $this->findById($id);
        if (!$company) return false;
        
        // AI optimization logic here
        $aiDescription = "AI-optimized: " . $company['description'];
        return $this->update($id, ['ai_description' => $aiDescription]);
    }
    
    private function generateSlug($name) {
        return strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
    }
}
