<?php
// Initialize CRM database tables
require_once __DIR__ . '/../admin/includes/autoload.php';

try {
    // This will trigger the Database class constructor which runs migrations
    $db = new \ZeroAI\Core\DatabaseManager();
    echo "CRM database initialized successfully!<br>";
    echo "<a href='/web/'>Go to CRM Dashboard</a>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>