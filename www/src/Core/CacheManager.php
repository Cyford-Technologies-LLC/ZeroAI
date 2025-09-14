<?php

namespace ZeroAI\Core;

class CacheManager {
    private $redis;
    private $enabled = false;
    
    public function __construct() {
        if (extension_loaded('redis')) {
            try {
                $this->redis = new \Redis();
                $this->redis->connect('127.0.0.1', 6379);
                $this->enabled = true;
            } catch (\Exception $e) {
                error_log("Redis connection failed: " . $e->getMessage());
            }
        }
    }
    
    public function set($key, $value, $ttl = 3600) {
        if (!$this->enabled) return false;
        return $this->redis->setex($key, $ttl, serialize($value));
    }
    
    public function get($key) {
        if (!$this->enabled) return null;
        $value = $this->redis->get($key);
        return $value ? unserialize($value) : null;
    }
    
    public function delete($key) {
        if (!$this->enabled) return false;
        return $this->redis->del($key);
    }
    
    public function exists($key) {
        if (!$this->enabled) return false;
        return $this->redis->exists($key);
    }
    
    public function isEnabled() {
        return $this->enabled;
    }
}
?>