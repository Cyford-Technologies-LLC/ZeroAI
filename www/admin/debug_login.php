<?php
// Debug login page to identify 500 error
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Debug: Starting login page...<br>";

try {
    session_start();
    echo "Debug: Session started<br>";
    
    // Test bootstrap
    if (file_exists(__DIR__ . '/includes/autoload.php')) {
        echo "Debug: Autoload file exists<br>";
        require_once __DIR__ . '/includes/autoload.php';
        echo "Debug: Autoload included<br>";
    } else {
        echo "Error: Autoload file not found<br>";
    }
    
    // Test AuthService
    if (class_exists('ZeroAI\\Services\\AuthService')) {
        echo "Debug: AuthService class exists<br>";
        $auth = new ZeroAI\Services\AuthService();
        echo "Debug: AuthService created<br>";
    } else {
        echo "Error: AuthService class not found<br>";
    }
    
    echo "Debug: Login page loaded successfully<br>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
} catch (Error $e) {
    echo "Fatal Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>

<h2>Simple Login Form</h2>
<form method="POST" action="/admin/login.php">
    <input type="text" name="username" placeholder="Username" value="admin" required><br><br>
    <input type="password" name="password" placeholder="Password" value="admin123" required><br><br>
    <button type="submit">Login</button>
</form>


