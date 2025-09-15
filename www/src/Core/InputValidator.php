<?php
namespace ZeroAI\Core;

class InputValidator {
    private static $allowedPaths = ['/app', '/tmp/zeroai'];
    private static $allowedExtensions = ['txt', 'json', 'yaml', 'yml', 'log', 'md'];
    
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function sanitizeForOutput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeForOutput'], $input);
        }
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    public static function validatePath($path, $allowWrite = false) {
        if (empty($path) || !is_string($path)) {
            return false;
        }
        
        // Remove any null bytes
        $path = str_replace("\0", '', $path);
        
        // Resolve the real path
        $realPath = realpath(dirname($path)) . '/' . basename($path);
        
        // Check against allowed base paths
        foreach (self::$allowedPaths as $allowedPath) {
            $allowedRealPath = realpath($allowedPath);
            if ($allowedRealPath && strpos($realPath, $allowedRealPath) === 0) {
                // Additional checks for write operations
                if ($allowWrite) {
                    return self::validateFileExtension($path) && self::isWritableLocation($realPath);
                }
                return true;
            }
        }
        
        return false;
    }
    
    public static function validateFileExtension($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, self::$allowedExtensions);
    }
    
    private static function isWritableLocation($path) {
        $writablePaths = ['/app/config', '/app/data', '/app/logs', '/tmp/zeroai'];
        foreach ($writablePaths as $writablePath) {
            if (strpos($path, realpath($writablePath)) === 0) {
                return true;
            }
        }
        return false;
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public static function validateApiKey($key) {
        return !empty($key) && preg_match('/^[a-zA-Z0-9_-]+$/', $key) && strlen($key) >= 20;
    }
    
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function sanitizeFilename($filename) {
        // Remove directory traversal attempts
        $filename = basename($filename);
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        return $filename;
    }
    
    public static function validateJSON($json) {
        if (!is_string($json)) {
            return false;
        }
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    public static function sanitizeCommand($command) {
        // Only allow alphanumeric, spaces, and safe characters
        return preg_replace('/[^a-zA-Z0-9\s._-]/', '', $command);
    }
}


