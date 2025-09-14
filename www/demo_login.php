<?php
session_start();

if ($_POST) {
    try {
        require_once 'src/Core/UserManager.php';
        $userManager = new \ZeroAI\Core\UserManager();
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            throw new Exception('Username and password are required');
        }
        
        $user = $userManager->authenticate($username, $password);
        
        if ($user && $user['role'] === 'demo') {
            $_SESSION['demo_logged_in'] = true;
            $_SESSION['demo_user'] = $username;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['admin_logged_in'] = true; // Allow admin access but read-only
            $_SESSION['admin_user'] = $username;
            
            header('Location: /demo/dashboard.php');
            exit;
        } else {
            $error = "Invalid credentials or access denied";
        }
    } catch (Exception $e) {
        $error = "Login failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Demo Login - ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-form { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 300px; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .error { color: red; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="login-form">
        <h2>ZeroAI Demo Login</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <p style="text-align: center; margin-top: 15px; color: #666; font-size: 12px;">
            Demo: demo / demo123
        </p>
        <p style="text-align: center; margin-top: 10px;">
            <a href="/admin/login.php" style="color: #007bff; text-decoration: none;">Admin Login</a>
        </p>
    </div>
</body>
</html>