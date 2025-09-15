<?php
namespace ZeroAI\Core;

class DatabaseManager {
    private static $instance = null;
    private $db;
    private $cache;
    
    private function __construct() {
        require_once __DIR__ . '/../../config/database.php';
        $this->db = new \Database();
        $this->cache = \ZeroAI\Core\CacheManager::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function select($table, $where = [], $limit = null) {
        return $this->db->select($table, $where, $limit);
    }
    
    public function insert($table, $data) {
        return $this->db->insert($table, $data);
    }
    
    public function update($table, $data, $where) {
        return $this->db->update($table, $data, $where);
    }
    
    public function delete($table, $where) {
        return $this->db->delete($table, $where);
    }
    
    public function query($sql, $params = []) {
        // Check cache for SELECT queries
        if (stripos(trim($sql), 'SELECT') === 0) {
            $cacheKey = 'db_' . hash('sha256', $sql . serialize($params));
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null && $cached !== false) {
                return $cached;
            }
        }
        
        $pdo = $this->db->getConnection();
        if (empty($params)) {
            $stmt = $pdo->query($sql);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Cache SELECT results for 5 minutes
        if (stripos(trim($sql), 'SELECT') === 0) {
            $this->cache->set($cacheKey, $result, 300);
        }
        
        return $result;
    }
    
    public function getTokenUsage() {
        return [
            'total_tokens' => 0,
            'tokens_today' => 0,
            'cost_today' => 0.00,
            'requests_today' => 0
        ];
    }
    
    public function logTokenUsage($tokens, $cost = 0) {
        return true;
    }
}
