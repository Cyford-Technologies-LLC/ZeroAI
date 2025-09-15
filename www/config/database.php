<?php
require_once __DIR__ . '/../src/Core/CacheManager.php';
require_once __DIR__ . '/../src/Core/QueueManager.php';

class Database {
    private $db_path = '/app/data/zeroai.db';
    private $pdo;
    private $cache;
    private $queue;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("sqlite:" . $this->db_path);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->cache = \ZeroAI\Core\CacheManager::getInstance();
            $this->queue = \ZeroAI\Core\QueueManager::getInstance();
            $this->initTables();
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function initTables() {
        $adminPassword = password_hash(getenv('ADMIN_PASSWORD') ?: 'admin123', PASSWORD_DEFAULT);
        $userPassword = password_hash('user123', PASSWORD_DEFAULT);
        
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS agents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            role TEXT NOT NULL,
            goal TEXT NOT NULL,
            backstory TEXT NOT NULL,
            config TEXT NOT NULL,
            is_core BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        INSERT OR IGNORE INTO users (username, password, role) VALUES 
        ('admin', '$adminPassword', 'admin'),
        ('user', '$userPassword', 'user');
        
        INSERT OR IGNORE INTO agents (name, role, goal, backstory, config, is_core) VALUES 
        ('Team Manager', 'Team Coordination Specialist', 'Coordinate team activities', 'Expert in team management', '{\"tools\": [\"delegate_tool\"], \"memory\": true}', 1),
        ('Project Manager', 'Project Management Expert', 'Oversee project execution', 'Experienced project manager', '{\"tools\": [\"file_tool\"], \"memory\": true}', 1),
        ('Prompt Refinement Agent', 'Prompt Optimization Specialist', 'Refine prompts for better responses', 'Expert in prompt engineering', '{\"tools\": [\"learning_tool\"], \"memory\": true}', 1);
        ";
        
        $this->pdo->exec($sql);
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    // Enhanced read with Redis cache
    public function select($table, $where = [], $limit = null) {
        $cacheKey = $this->buildCacheKey($table, $where, $limit);
        
        // Try Redis first (2 second timeout)
        try {
            $result = $this->cache->get($cacheKey);
            if ($result !== false) {
                return $result;
            }
        } catch (Exception $e) {
            // Redis timeout, continue to database
            error_log("Redis timeout: " . $e->getMessage());
        }
        
        // Fallback to database
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
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cache result for 5 minutes
        $this->cache->set($cacheKey, $result, 300);
        
        return $result;
    }
    
    // Enhanced write with Redis + Queue
    public function insert($table, $data) {
        // Try queue first
        $queued = $this->queue->push($table, $data, 'INSERT');
        
        if (!$queued) {
            // Fallback to direct database write
            $sql = "INSERT INTO {$table} (" . implode(',', array_keys($data)) . ") VALUES (" . str_repeat('?,', count($data) - 1) . "?)";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute(array_values($data));
            
            if ($result) {
                $data['id'] = $this->pdo->lastInsertId();
            }
        } else {
            // Simulate ID for immediate use
            $data['id'] = time() . rand(1000, 9999);
        }
        
        // Update Redis cache immediately
        $this->updateCacheAfterWrite($table, $data, 'INSERT');
        
        return $data['id'] ?? true;
    }
    
    public function update($table, $data, $where) {
        // Try queue first
        $queueData = ['data' => $data, 'where' => $where];
        $queued = $this->queue->push($table, $queueData, 'UPDATE');
        
        if (!$queued) {
            // Fallback to direct database write
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
            $stmt->execute($params);
        }
        
        // Update Redis cache immediately
        $this->updateCacheAfterWrite($table, array_merge($data, $where), 'UPDATE');
        
        return true;
    }
    
    public function delete($table, $where) {
        // Try queue first
        $queued = $this->queue->push($table, $where, 'DELETE');
        
        if (!$queued) {
            // Fallback to direct database write
            $whereParts = [];
            $params = [];
            foreach ($where as $key => $value) {
                $whereParts[] = "{$key} = ?";
                $params[] = $value;
            }
            
            $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereParts);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        // Update Redis cache immediately
        $this->updateCacheAfterWrite($table, $where, 'DELETE');
        
        return true;
    }
    
    private function buildCacheKey($table, $where = [], $limit = null) {
        $key = "db:{$table}";
        if (!empty($where)) {
            $key .= ':' . hash('sha256', serialize($where));
        }
        if ($limit) {
            $key .= ":limit:{$limit}";
        }
        return $key;
    }
    
    private function invalidateTableCache($table) {
        // Clear all cache entries for this table
        try {
            $this->cache->clearPattern("db:{$table}*");
        } catch (Exception $e) {
            // Redis error, log but continue
            error_log("Redis cache invalidation failed: " . $e->getMessage());
        }
    }
    
    private function updateCacheAfterWrite($table, $data, $action) {
        try {
            // Invalidate table cache
            $this->invalidateTableCache($table);
            
            // For INSERT/UPDATE, pre-populate cache with new data
            if ($action === 'INSERT' && isset($data['id'])) {
                $cacheKey = "db:{$table}:id:" . $data['id'];
                $this->cache->set($cacheKey, [$data], 300);
            }
            
        } catch (Exception $e) {
            error_log("Cache update failed: " . $e->getMessage());
        }
    }
}
?>