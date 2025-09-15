<?php
namespace ZeroAI\Core;

class QueueProcessor {
    private $queue;
    private $db;
    
    public function __construct() {
        $this->queue = QueueManager::getInstance();
        require_once __DIR__ . '/../../config/database.php';
        $this->db = new \Database();
    }
    
    public function process() {
        $processed = 0;
        $maxJobs = 100; // Process max 100 jobs per run
        
        while ($processed < $maxJobs) {
            $job = $this->queue->pop();
            if (!$job) break;
            
            try {
                $this->executeJob($job);
                $processed++;
            } catch (Exception $e) {
                error_log("Queue job failed: " . $e->getMessage());
                $this->queue->retry($job);
            }
        }
        
        return $processed;
    }
    
    private function executeJob($job) {
        $pdo = $this->db->getConnection();
        
        switch ($job['action']) {
            case 'INSERT':
                $this->insertRecord($pdo, $job['table'], $job['data']);
                break;
            case 'UPDATE':
                $this->updateRecord($pdo, $job['table'], $job['data']);
                break;
            case 'DELETE':
                $this->deleteRecord($pdo, $job['table'], $job['data']);
                break;
            default:
                throw new Exception("Unknown action: " . $job['action']);
        }
    }
    
    private function insertRecord($pdo, $table, $data) {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
    }
    
    private function updateRecord($pdo, $table, $data) {
        $id = $data['id'];
        unset($data['id']);
        
        $sets = array_map(fn($col) => "$col = ?", array_keys($data));
        $sql = "UPDATE {$table} SET " . implode(',', $sets) . " WHERE id = ?";
        
        $values = array_values($data);
        $values[] = $id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    }
    
    private function deleteRecord($pdo, $table, $data) {
        $sql = "DELETE FROM {$table} WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['id']]);
    }
}
