<?php

namespace ZeroAI\Core;

class CronManager {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->initDatabase();
    }
    
    private function initDatabase() {
        $this->db->query("CREATE TABLE IF NOT EXISTS cron_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            command TEXT NOT NULL,
            schedule TEXT NOT NULL,
            enabled INTEGER DEFAULT 1,
            last_run DATETIME,
            next_run DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    public function addJob($name, $command, $schedule) {
        $nextRun = $this->calculateNextRun($schedule);
        return $this->db->query(
            "INSERT INTO cron_jobs (name, command, schedule, next_run) VALUES (?, ?, ?, ?)",
            [$name, $command, $schedule, $nextRun]
        );
    }
    
    public function getJobs() {
        $result = $this->db->query("SELECT * FROM cron_jobs ORDER BY next_run");
        return $result[0]['data'] ?? [];
    }
    
    public function runDueJobs() {
        $now = date('Y-m-d H:i:s');
        $result = $this->db->query(
            "SELECT * FROM cron_jobs WHERE enabled = 1 AND next_run <= ?",
            [$now]
        );
        
        $jobs = $result[0]['data'] ?? [];
        foreach ($jobs as $job) {
            $this->executeJob($job);
        }
    }
    
    private function executeJob($job) {
        $output = shell_exec($job['command'] . ' 2>&1');
        $nextRun = $this->calculateNextRun($job['schedule']);
        
        $this->db->query(
            "UPDATE cron_jobs SET last_run = ?, next_run = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $nextRun, $job['id']]
        );
        
        error_log("Cron job '{$job['name']}' executed: " . $output);
    }
    
    private function calculateNextRun($schedule) {
        // Simple schedule parser: "*/5 * * * *" = every 5 minutes
        if ($schedule === '*/5 * * * *') {
            return date('Y-m-d H:i:s', strtotime('+5 minutes'));
        } elseif ($schedule === '0 * * * *') {
            return date('Y-m-d H:i:s', strtotime('+1 hour'));
        } elseif ($schedule === '0 0 * * *') {
            return date('Y-m-d H:i:s', strtotime('+1 day'));
        }
        
        // Default: 1 hour
        return date('Y-m-d H:i:s', strtotime('+1 hour'));
    }
    
    public function toggleJob($id, $enabled) {
        return $this->db->query(
            "UPDATE cron_jobs SET enabled = ? WHERE id = ?",
            [$enabled ? 1 : 0, $id]
        );
    }
    
    public function deleteJob($id) {
        return $this->db->query("DELETE FROM cron_jobs WHERE id = ?", [$id]);
    }
}
?>
