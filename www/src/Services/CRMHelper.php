<?php
namespace ZeroAI\Services;

require_once __DIR__ . '/../bootstrap.php';

class CRMHelper {
    private $logger;
    private $tenantDb;
    private $organizationId;
    
    public function __construct($organizationId = null) {
        try {
            $this->logger = \ZeroAI\Core\Logger::getInstance();
            
            if ($organizationId) {
                $this->organizationId = $organizationId;
                $this->tenantDb = new TenantDatabase($organizationId);
                $this->logger->debug('CRMHelper: Initialized with tenant database', ['org_id' => $organizationId]);
            } else {
                $this->logger->debug('CRMHelper: Initialized without tenant database');
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('CRMHelper: Initialization failed', [
                    'org_id' => $organizationId,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }
    
    public function getCompanies($limit = null) {
        try {
            if ($this->tenantDb) {
                $companies = $this->tenantDb->select('companies', [], $limit);
                $this->logger->debug('CRMHelper: Retrieved companies from tenant DB', [
                    'org_id' => $this->organizationId,
                    'count' => count($companies)
                ]);
                return $companies;
            }
            
            // Fallback to main database if no tenant DB
            require_once __DIR__ . '/../../config/database.php';
            $mainDb = new \Database();
            $companies = $mainDb->select('companies', [], $limit);
            $this->logger->debug('CRMHelper: Retrieved companies from main DB', ['count' => count($companies)]);
            return $companies;
        } catch (\Exception $e) {
            $this->logger->error('CRMHelper: Failed to get companies', [
                'org_id' => $this->organizationId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    public function getContacts($companyId = null, $limit = null) {
        try {
            $where = $companyId ? ['company_id' => $companyId] : [];
            
            if ($this->tenantDb) {
                $contacts = $this->tenantDb->select('contacts', $where, $limit);
                $this->logger->debug('CRMHelper: Retrieved contacts from tenant DB', [
                    'org_id' => $this->organizationId,
                    'company_id' => $companyId,
                    'count' => count($contacts)
                ]);
                return $contacts;
            }
            
            // Fallback to main database
            require_once __DIR__ . '/../../config/database.php';
            $mainDb = new \Database();
            $contacts = $mainDb->select('contacts', $where, $limit);
            $this->logger->debug('CRMHelper: Retrieved contacts from main DB', ['count' => count($contacts)]);
            return $contacts;
        } catch (\Exception $e) {
            $this->logger->error('CRMHelper: Failed to get contacts', [
                'org_id' => $this->organizationId,
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    public function getProjects($companyId = null, $limit = null) {
        try {
            $where = $companyId ? ['company_id' => $companyId] : [];
            
            if ($this->tenantDb) {
                $projects = $this->tenantDb->select('projects', $where, $limit);
                $this->logger->debug('CRMHelper: Retrieved projects from tenant DB', [
                    'org_id' => $this->organizationId,
                    'company_id' => $companyId,
                    'count' => count($projects)
                ]);
                return $projects;
            }
            
            // Fallback to main database
            require_once __DIR__ . '/../../config/database.php';
            $mainDb = new \Database();
            $projects = $mainDb->select('projects', $where, $limit);
            $this->logger->debug('CRMHelper: Retrieved projects from main DB', ['count' => count($projects)]);
            return $projects;
        } catch (\Exception $e) {
            $this->logger->error('CRMHelper: Failed to get projects', [
                'org_id' => $this->organizationId,
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    public function addCompany($data) {
        try {
            if ($this->tenantDb) {
                $companyId = $this->tenantDb->insert('companies', $data);
                $this->logger->info('CRMHelper: Company added to tenant DB', [
                    'org_id' => $this->organizationId,
                    'company_id' => $companyId,
                    'name' => $data['name'] ?? 'unknown'
                ]);
                return $companyId;
            }
            
            // Fallback to main database
            require_once __DIR__ . '/../../config/database.php';
            $mainDb = new \Database();
            $companyId = $mainDb->insert('companies', $data);
            $this->logger->info('CRMHelper: Company added to main DB', [
                'company_id' => $companyId,
                'name' => $data['name'] ?? 'unknown'
            ]);
            return $companyId;
        } catch (\Exception $e) {
            $this->logger->error('CRMHelper: Failed to add company', [
                'org_id' => $this->organizationId,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
    
    public function addContact($data) {
        try {
            if ($this->tenantDb) {
                $contactId = $this->tenantDb->insert('contacts', $data);
                $this->logger->info('CRMHelper: Contact added to tenant DB', [
                    'org_id' => $this->organizationId,
                    'contact_id' => $contactId,
                    'name' => ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')
                ]);
                return $contactId;
            }
            
            // Fallback to main database
            require_once __DIR__ . '/../../config/database.php';
            $mainDb = new \Database();
            $contactId = $mainDb->insert('contacts', $data);
            $this->logger->info('CRMHelper: Contact added to main DB', [
                'contact_id' => $contactId,
                'name' => ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')
            ]);
            return $contactId;
        } catch (\Exception $e) {
            $this->logger->error('CRMHelper: Failed to add contact', [
                'org_id' => $this->organizationId,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
    
    public function addProject($data) {
        try {
            if ($this->tenantDb) {
                $projectId = $this->tenantDb->insert('projects', $data);
                $this->logger->info('CRMHelper: Project added to tenant DB', [
                    'org_id' => $this->organizationId,
                    'project_id' => $projectId,
                    'name' => $data['name'] ?? 'unknown'
                ]);
                return $projectId;
            }
            
            // Fallback to main database
            require_once __DIR__ . '/../../config/database.php';
            $mainDb = new \Database();
            $projectId = $mainDb->insert('projects', $data);
            $this->logger->info('CRMHelper: Project added to main DB', [
                'project_id' => $projectId,
                'name' => $data['name'] ?? 'unknown'
            ]);
            return $projectId;
        } catch (\Exception $e) {
            $this->logger->error('CRMHelper: Failed to add project', [
                'org_id' => $this->organizationId,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
    
    public function getUserOrganizationId($userId) {
        try {
            require_once __DIR__ . '/../../config/database.php';
            $mainDb = new \Database();
            $user = $mainDb->select('users', ['id' => $userId], 1);
            
            if (!empty($user)) {
                $orgId = $user[0]['organization_id'] ?? null;
                $this->logger->debug('CRMHelper: Retrieved user organization ID', [
                    'user_id' => $userId,
                    'org_id' => $orgId
                ]);
                return $orgId;
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->error('CRMHelper: Failed to get user organization ID', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}