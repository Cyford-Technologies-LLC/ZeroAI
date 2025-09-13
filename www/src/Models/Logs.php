<?php
namespace Models;

use Core\System;

class Logs {
    private $system;
    private $logger;
    
    public function __construct() {
        $this->system = System::getInstance();
        $this->logger = $this->system->getLogger();
    }
    
    public function getRecentLogs(string $type = 'ai', int $lines = 100): array {
        return $this->logger->getRecentLogs($type, $lines);
    }
    
    public function searchLogs(string $query, string $type = 'ai', int $days = 7): array {
        return $this->logger->searchLogs($query, $type, $days);
    }
    
    public function getErrorLogs(int $lines = 100): array {
        return $this->logger->getRecentLogs('error', $lines);
    }
    
    public function getAILogs(int $lines = 100): array {
        return $this->logger->getRecentLogs('ai', $lines);
    }
    
    public function getDebugLogs(int $lines = 100): array {
        return $this->logger->getRecentLogs('debug', $lines);
    }
    
    public function clearOldLogs(int $daysToKeep = 30): int {
        return $this->logger->clearOldLogs($daysToKeep);
    }
    
    public function getSystemLogs(): array {
        try {
            // Get system logs from various sources
            $logs = [];
            
            // Docker logs
            $dockerLogs = shell_exec('docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" 2>&1');
            if ($dockerLogs) {
                $logs['docker'] = explode("\n", trim($dockerLogs));
            }
            
            // System status
            $logs['system_status'] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'memory_usage' => memory_get_usage(true),
                'disk_free' => disk_free_space('/app'),
                'load_average' => sys_getloadavg()
            ];
            
            return $logs;
        } catch (\Exception $e) {
            $this->system->getLogger()->error('Failed to get system logs', ['error' => $e->getMessage()]);
            return [];
        }
    }
}