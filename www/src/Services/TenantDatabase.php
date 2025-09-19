<?php
namespace ZeroAI\Services;

require_once __DIR__ . '/../bootstrap.php';

class TenantDatabase {
    private $logger;
    private $pdo;
    private $organizationId;
    
    public function __construct($dbPath, $organizationId = null) {
        try {
            $this->logger = \ZeroAI\Core\Logger::getInstance();
            $this->organizationId = $organizationId ?: basename(dirname($dbPath));
            
            if (!file_exists($dbPath)) {
                throw new \Exception("Tenant database file not found: {$dbPath}");
            }
            
            $this->pdo = new \PDO("sqlite:{$dbPath}");
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            $this->logger->debug('TenantDatabase: Connected to tenant database', [
                'org_id' => $this->organizationId,
                'db_path' => $dbPath
            ]);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('TenantDatabase: Connection failed', [
                    'org_id' => $this->organizationId,
                    'db_path' => $dbPath,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function execute($sql) {
        try {
            $result = $this->pdo->exec($sql);
            $this->logger->debug('TenantDatabase: SQL executed', [
                'org_id' => $this->organizationId,
                'affected_rows' => $result
            ]);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('TenantDatabase: SQL execution failed', [
                'org_id' => $this->organizationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    public function select($table, $where = [], $limit = null) {
        try {
            $sql = "SELECT * FROM {$table}";
            $params = [];
            
            if (!empty($where)) {
                $conditions = [];
                foreach ($where as $key => $value) {
                    $conditions[] = "{$key} = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $conditions);
            }
            
            if ($limit) {
                $sql .= " LIMIT {$limit}";
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->logger->debug('TenantDatabase: Select query executed', [
                'org_id' => $this->organizationId,
                'table' => $table,
                'count' => count($result)
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('TenantDatabase: Select query failed', [
                'org_id' => $this->organizationId,
                'table' => $table,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    public function insert($table, $data) {
        try {
            $sql = "INSERT INTO {$table} (" . implode(',', array_keys($data)) . ") VALUES (" . str_repeat('?,', count($data) - 1) . "?)";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute(array_values($data));
            
            if ($result) {
                $insertId = $this->pdo->lastInsertId();
                $this->logger->debug('TenantDatabase: Insert successful', [
                    'org_id' => $this->organizationId,
                    'table' => $table,
                    'insert_id' => $insertId
                ]);
                return $insertId;
            }
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('TenantDatabase: Insert failed', [
                'org_id' => $this->organizationId,
                'table' => $table,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    public function update($table, $data, $where) {
        try {
            $setParts = [];
            $params = [];
            foreach ($data as $key => $value) {
                $setParts[] = "{$key} = ?";
                $params[] = $value;
            }
            
            $whereParts = [];
            foreach ($where as $key => $value) {
                $whereParts[] = "{$key} = ?";
                $params[] = $value;
            }
            
            $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE " . implode(' AND ', $whereParts);
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            $this->logger->debug('TenantDatabase: Update successful', [
                'org_id' => $this->organizationId,
                'table' => $table
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('TenantDatabase: Update failed', [
                'org_id' => $this->organizationId,
                'table' => $table,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    public function delete($table, $where) {
        try {
            $whereParts = [];
            $params = [];
            foreach ($where as $key => $value) {
                $whereParts[] = "{$key} = ?";
                $params[] = $value;
            }
            
            $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereParts);
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            $this->logger->debug('TenantDatabase: Delete successful', [
                'org_id' => $this->organizationId,
                'table' => $table
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('TenantDatabase: Delete failed', [
                'org_id' => $this->organizationId,
                'table' => $table,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}