<?php
namespace ZeroAI\Core;

class SessionManager {
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
                $this->setupRedisSession();
            } catch (Exception $e) {
                $this->redis = null;
            }
        }
    }
    
    private function setupRedisSession() {
        if ($this->redis && session_status() === PHP_SESSION_NONE) {
            ini_set('session.save_handler', 'redis');
            ini_set('session.save_path', 'tcp://127.0.0.1:6379');
        }
    }
    
    public function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function destroy() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
    
    public function regenerate() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
    
    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    public function has($key) {
        return isset($_SESSION[$key]);
    }
    
    public function remove($key) {
        unset($_SESSION[$key]);
    }
}