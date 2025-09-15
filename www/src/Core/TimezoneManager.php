<?php

namespace ZeroAI\Core;

class TimezoneManager {
    private static $instance = null;
    private $timezone = 'America/New_York';
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        $this->loadTimezone();
        $this->applyTimezone();
    }
    
    private function loadTimezone() {
        try {
            $db = DatabaseManager::getInstance();
            $db->query("CREATE TABLE IF NOT EXISTS system_settings (key TEXT PRIMARY KEY, value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)", 'main');
            $result = $db->query("SELECT value FROM system_settings WHERE key = 'timezone'");
            
            if (!empty($result)) {
                $this->timezone = $result[0]['value'];
            }
        } catch (\Exception $e) {
            // Use default timezone if database not available
        }
    }
    
    public function applyTimezone() {
        // Set PHP timezone
        date_default_timezone_set($this->timezone);
        
        // Set environment variable for subprocesses
        putenv("TZ={$this->timezone}");
        
        // Write timezone file for Docker containers
        file_put_contents('/app/.env.timezone', "TZ={$this->timezone}\n");
    }
    
    public function getTimezone() {
        return $this->timezone;
    }
    
    public function setTimezone($timezone) {
        $this->timezone = $timezone;
        $this->applyTimezone();
        
        // Save to database
        try {
            $db = DatabaseManager::getInstance();
            $db->query("CREATE TABLE IF NOT EXISTS system_settings (key TEXT PRIMARY KEY, value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)", 'main');
            $db->query("INSERT OR REPLACE INTO system_settings (key, value) VALUES ('timezone', '$timezone')", 'main');
        } catch (\Exception $e) {
            error_log("Failed to save timezone setting: " . $e->getMessage());
        }
    }
    
    public function formatTimestamp($timestamp = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        return date('Y-m-d H:i:s T', $timestamp);
    }
    
    public function getCurrentTime() {
        return $this->formatTimestamp();
    }
}
?>


