<?php
require_once __DIR__ . '/admin/includes/autoload.php';

// Log 404 error with details
$logger = \ZeroAI\Core\Logger::getInstance();
$logger->error('404 Not Found', [
    'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'referrer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
]);

http_response_code(404);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Page Not Found - ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .error-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
        .error-code { font-size: 4em; color: #dc3545; font-weight: bold; margin: 0; }
        .error-message { font-size: 1.2em; color: #666; margin: 20px 0; }
        .error-details { color: #999; font-size: 0.9em; margin: 20px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 10px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <h1>Page Not Found</h1>
        <div class="error-message">
            The page you're looking for doesn't exist or has been moved.
        </div>
        <div class="error-details">
            ✅ This error has been logged and our team is working on it.<br>
            📧 If this continues, please contact support.
        </div>
        <a href="/admin/dashboard.php" class="btn">Go to Dashboard</a>
        <a href="javascript:history.back()" class="btn" style="background: #6c757d;">Go Back</a>
    </div>
</body>
</html>