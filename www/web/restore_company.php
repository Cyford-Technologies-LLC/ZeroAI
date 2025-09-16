<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Check if any companies exist
    $companies = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
    
    if ($companies == 0) {
        // Create a test company
        $stmt = $pdo->prepare("INSERT INTO companies (name, email, phone, website, organization_id, user_id, created_by) VALUES (?, ?, ?, ?, 1, 1, 1)");
        $stmt->execute(['Test Company', 'test@company.com', '555-1234', 'https://testcompany.com']);
        echo "Test company created successfully!";
    } else {
        echo "Found {$companies} companies in database.";
    }
    
    // List all companies
    $allCompanies = $pdo->query("SELECT * FROM companies")->fetchAll(PDO::FETCH_ASSOC);
    echo "<br><br>Current companies:<br>";
    foreach ($allCompanies as $company) {
        echo "ID: {$company['id']}, Name: {$company['name']}, Email: {$company['email']}<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
<br><br>
<a href="/web/companies.php">Go to Companies Page</a>