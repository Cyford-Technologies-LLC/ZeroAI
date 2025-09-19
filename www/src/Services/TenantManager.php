<?php
namespace ZeroAI\Services;

require_once __DIR__ . '/../bootstrap.php';

class TenantManager {
    private $logger;
    private $mainDb;
    
    public function __construct() {
        try {
            $this->logger = \ZeroAI\Core\Logger::getInstance();
            $this->logger->debug('TenantManager: Initializing');
            
            require_once __DIR__ . '/../../config/database.php';
            $this->mainDb = new \Database();
            
            $this->ensureTenantTable();
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('TenantManager: Initialization failed', ['error' => $e->getMessage()]);
            }
            throw $e;
        }
    }
    
    private function ensureTenantTable() {
        try {
            $pdo = $this->mainDb->getConnection();
            $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_databases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id TEXT UNIQUE NOT NULL,
                db_path TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $this->logger->debug('TenantManager: Tenant table ensured');
        } catch (\Exception $e) {
            $this->logger->error('TenantManager: Failed to ensure tenant table', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function createTenantDatabase($organizationId) {
        try {
            $this->logger->info('TenantManager: Creating tenant database', ['org_id' => $organizationId]);
            
            // Generate unique organization ID if not provided
            if (empty($organizationId)) {
                $organizationId = $this->generateOrganizationId();
            }
            
            // Create directory structure
            $tenantDir = "/app/data/companies/{$organizationId}";
            if (!is_dir($tenantDir)) {
                if (!mkdir($tenantDir, 0755, true)) {
                    throw new \Exception("Failed to create tenant directory: {$tenantDir}");
                }
            }
            
            // Create tenant database
            $dbPath = "{$tenantDir}/crm.db";
            $this->initializeTenantDb($dbPath);
            
            // Record in main database
            $pdo = $this->mainDb->getConnection();
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO tenant_databases (organization_id, db_path) VALUES (?, ?)");
            $stmt->execute([$organizationId, $dbPath]);
            
            $this->logger->info('TenantManager: Tenant database created successfully', [
                'org_id' => $organizationId,
                'db_path' => $dbPath
            ]);
            
            return $organizationId;
        } catch (\Exception $e) {
            $this->logger->error('TenantManager: Failed to create tenant database', [
                'org_id' => $organizationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    private function initializeTenantDb($dbPath) {
        try {
            $pdo = new \PDO("sqlite:{$dbPath}");
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // Create tenant-specific tables
            $sql = "
            CREATE TABLE IF NOT EXISTS companies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT,
                phone TEXT,
                address TEXT,
                website TEXT,
                industry TEXT,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                email TEXT,
                phone TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id)
            );
            
            CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER,
                name TEXT NOT NULL,
                description TEXT,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id)
            );
            ";
            
            $pdo->exec($sql);
            $this->logger->debug('TenantManager: Tenant database initialized', ['db_path' => $dbPath]);
        } catch (\Exception $e) {
            $this->logger->error('TenantManager: Failed to initialize tenant database', [
                'db_path' => $dbPath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    private function generateOrganizationId() {
        return 'ORG' . str_pad(mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
    }
    
    public function getTenantDbPath($organizationId) {
        try {
            // Check file system directly first
            $dbPath = "/app/data/companies/{$organizationId}/crm.db";
            $dirPath = "/app/data/companies/{$organizationId}";
            
            error_log("[DEBUG] TenantManager: getTenantDbPath called with orgId: " . var_export($organizationId, true));
            error_log("[DEBUG] TenantManager: Expected dir: {$dirPath}");
            error_log("[DEBUG] TenantManager: Expected db: {$dbPath}");
            error_log("[DEBUG] TenantManager: Directory exists: " . (is_dir($dirPath) ? 'YES' : 'NO'));
            error_log("[DEBUG] TenantManager: File exists: " . (file_exists($dbPath) ? 'YES' : 'NO'));
            
            if (is_dir($dirPath)) {
                if (file_exists($dbPath)) {
                    error_log("[DEBUG] TenantManager: Returning existing db path: {$dbPath}");
                    return $dbPath;
                } else {
                    error_log("[DEBUG] TenantManager: Directory exists but database missing");
                }
            } else {
                error_log("[DEBUG] TenantManager: Directory does not exist");
            }
            
            // Fallback to database table
            error_log("[DEBUG] TenantManager: Checking database table for tenant path");
            $pdo = $this->mainDb->getConnection();
            $stmt = $pdo->prepare("SELECT db_path FROM tenant_databases WHERE organization_id = ?");
            $stmt->execute([$organizationId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result) {
                error_log("[DEBUG] TenantManager: Found path in database table: " . $result['db_path']);
                return $result['db_path'];
            } else {
                error_log("[DEBUG] TenantManager: No entry found in tenant_databases table");
            }
            
            return null;
        } catch (\Exception $e) {
            error_log("[ERROR] TenantManager: Exception in getTenantDbPath: " . $e->getMessage());
            return null;
        }
    }
    
    public function getTenantDatabase($organizationId) {
        try {
            error_log("[DEBUG] TenantManager: getTenantDatabase called with orgId: " . var_export($organizationId, true));
            
            $dbPath = $this->getTenantDbPath($organizationId);
            if (!$dbPath) {
                // Try to create the tenant database if it doesn't exist
                error_log("[DEBUG] TenantManager: Database not found, attempting to create for org: {$organizationId}");
                $this->createTenantDatabase($organizationId);
                $dbPath = $this->getTenantDbPath($organizationId);
                
                if (!$dbPath) {
                    $this->logger->error("TenantManager: Tenant database not found", [
                        'org_id' => $organizationId,
                        'searched_path' => "/app/data/companies/{$organizationId}/crm.db"
                    ]);
                    error_log("[ERROR] TenantManager: Tenant database not found for organization: {$organizationId}");
                    throw new \Exception("Tenant database not found for organization: {$organizationId}");
                }
            }
            
            $this->logger->info("TenantManager: Creating TenantDatabase instance", ['path' => $dbPath]);
            return new TenantDatabase($dbPath, $organizationId);
        } catch (\Exception $e) {
            $this->logger->error('TenantManager: Failed to get tenant database', [
                'org_id' => $organizationId,
                'error' => $e->getMessage()
            ]);
            error_log("[ERROR] TenantManager: Exception in getTenantDatabase: " . $e->getMessage());
            throw $e;
        }
    }
}