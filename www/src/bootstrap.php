<?php
/**
 * ZeroAI Bootstrap
 * Uses autoloader to load classes and initializes system
 */

// Load autoloader first
require_once __DIR__ . '/autoload.php';

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Initialize core classes using autoloader
$logger = \ZeroAI\Core\Logger::getInstance();

// Set up error handling
set_error_handler(function($severity, $message, $file, $line) use ($logger) {
    $logger->error("PHP Error: $message", [
        'file' => $file,
        'line' => $line,
        'severity' => $severity
    ]);
    
    if (getenv('ENVIRONMENT') !== 'development') {
        throw new \ZeroAI\Core\SecurityException('Internal server error', 500);
    }
    
    return false;
});

// Set up exception handler
set_exception_handler(function($exception) use ($logger) {
    if ($exception instanceof \ZeroAI\Core\SecurityException) {
        $logger->logSecurity($exception->getMessage(), 'high');
        http_response_code($exception->getCode() ?: 500);
        echo json_encode(['error' => 'Security error occurred']);
    } else {
        $logger->error('Unhandled exception: ' . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
    
    exit;
});

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = \ZeroAI\Core\InputValidator::generateCSRFToken();
}

// Set secure PHP configuration
ini_set('expose_php', 0);
ini_set('display_errors', getenv('ENVIRONMENT') === 'development' ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', '/app/logs/php_errors.log');


