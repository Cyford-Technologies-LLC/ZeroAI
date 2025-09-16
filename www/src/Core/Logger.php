<?php
namespace ZeroAI\Core;

class Logger {
    private static $instance = null;
    private $logPath;
    private $securityLogPath;
    private $auditLogPath;
    private $claudeLogPath;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initializePaths();
        $this->ensureLogDirectory();
    }
    
    private function initializePaths() {
        $logDir = $this->getLogDirectory();
        $this->logPath = $logDir . '/errors.log';
        $this->securityLogPath = $logDir . '/security.log';
        $this->auditLogPath = $logDir . '/audit.log';
        $this->claudeLogPath = $logDir . '/claude_debug.log';
    }
    
    private function getLogDirectory() {
        // Check if running in Docker
        if (file_exists('/app')) {
            return '/app/logs';
        }
        // Local development
        return __DIR__ . '/../../logs';
    }
    
    private function ensureLogDirectory() {
        $logDir = $this->getLogDirectory();
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                error_log('Cannot create log directory: ' . $logDir);
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
        if ($this->isDebugEnabled()) {
            $this->log('DEBUG', $message, $context);
        }
    }
    
    public function isDebugEnabled() {
        // Check system setting first
        try {
            $db = \ZeroAI\Core\DatabaseManager::getInstance();
            $db->query("CREATE TABLE IF NOT EXISTS system_settings (key TEXT PRIMARY KEY, value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
            $result = $db->query("SELECT value FROM system_settings WHERE key = 'debug_logging'");
            if (!empty($result)) {
                return $result[0]['value'] === 'true';
            }
            // Enable debug by default if no setting exists
            $db->query("INSERT OR IGNORE INTO system_settings (key, value) VALUES ('debug_logging', 'true')");
            return true;
        } catch (\Exception $e) {
            // Fall back to environment variable
        }
        
        // Default to true for debugging
        return getenv('DEBUG') !== 'false';
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


