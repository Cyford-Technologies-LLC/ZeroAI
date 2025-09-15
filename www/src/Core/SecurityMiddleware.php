<?php
namespace ZeroAI\Core;

class SecurityMiddleware {
    private static $instance = null;
    private $logger;
    
    private function __construct() {
        $this->logger = Logger::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Main security check method
     */
    public function checkRequest() {
        $this->checkCSRF();
        $this->checkRateLimit();
        $this->sanitizeGlobals();
        $this->checkFileUploads();
        $this->setSecurityHeaders();
    }
    
    /**
     * Check CSRF token for state-changing requests
     */
    private function checkCSRF() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            
            if (!InputValidator::validateCSRFToken($token)) {
                $this->logger->logSecurity('CSRF token validation failed', 'high');
                throw new SecurityException('Invalid CSRF token', 403, 'high');
            }
        }
    }
    
    /**
     * Basic rate limiting
     */
    private function checkRateLimit() {
        $ip = $this->getClientIP();
        $key = "rate_limit:$ip";
        
        $cache = CacheManager::getInstance();
        $requests = $cache->get($key) ?: 0;
        
        if ($requests > 100) { // 100 requests per minute
            $this->logger->logSecurity("Rate limit exceeded for IP: $ip", 'medium');
            throw new SecurityException('Rate limit exceeded', 429, 'medium');
        }
        
        $cache->set($key, $requests + 1, 60); // 1 minute window
    }
    
    /**
     * Sanitize all global variables
     */
    private function sanitizeGlobals() {
        $_GET = $this->sanitizeArray($_GET);
        $_POST = $this->sanitizeArray($_POST);
        $_COOKIE = $this->sanitizeArray($_COOKIE);
    }
    
    /**
     * Recursively sanitize array
     */
    private function sanitizeArray($array) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sanitizeArray($value);
            } else {
                $array[$key] = InputValidator::sanitize($value);
            }
        }
        return $array;
    }
    
    /**
     * Check file uploads for security
     */
    private function checkFileUploads() {
        if (!empty($_FILES)) {
            foreach ($_FILES as $file) {
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $this->validateUploadedFile($file);
                }
            }
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateUploadedFile($file) {
        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new FileSecurityException('File too large');
        }
        
        // Check file extension
        if (!InputValidator::validateFileExtension($file['name'])) {
            throw new FileSecurityException('Invalid file type');
        }
        
        // Check MIME type
        $allowedMimes = [
            'text/plain', 'application/json', 'text/yaml',
            'application/x-yaml', 'text/markdown'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimes)) {
            throw new FileSecurityException('Invalid MIME type');
        }
    }
    
    /**
     * Set security headers
     */
    private function setSecurityHeaders() {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'');
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Validate API request
     */
    public function validateAPIRequest($requiredFields = []) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException('Invalid JSON input');
        }
        
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                throw new ValidationException("Required field missing: $field");
            }
        }
        
        return $input;
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent($event, $level = 'medium', $details = []) {
        $this->logger->logSecurity($event, $level, $details);
    }
}


