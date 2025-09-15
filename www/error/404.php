<?php
http_response_code(404);
$requestUri = $_SERVER['ORIGINAL_URI'] ?? $_SERVER['REQUEST_URI'] ?? '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .container { max-width: 600px; margin: 100px auto; text-align: center; padding: 40px 20px; }
        .error-code { font-size: 8rem; font-weight: bold; margin: 0; opacity: 0.8; }
        .error-message { font-size: 1.5rem; margin: 20px 0; }
        .error-description { font-size: 1rem; margin: 20px 0; opacity: 0.9; }
        .btn { display: inline-block; padding: 12px 24px; background: rgba(255,255,255,0.2); color: white; text-decoration: none; border-radius: 6px; margin: 10px; transition: all 0.3s; }
        .btn:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }
        .path { background: rgba(0,0,0,0.2); padding: 10px; border-radius: 4px; font-family: monospace; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-code">404</div>
        <div class="error-message">Page Not Found</div>
        <div class="error-description">
            The page you're looking for doesn't exist or has been moved.
        </div>
        <div class="path">Requested: <?= htmlspecialchars($requestUri) ?></div>
        <div>
            <a href="/" class="btn">🏠 Home</a>
            <a href="/admin/" class="btn">🔧 Admin</a>
            <a href="javascript:history.back()" class="btn">← Go Back</a>
        </div>
    </div>
</body>
</html>

