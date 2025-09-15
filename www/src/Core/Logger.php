<?php
namespace ZeroAI\Core;

class Logger {
    private static $instance = null;
    private $logPath = '/app/logs/errors.log';
    private $securityLogPath = '/app/logs/security.log';
    private $auditLogPath = '/app/logs/audit.log';
    private $claudeLogPath = '/app/logs/claude_debug.log';
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->ensureLogDirectory();
    }
    
    private function ensureLogDirectory() {
        $logDir = '/app/logs';
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                error_log('Cannot create log directory');
            }
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
    
    public function debug($message, $context = []) {
        if (getenv('DEBUG') === 'true') {
            $this->log('DEBUG', $message, $context);
        }
    }
    
    private function log($level, $message, $context = [], $logFile = null) {
        $logFile = $logFile ?: $this->logPath;
        
        // Skip validation for log files - they're internal
        if (!$logFile || !is_string($logFile)) {
            return false;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $this->getClientIP();
        $user = $_SESSION['admin_user'] ?? 'anonymous';
        
        // Sanitize message and context to prevent log injection
        $message = InputValidator::sanitize($message);
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        
        $logEntry = "[$timestamp] [$ip] [$user] $level: $message$contextStr" . PHP_EOL;
        
        return file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) !== false;
    }
    
    public function logSecurity($message, $level = 'medium', $context = []) {
        $securityContext = array_merge($context, [
            'level' => $level,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ]);
        
        $this->log('SECURITY', $message, $securityContext, $this->securityLogPath);
        
        // Also log to main log if high severity
        if (in_array($level, ['high', 'critical'])) {
            $this->error("SECURITY ALERT: $message", $securityContext);
        }
    }
    
    public function logAudit($action, $resource, $context = []) {
        $auditContext = array_merge($context, [
            'action' => $action,
            'resource' => $resource,
            'timestamp' => time()
        ]);
        
        $this->log('AUDIT', "$action on $resource", $auditContext, $this->auditLogPath);
    }
    
    public function logClaude($message, $context = []) {
        $claudeContext = array_merge($context, [
            'component' => 'claude',
            'timestamp' => time()
        ]);
        
        $this->log('CLAUDE', $message, $claudeContext, $this->claudeLogPath);
    }
    
    public function getRecentLogs($lines = 50, $logType = 'error') {
        $logFile = $this->getLogFile($logType);
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $logs = file($logFile, FILE_IGNORE_NEW_LINES);
        if ($logs === false) {
            return [];
        }
        
        return array_reverse(array_slice($logs, -$lines));
    }
    
    public function clearLogs($logType = 'error') {
        $logFile = $this->getLogFile($logType);
        
        if ($logFile && is_string($logFile)) {
            file_put_contents($logFile, '');
            $this->logAudit('CLEAR_LOGS', $logType);
        }
    }
    
    private function getLogFile($type) {
        switch ($type) {
            case 'security':
                return $this->securityLogPath;
            case 'audit':
                return $this->auditLogPath;
            case 'claude':
                return $this->claudeLogPath;
            default:
                return $this->logPath;
        }
    }
    
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}


