<?php
namespace ZeroAI\Core;

class Logger {
    private static $instance = null;
    private $logPath = '/app/logs/errors.log';
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (!is_dir('/app/logs')) {
            mkdir('/app/logs', 0755, true);
        }
    }
    
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }
    
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }
    
    private function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[$timestamp] $level: $message$contextStr" . PHP_EOL;
        
        file_put_contents($this->logPath, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function getRecentLogs($lines = 50) {
        if (!file_exists($this->logPath)) {
            return [];
        }
        
        $logs = file($this->logPath, FILE_IGNORE_NEW_LINES);
        return array_reverse(array_slice($logs, -$lines));
    }
    
    public function clearLogs() {
        file_put_contents($this->logPath, '');
    }
}