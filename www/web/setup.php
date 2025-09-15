<?php
require_once __DIR__ . '/../admin/includes/autoload.php';

use ZeroAI\Core\DatabaseManager;

$db = DatabaseManager::getInstance();

// Read and execute schema
$schema = file_get_contents(__DIR__ . '/schema.sql');
$statements = explode(';', $schema);

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
        try {
            $db->query($statement);
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
        }
    }
}

// Create sample data
$sampleTenant = [
    'name' => 'Demo Tenant',
    'domain' => 'demo.zeroai.local',
    'settings' => json_encode(['theme' => 'default'])
];

$sampleCompany = [
    'tenant_id' => 1,
    'name' => 'ZeroAI Technologies',
    'slug' => 'zeroai-tech',
    'email' => 'contact@zeroai.tech',
    'website' => 'https://zeroai.tech',
    'industry' => 'Technology',
    'description' => 'AI-powered development solutions',
    'social_media' => json_encode([
        'twitter' => '@zeroai',
        'linkedin' => 'zeroai-tech'
    ]),
    'seo_settings' => json_encode([
        'meta_title' => 'ZeroAI Technologies - AI Development',
        'meta_description' => 'Leading AI development company'
    ])
];

$sampleProject = [
    'company_id' => 1,
    'name' => 'CRM Development',
    'slug' => 'crm-dev',
    'description' => 'Multi-tenant CRM with project management',
    'status' => 'active',
    'priority' => 'high',
    'start_date' => date('Y-m-d'),
    'budget' => 50000.00
];

try {
    $db->insert('tenants', $sampleTenant);
    $db->insert('companies', $sampleCompany);
    $db->insert('projects', $sampleProject);
    echo "\n✓ Sample data created successfully!\n";
} catch (Exception $e) {
    echo "\n✗ Sample data error: " . $e->getMessage() . "\n";
}

echo "\n🎉 CRM setup complete! Visit /web/dashboard.php\n";
?>

