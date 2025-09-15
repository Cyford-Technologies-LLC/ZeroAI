<?php
namespace ZeroAI\Core;

class SecurityException extends \Exception {
    private $securityLevel;
    
    public function __construct($message = "", $code = 0, $securityLevel = 'medium', \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->securityLevel = $securityLevel;
        
        // Log security incidents
        Logger::getInstance()->logSecurity($message, $securityLevel);
    }
    
    public function getSecurityLevel() {
        return $this->securityLevel;
    }
}

class ValidationException extends SecurityException {
    public function __construct($message = "Validation failed", $code = 400, \Throwable $previous = null) {
        parent::__construct($message, $code, 'low', $previous);
    }
}

class AuthenticationException extends SecurityException {
    public function __construct($message = "Authentication failed", $code = 401, \Throwable $previous = null) {
        parent::__construct($message, $code, 'high', $previous);
    }
}

class AuthorizationException extends SecurityException {
    public function __construct($message = "Access denied", $code = 403, \Throwable $previous = null) {
        parent::__construct($message, $code, 'high', $previous);
    }
}

class FileSecurityException extends SecurityException {
    public function __construct($message = "File operation not allowed", $code = 403, \Throwable $previous = null) {
        parent::__construct($message, $code, 'critical', $previous);
    }
}
