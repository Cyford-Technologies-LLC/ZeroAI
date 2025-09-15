<?php
namespace ZeroAI\Security;

class InputValidator {
    public static function sanitizeOutput($input) {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateFilePath($path, $allowedDirs = ['/app/']) {
        $realPath = realpath($path);
        if ($realPath === false) return false;
        
        foreach ($allowedDirs as $allowedDir) {
            if (strpos($realPath, realpath($allowedDir)) === 0) {
                return $realPath;
            }
        }
        return false;
    }
    
    public static function sanitizeCommand($input) {
        return escapeshellarg($input);
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
    
    public static function sanitizeLogInput($input) {
        return str_replace(["\n", "\r"], '', $input);
    }
}


