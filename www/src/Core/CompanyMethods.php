<?php
namespace ZeroAI\Core;

trait CompanyMethods {
    
    public function getAllCompanies($bypassCache = false) {
        return $this->query('SELECT * FROM companies ORDER BY id', [], $bypassCache);
    }
    
    public function getCompany($id, $bypassCache = false) {
        return $this->query('SELECT * FROM companies WHERE id = ?', [$id], $bypassCache)[0] ?? null;
    }
    
    public function createCompany($data, $bypassQueue = false) {
        return $this->insert('companies', $data, $bypassQueue);
    }
    
    public function updateCompany($id, $data, $bypassQueue = false) {
        return $this->update('companies', $data, ['id' => $id], $bypassQueue);
    }
    
    public function deleteCompany($id, $bypassQueue = false) {
        return $this->delete('companies', ['id' => $id], $bypassQueue);
    }
    
    public function getActiveCompanies($bypassCache = false) {
        return $this->query('SELECT * FROM companies WHERE status = "active" ORDER BY id', [], $bypassCache);
    }
}
?>