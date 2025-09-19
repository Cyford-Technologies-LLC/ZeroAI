<?php
require_once __DIR__ . '/../src/bootstrap.php';

try {
    $logger = \ZeroAI\Core\Logger::getInstance();
    $logger->info('Testing tenant database system');
    
    // Test TenantManager
    require_once __DIR__ . '/../src/Services/TenantManager.php';
    $tenantManager = new \ZeroAI\Services\TenantManager();
    
    // Create a test tenant database
    $testOrgId = 'TEST' . time();
    $createdOrgId = $tenantManager->createTenantDatabase($testOrgId);
    
    echo "✅ Tenant database created: {$createdOrgId}\n";
    $logger->info('Test tenant database created', ['org_id' => $createdOrgId]);
    
    // Test TenantDatabase connection
    require_once __DIR__ . '/../src/Services/TenantDatabase.php';
    $tenantDb = new \ZeroAI\Services\TenantDatabase($createdOrgId);
    
    // Test inserting data
    $companyId = $tenantDb->insert('companies', [
        'name' => 'Test Company',
        'email' => 'test@example.com',
        'phone' => '555-1234'
    ]);
    
    echo "✅ Test company created with ID: {$companyId}\n";
    
    // Test retrieving data
    $companies = $tenantDb->select('companies');
    echo "✅ Retrieved " . count($companies) . " companies from tenant DB\n";
    
    // Test CRMHelper
    require_once __DIR__ . '/../src/Services/CRMHelper.php';
    $crmHelper = new \ZeroAI\Services\CRMHelper($createdOrgId);
    
    $helperCompanies = $crmHelper->getCompanies();
    echo "✅ CRMHelper retrieved " . count($helperCompanies) . " companies\n";
    
    echo "\n🎉 All tenant database tests passed!\n";
    $logger->info('Tenant database tests completed successfully');
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    if (isset($logger)) {
        $logger->error('Tenant database test failed', ['error' => $e->getMessage()]);
    }
}