<?php
// Simple test page
echo "PHP is working!<br>";
echo "Session test: ";
session_start();
echo "OK<br>";

echo "Database test: ";
try {
    require_once __DIR__ . '/../config/database.php';
    echo "Config loaded<br>";
    $db = new Database();
    echo "Database class created<br>";
    $pdo = $db->getConnection();
    echo "Connection OK<br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "All tests completed.";
?>

