<?php
// ZeroAI Bootstrap - Initialize all core systems

// Start output buffering for better performance
ob_start();

// Set timezone
date_default_timezone_set('UTC');

// Initialize session management
require_once __DIR__ . '/src/Core/SessionManager.php';
$sessionManager = \ZeroAI\Core\SessionManager::getInstance();
$sessionManager->start();

// Initialize cache management
require_once __DIR__ . '/src/Core/CacheManager.php';
$cacheManager = \ZeroAI\Core\CacheManager::getInstance();

// Initialize security
require_once __DIR__ . '/src/Security/InputValidator.php';

// Set error reporting
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Performance optimizations
if (extension_loaded('opcache')) {
    ini_set('opcache.enable', 1);
    ini_set('opcache.memory_consumption', 128);
    ini_set('opcache.max_accelerated_files', 4000);
}

// Memory limit
ini_set('memory_limit', '256M');

// Global error handler
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    error_log("PHP Error: $message in $file on line $line");
    return true;
});

// Global exception handler
set_exception_handler(function($exception) {
    error_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        echo "Internal Server Error";
    }
});

// Auto-flush output buffer
register_shutdown_function(function() {
    if (ob_get_level()) {
        ob_end_flush();
    }
});

