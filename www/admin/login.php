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
        
        if ($user) {
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
        body { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); min-height: 100vh; }
        .login-card { max-width: 400px; margin: 0 auto; }
        .card { border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .btn-primary { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); border: none; }
        .btn-primary:hover { background: linear-gradient(135deg, #0056b3 0%, #004085 100%); }
    </style>
</head>
<body class="d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card">
                    <div class="card-body p-5">
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