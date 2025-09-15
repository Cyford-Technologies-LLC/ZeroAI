<?php
namespace ZeroAI\Models;

abstract class BaseModel {
    protected $db;
    protected $table;
    
    public function __construct() {
        $this->db = new \ZeroAI\Core\DatabaseManager();
    }
    
    protected function executeSQL(string $sql, string $dbName = 'main'): array {
        $dbManager = new \ZeroAI\Core\DatabaseManager();
        return $dbManager->query($sql, $dbName);
    }
    
    public function find(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = $id LIMIT 1";
        $result = $this->query($sql);
        return $result[0]['data'][0] ?? null;
    }
    
    public function findAll(): array {
        $sql = "SELECT * FROM {$this->table}";
        $result = $this->query($sql);
        return $result[0]['data'] ?? [];
    }
    
    public function create(array $data): bool {
        $columns = implode(', ', array_keys($data));
        $values = "'" . implode("', '", array_values($data)) . "'";
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($values)";
        $result = $this->query($sql);
        return !isset($result[0]['error']);
    }
    
    public function update(int $id, array $data): bool {
        $sets = [];
        foreach ($data as $key => $value) {
            $sets[] = "$key = '$value'";
        }
        $setClause = implode(', ', $sets);
        $sql = "UPDATE {$this->table} SET $setClause WHERE id = $id";
        $result = $this->query($sql);
        return !isset($result[0]['error']);
    }
    
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id = $id";
        $result = $this->query($sql);
        return !isset($result[0]['error']);
    }
}
