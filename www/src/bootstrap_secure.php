<?php
/**
 * Secure Bootstrap for ZeroAI
 * Initializes all security measures and error handling
 */

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Load core security classes
require_once __DIR__ . '/Core/InputValidator.php';
require_once __DIR__ . '/Core/SecurityException.php';
require_once __DIR__ . '/Core/Logger.php';
require_once __DIR__ . '/Core/SecurityMiddleware.php';
require_once __DIR__ . '/Core/CacheManager.php';

use ZeroAI\Core\SecurityMiddleware;
use ZeroAI\Core\SecurityException;
use ZeroAI\Core\Logger;
use ZeroAI\Core\InputValidator;

// Set up error handling
set_error_handler(function($severity, $message, $file, $line) {
    $logger = Logger::getInstance();
    $logger->error("PHP Error: $message", [
        'file' => $file,
        'line' => $line,
        'severity' => $severity
    ]);
    
    // Don't expose internal errors in production
    if (getenv('ENVIRONMENT') !== 'development') {
        throw new SecurityException('Internal server error', 500);
    }
    
    return false; // Let PHP handle it normally in development
});

// Set up exception handler
set_exception_handler(function($exception) {
    $logger = Logger::getInstance();
    
    if ($exception instanceof SecurityException) {
        $logger->logSecurity($exception->getMessage(), $exception->getSecurityLevel());
        
        // Send appropriate HTTP status
        http_response_code($exception->getCode() ?: 500);
        
        if (getenv('ENVIRONMENT') === 'development') {
            echo json_encode([
                'error' => $exception->getMessage(),
                'type' => 'SecurityException',
                'code' => $exception->getCode()
            ]);
        } else {
            echo json_encode(['error' => 'Security error occurred']);
        }
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

// Initialize security middleware
try {
    $security = SecurityMiddleware::getInstance();
    
    // Skip security checks for certain paths
    $skipPaths = ['/health', '/status', '/favicon.ico'];
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    
    if (!in_array($currentPath, $skipPaths)) {
        $security->checkRequest();
    }
    
} catch (SecurityException $e) {
    // Security middleware will handle this
    throw $e;
} catch (Exception $e) {
    $logger = Logger::getInstance();
    $logger->logSecurity('Security middleware initialization failed: ' . $e->getMessage(), 'critical');
    throw new SecurityException('Security initialization failed', 500, 'critical');
}

// Generate CSRF token for forms
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = InputValidator::generateCSRFToken();
}

// Set secure PHP configuration
ini_set('expose_php', 0);
ini_set('display_errors', getenv('ENVIRONMENT') === 'development' ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', '/app/logs/php_errors.log');

// Disable dangerous functions in production
if (getenv('ENVIRONMENT') !== 'development') {
    ini_set('disable_functions', 'exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source');
}
