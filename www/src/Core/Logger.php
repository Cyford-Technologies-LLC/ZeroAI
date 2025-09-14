<?php
namespace ZeroAI\Core;

class Logger {
    private static $instance = null;
    private $logDir = '/app/logs';
    private $aiLogFile;
    private $errorLogFile;
    private $debugLogFile;
    
    private function __construct() {
        $this->ensureLogDirectory();
        $date = date('Y-m-d');
        $this->aiLogFile = $this->logDir . "/ai_$date.log";
        $this->errorLogFile = $this->logDir . "/errors_$date.log";
        $this->debugLogFile = $this->logDir . "/debug_$date.log";
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function ensureLogDirectory(): void {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }
    }
    
    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context, $this->aiLogFile);
    }
    
    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context, $this->errorLogFile);
        // Also log to AI log for visibility
        $this->log('ERROR', $message, $context, $this->aiLogFile);
    }
    
    public function debug(string $message, array $context = []): void {
        $this->log('DEBUG', $message, $context, $this->debugLogFile);
    }
    
    public function aiLog(string $agent, string $action, array $data = []): void {
        $context = array_merge(['agent' => $agent, 'action' => $action], $data);
        $this->log('AI', $action, $context, $this->aiLogFile);
    }
    
    public function claudeLog(string $message, array $context = []): void {
        $context['agent'] = 'claude';
        $this->log('CLAUDE', $message, $context, $this->aiLogFile);
    }
    
    public function systemLog(string $message, array $context = []): void {
        $context['component'] = 'system';
        $this->log('SYSTEM', $message, $context, $this->aiLogFile);
    }
    
    private function log(string $level, string $message, array $context, string $file): void {
        try {
            $timestamp = date('Y-m-d H:i:s');
            $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
            $logEntry = "[$timestamp] [$level] $message$contextStr\n";
            
            file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            error_log("Logging failed: " . $e->getMessage());
        }
    }
    
    public function getRecentLogs(string $type = 'ai', int $lines = 100): array {
        try {
            $file = match($type) {
                'ai' => $this->aiLogFile,
                'error' => $this->errorLogFile,
                'debug' => $this->debugLogFile,
                default => $this->aiLogFile
            };
            
            if (!file_exists($file)) return [];
            
            $logs = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            return array_slice($logs, -$lines);
        } catch (\Exception $e) {
            return ["Error reading logs: " . $e->getMessage()];
        }
    }
    
    public function searchLogs(string $query, string $type = 'ai', int $days = 7): array {
        try {
            $results = [];
            $currentDate = new \DateTime();
            
            for ($i = 0; $i < $days; $i++) {
                $date = clone $currentDate;
                $date->sub(new \DateInterval('P' . $i . 'D'));
                $dateStr = $date->format('Y-m-d');
                
                $file = $this->logDir . "/{$type}_{$dateStr}.log";
                if (file_exists($file)) {
                    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        if (stripos($line, $query) !== false) {
                            $results[] = $line;
                        }
                    }
                }
            }
            
            return $results;
        } catch (\Exception $e) {
            return ["Error searching logs: " . $e->getMessage()];
        }
    }
    
    public function clearOldLogs(int $daysToKeep = 30): int {
        try {
            $deleted = 0;
            $cutoffDate = new \DateTime();
            $cutoffDate->sub(new \DateInterval('P' . $daysToKeep . 'D'));
            
            $files = glob($this->logDir . '/*.log');
            foreach ($files as $file) {
                $fileDate = filemtime($file);
                if ($fileDate < $cutoffDate->getTimestamp()) {
                    unlink($file);
                    $deleted++;
                }
            }
            
            return $deleted;
        } catch (\Exception $e) {
            $this->error('Log cleanup failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }
}