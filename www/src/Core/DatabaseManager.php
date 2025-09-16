<?php
namespace ZeroAI\Core;

// Load trait files
require_once __DIR__ . '/AgentMethods.php';
require_once __DIR__ . '/UserMethods.php';
require_once __DIR__ . '/CompanyMethods.php';
require_once __DIR__ . '/TaskMethods.php';

class DatabaseManager {
    // Load database extensions
    use AgentMethods;
    use UserMethods;
    use CompanyMethods;
    use TaskMethods;
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
    
    public function select($table, $where = [], $limit = null, $bypassCache = false) {
        return $this->db->select($table, $where, $limit);
    }
    
    public function insert($table, $data, $bypassQueue = false) {
        if ($bypassQueue) {
            // Force immediate database write, skip queue
            return $this->db->insert($table, $data);
        }
        
        // Normal flow - may use queue if configured
        $result = $this->db->insert($table, $data);
        // Clear cache for this table after insert
        $cacheKey = 'db_' . $table . '_*';
        $this->cache->clearPattern($cacheKey);
        return $result;
    }
    
    public function update($table, $data, $where, $bypassQueue = false) {
        if ($bypassQueue) {
            // Force immediate database write, skip queue
            return $this->db->update($table, $data, $where);
        }
        
        // Normal flow - may use queue if configured
        return $this->db->update($table, $data, $where);
    }
    
    public function delete($table, $where, $bypassQueue = false) {
        return $this->db->delete($table, $where);
    }
    
    public function query($sql, $params = [], $bypassCache = false) {
        // Check cache for SELECT queries
        if (!$bypassCache && stripos(trim($sql), 'SELECT') === 0) {
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
        if (!$bypassCache && stripos(trim($sql), 'SELECT') === 0) {
            $this->cache->set($cacheKey, $result, 300);
        }
        
        return $result;
    }
    
    public function executeSQL($sql, $dbName = 'main', $params = [], $bypassCache = false) {
        // Compatibility method - ignore dbName for now
        return $this->query($sql, $params, $bypassCache);
    }
    
    public function fetchColumn() {
        // For compatibility with old code expecting fetchColumn
        return 0;
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
    
    public function getAvailableDatabases() {
        $databases = [];
        $dataPaths = [
            '/app/data/',
            '../data/',
            './data/',
            __DIR__ . '/../../data/',
            __DIR__ . '/../../knowledge/'
        ];
        
        foreach ($dataPaths as $path) {
            if (is_dir($path)) {
                $files = glob($path . '*.db');
                foreach ($files as $file) {
                    $name = basename($file, '.db');
                    $databases[$file] = [
                        'name' => ucfirst($name),
                        'path' => $file,
                        'size' => filesize($file),
                        'modified' => filemtime($file)
                    ];
                }
            }
        }
        
        // Always include main database if no files found
        if (empty($databases)) {
            $mainDb = '/app/data/zeroai.db';
            $databases[$mainDb] = [
                'name' => 'Main Database',
                'path' => $mainDb,
                'size' => file_exists($mainDb) ? filesize($mainDb) : 0,
                'modified' => file_exists($mainDb) ? filemtime($mainDb) : time()
            ];
        }
        
        return $databases;
    }
}

