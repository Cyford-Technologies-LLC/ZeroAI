<?php
require_once __DIR__ . '/../src/bootstrap.php';

try {
    $logger = \ZeroAI\Core\Logger::getInstance();
    $logger->info('Creating missing tenant databases for existing users');
    
    echo "🔧 Creating missing tenant databases...\n\n";
    
    // Get all users from main database
    require_once __DIR__ . '/../config/database.php';
    $mainDb = new Database();
    $pdo = $mainDb->getConnection();
    
    $stmt = $pdo->query("SELECT id, username, organization_id FROM users WHERE organization_id IS NOT NULL");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "👥 Found " . count($users) . " users\n\n";
    
    require_once __DIR__ . '/../src/Services/TenantManager.php';
    $tenantManager = new \ZeroAI\Services\TenantManager();
    
    foreach ($users as $user) {
        $orgId = $user['organization_id'];
        $username = $user['username'];
        
        echo "🔍 Checking user: {$username} (Org: {$orgId})\n";
        
        // Check if tenant database already exists
        $dbPath = $tenantManager->getTenantDbPath($orgId);
        
        if (!$dbPath || !file_exists($dbPath)) {
            echo "  📁 Creating tenant database for organization {$orgId}...\n";
            
            try {
                $createdOrgId = $tenantManager->createTenantDatabase($orgId);
                echo "  ✅ Created tenant database: {$createdOrgId}\n";
                
                $logger->info('Created missing tenant database', [
                    'org_id' => $createdOrgId,
                    'username' => $username
                ]);
            } catch (Exception $e) {
                echo "  ❌ Failed to create tenant database: " . $e->getMessage() . "\n";
                $logger->error('Failed to create missing tenant database', [
                    'org_id' => $orgId,
                    'username' => $username,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            echo "  ✅ Tenant database already exists\n";
        }
        
        echo "\n";
    }
    
    echo "🎉 Finished creating missing tenant databases!\n";
    $logger->info('Completed creating missing tenant databases');
    
} catch (Exception $e) {
    echo "❌ Script failed: " . $e->getMessage() . "\n";
    if (isset($logger)) {
        $logger->error('Create missing tenant databases script failed', ['error' => $e->getMessage()]);
    }
}