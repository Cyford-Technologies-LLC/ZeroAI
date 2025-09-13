<?php

namespace ZeroAI\Services;

use ZeroAI\Core\System;

class SettingsService {
    private $system;
    
    public function __construct() {
        $this->system = new System();
    }
    
    public function saveSettings($settings) {
        foreach ($settings as $key => $value) {
            $_SESSION[$key] = $value;
        }
        
        if (isset($settings['display_errors'])) {
            ini_set('display_errors', $settings['display_errors'] ? 1 : 0);
        }
        
        return true;
    }
    
    public function getSystemInfo() {
        return [
            'php_version' => phpversion(),
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'],
            'error_display' => isset($_SESSION['display_errors']) && $_SESSION['display_errors'] ? 'Enabled' : 'Disabled',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ];
    }
    
    public function getDebugSettings() {
        return [
            'display_errors' => isset($_SESSION['display_errors']) && $_SESSION['display_errors']
        ];
    }
}