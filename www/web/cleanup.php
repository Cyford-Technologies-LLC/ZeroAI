<?php
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

try {
    $stmt = $pdo->prepare("DELETE FROM companies WHERE name = 'Sample Company'");
    $stmt->execute();
    echo "Deleted " . $stmt->rowCount() . " sample companies.<br>";
    echo "<a href='/web/companies.php'>Back to Companies</a>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

