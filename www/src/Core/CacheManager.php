<?php
namespace ZeroAI\Core;

class CacheManager {
    private static $instance = null;
    private $redis = null;
    
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
                $this->redis = null;
            }
        }
    }
    
    public function get($key) {
        // Try APCu first
        if (extension_loaded('apcu') && apcu_exists($key)) {
            return apcu_fetch($key);
        }
        
        // Try Redis
        if ($this->redis) {
            try {
                $value = $this->redis->get($key);
                if ($value !== false) {
                    return unserialize($value);
                }
            } catch (Exception $e) {
                error_log("Redis get failed: " . $e->getMessage());
            }
        }
        
        return null;
    }
    
    public function set($key, $value, $ttl = 3600) {
        try {
            // Store in APCu for fast access
            if (extension_loaded('apcu')) {
                apcu_store($key, $value, $ttl);
            }
            
            // Store in Redis for persistence
            if ($this->redis) {
                $this->redis->setex($key, $ttl, serialize($value));
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Cache set failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete($key) {
        if (extension_loaded('apcu')) {
            apcu_delete($key);
        }
        
        if ($this->redis) {
            $this->redis->del($key);
        }
        
        return true;
    }
    
    public function flush() {
        if (extension_loaded('apcu')) {
            apcu_clear_cache();
        }
        
        if ($this->redis) {
            $this->redis->flushAll();
        }
        
        return true;
    }
    
    public function clearPattern($pattern) {
        if ($this->redis) {
            $keys = $this->redis->keys($pattern);
            if ($keys) {
                $this->redis->del($keys);
            }
        }
        return true;
    }
}