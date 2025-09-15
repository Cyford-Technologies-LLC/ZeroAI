<?php
// Load logging system
require_once __DIR__ . '/admin/includes/autoload.php';

// Check for 404 errors and log them
if (isset($_GET['url']) || strpos($_SERVER['REQUEST_URI'], '404') !== false) {
    $logger = \ZeroAI\Core\Logger::getInstance();
    $logger->error('404 Not Found - Redirected to Index', [
        'url' => $_GET['url'] ?? $_SERVER['REQUEST_URI'] ?? 'unknown',
        'referrer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZeroAI - Zero Cost AI Workforce</title>
    <link rel="icon" type="image/x-icon" href="/www/assets/img/favicon.ico">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 100px 0; text-align: center; }
        .hero h1 { font-size: 4rem; font-weight: bold; margin-bottom: 1rem; }
        .hero p { font-size: 1.25rem; margin-bottom: 2rem; opacity: 0.9; }
        .btn { display: inline-block; padding: 15px 30px; font-size: 18px; margin: 10px; text-decoration: none; border-radius: 8px; font-weight: 500; transition: all 0.3s; }
        .btn-light { background: white; color: #333; }
        .btn-light:hover { background: #f8f9fa; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .btn-outline { background: transparent; color: white; border: 2px solid white; }
        .btn-outline:hover { background: white; color: #667eea; }
        .features { padding: 80px 0; }
        .features h2 { text-align: center; font-size: 2.5rem; margin-bottom: 3rem; color: #333; }
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; }
        .feature-card { background: white; padding: 40px 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .feature-card:hover { transform: translateY(-10px); }
        .feature-icon { font-size: 3rem; margin-bottom: 1rem; }
        .feature-card h5 { font-size: 1.25rem; margin-bottom: 1rem; color: #333; }
        .feature-card p { color: #666; }
        footer { background: #2c3e50; color: white; padding: 40px 0; text-align: center; }
        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .hero p { font-size: 1rem; }
            .btn { padding: 12px 24px; font-size: 16px; }
        }
    </style>
</head>
<body>
    <div class="hero">
        <div class="container">
            <h1>💰 ZeroAI</h1>
            <p>Zero Cost. Zero Cloud. Zero Limits.<br>Build your own AI workforce that runs entirely on your hardware.</p>
            
            <div>
                <a href="/web" class="btn btn-light">
                    👤 User Portal
                </a>
                <a href="/admin/login.php" class="btn btn-outline">
                    ⚙️ Admin Portal
                </a>
            </div>
        </div>
    </div>

    <div class="features">
        <div class="container">
            <h2>Why ZeroAI?</h2>
            
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">💰</div>
                    <h5>Zero Cost</h5>
                    <p>No API fees, no subscriptions, no hidden charges</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h5>Zero Cloud</h5>
                    <p>Your data never leaves your machine</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h5>Zero Limits</h5>
                    <p>Scale from prototype to enterprise on your terms</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🛠️</div>
                    <h5>Zero Lock-in</h5>
                    <p>Fully customizable and open source</p>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>💰 ZeroAI - Zero Cost. Zero Cloud. Zero Limits.</p>
            <p>Open Source AI Workforce Platform</p>
        </div>
    </footer>
</body>
</html>