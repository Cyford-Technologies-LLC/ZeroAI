<?php
// Include main bootstrap
require_once __DIR__ . '/../../bootstrap.php';

// Load core classes
require_once __DIR__ . '/../../src/Core/CacheManager.php';
require_once __DIR__ . '/../../src/Core/SessionManager.php';
require_once __DIR__ . '/../../src/Core/System.php';
require_once __DIR__ . '/../../src/Core/DatabaseManager.php';
require_once __DIR__ . '/../../src/Core/QueueManager.php';
require_once __DIR__ . '/../../src/Core/InputValidator.php';
require_once __DIR__ . '/../../src/Core/Logger.php';
require_once __DIR__ . '/../../src/Core/VisitorTracker.php';
require_once __DIR__ . '/../../src/Models/User.php';
require_once __DIR__ . '/../../src/Models/Tenant.php';
require_once __DIR__ . '/../../src/Core/Company.php';
require_once __DIR__ . '/../../src/Core/Project.php';

// Load additional services
if (file_exists(__DIR__ . '/../../src/Core/Company.php')) {
    require_once __DIR__ . '/../../src/Core/Company.php';
}

// Initialize core systems
$cache = \ZeroAI\Core\CacheManager::getInstance();
$session = \ZeroAI\Core\SessionManager::getInstance();
$logger = \ZeroAI\Core\Logger::getInstance();

// Set up error handling
set_error_handler(function($severity, $message, $file, $line) use ($logger) {
    $logger->error("PHP Error: $message in $file:$line", ['severity' => $severity]);
    return false;
});

set_exception_handler(function($exception) use ($logger) {
    $logger->error("Uncaught Exception: " . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
});