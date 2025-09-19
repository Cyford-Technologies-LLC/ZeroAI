<?php
require_once __DIR__ . '/../src/bootstrap.php';

try {
    $logger = \ZeroAI\Core\Logger::getInstance();
    $logger->info('Verifying tenant system functionality');
    
    echo "🔍 Verifying Multi-Tenant System...\n\n";
    
    // Check if tenant databases exist
    $dataDir = '/app/data/companies';
    if (is_dir($dataDir)) {
        $orgDirs = glob($dataDir . '/*', GLOB_ONLYDIR);
        echo "✅ Found " . count($orgDirs) . " tenant directories:\n";
        
        foreach ($orgDirs as $dir) {
            $orgId = basename($dir);
            $dbPath = $dir . '/crm.db';
            
            if (file_exists($dbPath)) {
                echo "  📁 Organization {$orgId}: Database exists\n";
                
                // Test database connection
                try {
                    require_once __DIR__ . '/../src/Services/TenantDatabase.php';
                    $tenantDb = new \ZeroAI\Services\TenantDatabase($orgId);
                    
                    $companies = $tenantDb->select('companies');
                    $contacts = $tenantDb->select('contacts');
                    $projects = $tenantDb->select('projects');
                    
                    echo "    📊 Companies: " . count($companies) . "\n";
                    echo "    👥 Contacts: " . count($contacts) . "\n";
                    echo "    🚀 Projects: " . count($projects) . "\n";
                    
                } catch (Exception $e) {
                    echo "    ❌ Database error: " . $e->getMessage() . "\n";
                }
            } else {
                echo "  ❌ Organization {$orgId}: Database missing\n";
            }
            echo "\n";
        }
    } else {
        echo "❌ Tenant data directory not found: {$dataDir}\n";
    }
    
    // Test CRMHelper
    echo "🧪 Testing CRMHelper...\n";
    if (!empty($orgDirs)) {
        $testOrgId = basename($orgDirs[0]);
        
        require_once __DIR__ . '/../src/Services/CRMHelper.php';
        $crmHelper = new \ZeroAI\Services\CRMHelper($testOrgId);
        
        $companies = $crmHelper->getCompanies();
        echo "✅ CRMHelper retrieved " . count($companies) . " companies for org {$testOrgId}\n";
    }
    
    echo "\n🎉 Tenant system verification complete!\n";
    $logger->info('Tenant system verification completed successfully');
    
} catch (Exception $e) {
    echo "❌ Verification failed: " . $e->getMessage() . "\n";
    if (isset($logger)) {
        $logger->error('Tenant system verification failed', ['error' => $e->getMessage()]);
    }
}