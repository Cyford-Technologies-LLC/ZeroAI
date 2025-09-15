<?php
session_start();

require_once __DIR__ . '/includes/autoload.php';

// Initialize visitor tracking if available
$tracker = null;
try {
    if (class_exists('ZeroAI\Core\VisitorTracker')) {
        $tracker = new \ZeroAI\Core\VisitorTracker();
        $tracker->trackVisitor();
    }
} catch (Exception $e) {
    error_log('VisitorTracker failed: ' . $e->getMessage());
}

if ($_POST) {
    try {
        require_once '../src/Core/UserManager.php';
        $userManager = new \ZeroAI\Core\UserManager();
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            throw new Exception('Username and password are required');
        }
        
        $user = $userManager->authenticate($username, $password);
        
        if ($user && in_array($user['role'], ['admin', 'demo'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $username;
            
            if ($tracker) $tracker->trackLogin($username, true);
            
            header('Location: /admin/dashboard.php');
            exit;
        } else {
            if ($tracker) $tracker->trackLogin($username, false, 'Invalid credentials');
            $error = "Invalid credentials";
        }
    } catch (Exception $e) {
        if ($tracker) $tracker->trackLogin($username ?? 'unknown', false, $e->getMessage());
        $error = "Login failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZeroAI Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container { min-height: 100vh; padding: 20px; }
        .login-card { 
            max-width: 420px; 
            margin: 0 auto;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        .card { 
            border: none; 
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-body { padding: 3rem 2rem; }
        .display-4 { font-size: 3.5rem; }
        .btn-primary { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover { 
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .form-control {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .input-group-text {
            border-radius: 15px 0 0 15px;
            border: 2px solid #e9ecef;
            border-right: none;
            background: #f8f9fa;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 15px 15px 0;
        }
        .alert {
            border-radius: 15px;
            border: none;
        }
        .text-decoration-none:hover {
            text-decoration: underline !important;
        }
        @media (max-width: 576px) {
            .card-body { padding: 2rem 1.5rem; }
            .display-4 { font-size: 2.5rem; }
            .login-container { padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid login-container d-flex align-items-center justify-content-center">
        <div class="row w-100 justify-content-center">
            <div class="col-12 col-sm-8 col-md-6 col-lg-4">
                <div class="card login-card">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="bi bi-shield-lock display-4 text-primary"></i>
                            <h2 class="mt-2">ZeroAI Admin</h2>
                            <p class="text-muted">Administrator Access</p>
                        </div>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-box-arrow-in-right"></i> Admin Login
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <a href="/web/login.php" class="text-decoration-none">
                                <i class="bi bi-person"></i> User Login
                            </a>
                        </div>
                        
                        <div class="alert alert-info mt-3" role="alert">
                            <small>
                                <strong>Demo Login:</strong><br>
                                Username: <code>demo</code><br>
                                Password: <code>demo123</code>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>