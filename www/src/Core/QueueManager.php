<?php
namespace ZeroAI\Core;

class QueueManager {
    private static $instance = null;
    private $redis = null;
    private $queueKey = 'zeroai:queue';
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (extension_loaded('redis')) {
            $this->redis = new \Redis();
            try {
                $this->redis->connect('127.0.0.1', 6379);
            } catch (Exception $e) {
                error_log("Redis connection failed: " . $e->getMessage());
            }
        }
    }
    
    public function push($table, $data, $action = 'INSERT') {
        if (!$this->redis) return false;
        
        $job = [
            'id' => uniqid(),
            'table' => $table,
            'action' => $action,
            'data' => $data,
            'timestamp' => time(),
            'retries' => 0
        ];
        
        return $this->redis->lpush($this->queueKey, json_encode($job));
    }
    
    public function pop() {
        if (!$this->redis) return null;
        
        $job = $this->redis->brpop([$this->queueKey], 1);
        if ($job) {
            return json_decode($job[1], true);
        }
        return null;
    }
    
    public function size() {
        if (!$this->redis) return 0;
        return $this->redis->llen($this->queueKey);
    }
    
    public function retry($job) {
        if (!$this->redis) return false;
        
        $job['retries']++;
        if ($job['retries'] < 3) {
            return $this->redis->lpush($this->queueKey, json_encode($job));
        }
        
        // Log failed job
        error_log("Queue job failed after 3 retries: " . json_encode($job));
        return false;
    }
    
    public function clear() {
        if (!$this->redis) return false;
        return $this->redis->del($this->queueKey);
    }
}


