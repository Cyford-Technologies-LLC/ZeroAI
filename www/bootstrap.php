<?php
// ZeroAI Application Bootstrap

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Define paths
define('ROOT_PATH', __DIR__);
define('SRC_PATH', ROOT_PATH . '/src');
define('DATA_PATH', '/app/data');

// Autoloader for ZeroAI classes
spl_autoload_register(function ($className) {
    // Convert namespace to file path
    $className = str_replace('ZeroAI\\', '', $className);
    $file = SRC_PATH . '/' . str_replace('\\', '/', $className) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    return false;
});

// Load environment variables
if (file_exists('/app/.env')) {
    $lines = file('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Initialize core system
use ZeroAI\Core\System;
use ZeroAI\Core\DatabaseManager;

try {
    $system = new System();
    $db = new DatabaseManager();
    
    // Ensure data directory exists
    if (!is_dir(DATA_PATH)) {
        mkdir(DATA_PATH, 0755, true);
    }
    
} catch (Exception $e) {
    error_log("Bootstrap error: " . $e->getMessage());
}