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
            
            $this->logger->info("TenantManager: Looking for tenant database", [
                'org_id' => $organizationId,
                'checking_dir' => $dirPath,
                'expected_db_path' => $dbPath
            ]);
            
            if (is_dir($dirPath)) {
                $this->logger->info("TenantManager: Directory exists", ['dir' => $dirPath]);
                if (file_exists($dbPath)) {
                    $this->logger->info("TenantManager: Database file found", ['path' => $dbPath]);
                    return $dbPath;
                } else {
                    $this->logger->warning("TenantManager: Directory exists but database missing", ['path' => $dbPath]);
                }
            } else {
                $this->logger->warning("TenantManager: Directory does not exist", ['dir' => $dirPath]);
            }
            
            // Fallback to database table
            $this->logger->info("TenantManager: Checking database table for tenant path");
            $pdo = $this->mainDb->getConnection();
            $stmt = $pdo->prepare("SELECT db_path FROM tenant_databases WHERE organization_id = ?");
            $stmt->execute([$organizationId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result) {
                $this->logger->info("TenantManager: Found path in database table", ['path' => $result['db_path']]);
            } else {
                $this->logger->warning("TenantManager: No entry found in tenant_databases table", ['org_id' => $organizationId]);
            }
            
            return $result ? $result['db_path'] : null;
        } catch (\Exception $e) {
            $this->logger->error('TenantManager: Failed to get tenant db path', [
                'org_id' => $organizationId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    public function getTenantDatabase($organizationId) {
        try {
            error_log("[DEBUG] TenantManager: getTenantDatabase called with orgId: " . var_export($organizationId, true));
            
            $dbPath = $this->getTenantDbPath($organizationId);
            if (!$dbPath) {
                $this->logger->error("TenantManager: Tenant database not found", [
                    'org_id' => $organizationId,
                    'searched_path' => "/app/data/companies/{$organizationId}/crm.db"
                ]);
                error_log("[ERROR] TenantManager: Tenant database not found for organization: {$organizationId}");
                throw new \Exception("Tenant database not found for organization: {$organizationId}");
            }
            
            $this->logger->info("TenantManager: Creating TenantDatabase instance", ['path' => $dbPath]);
            return new TenantDatabase($dbPath);
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