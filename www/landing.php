<?php
// Move the current index.php content here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZeroAI - Zero Cost AI Workforce</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 100px 0; }
        .feature-card { transition: transform 0.3s; }
        .feature-card:hover { transform: translateY(-5px); }
        .portal-btn { padding: 15px 30px; font-size: 18px; margin: 10px; }
    </style>
</head>
<body>
    <div class="hero text-center">
        <div class="container">
            <h1 class="display-3 fw-bold mb-4">💰 ZeroAI</h1>
            <p class="lead fs-4 mb-5">Zero Cost. Zero Cloud. Zero Limits.<br>Build your own AI workforce that runs entirely on your hardware.</p>
            
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="d-flex justify-content-center flex-wrap">
                        <a href="/web" class="btn btn-light btn-lg portal-btn">
                            👤 User Portal
                        </a>
                        <a href="/admin/login.php" class="btn btn-outline-light btn-lg portal-btn">
                            ⚙️ Admin Portal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <div class="row text-center mb-5">
            <div class="col-12">
                <h2 class="mb-4">Why ZeroAI?</h2>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-md-3">
                <div class="card h-100 feature-card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-3">💰</div>
                        <h5>Zero Cost</h5>
                        <p class="text-muted">No API fees, no subscriptions, no hidden charges</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 feature-card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-3">🔒</div>
                        <h5>Zero Cloud</h5>
                        <p class="text-muted">Your data never leaves your machine</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 feature-card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-3">⚡</div>
                        <h5>Zero Limits</h5>
                        <p class="text-muted">Scale from prototype to enterprise on your terms</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 feature-card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-3">🛠️</div>
                        <h5>Zero Lock-in</h5>
                        <p class="text-muted">Fully customizable and open source</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-light py-4">
        <div class="container text-center">
            <p class="mb-2">💰 ZeroAI - Zero Cost. Zero Cloud. Zero Limits.</p>
            <p class="mb-0">Open Source AI Workforce Platform</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

