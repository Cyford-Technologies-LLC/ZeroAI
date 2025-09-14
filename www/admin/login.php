<?php
session_start();

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
            
            header('Location: /admin/dashboard');
            exit;
        } else {
            $error = "Invalid credentials";
        }
    } catch (Exception $e) {
        $error = "Login failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-form { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 300px; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .error { color: red; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="login-form">
        <h2>ZeroAI Admin Login</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <p style="text-align: center; margin-top: 15px; color: #666; font-size: 12px;">
            Default: admin / admin123
        </p>
    </div>
</body>
</html>