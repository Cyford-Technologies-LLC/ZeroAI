<?php
namespace ZeroAI\Services;

require_once __DIR__ . '/../bootstrap.php';

class IntegrationManager {
    private $logger;
    private $tenantDb;
    
    public function __construct($organizationId) {
        try {
            $this->logger = \ZeroAI\Core\Logger::getInstance();
            $this->logger->debug('IntegrationManager: Initializing', ['org_id' => $organizationId]);
            
            $tenantManager = new TenantManager();
            $this->tenantDb = $tenantManager->getTenantDatabase($organizationId);
            
            $this->ensureIntegrationTables();
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('IntegrationManager: Initialization failed', ['error' => $e->getMessage()]);
            }
            throw $e;
        }
    }
    
    private function ensureIntegrationTables() {
        try {
            $sql = "
            CREATE TABLE IF NOT EXISTS integrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                status TEXT DEFAULT 'inactive',
                config TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS integration_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                integration_id INTEGER,
                action TEXT NOT NULL,
                status TEXT NOT NULL,
                message TEXT,
                data TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (integration_id) REFERENCES integrations(id)
            );
            ";
            
            $this->tenantDb->execute($sql);
            $this->logger->debug('IntegrationManager: Tables ensured');
        } catch (\Exception $e) {
            $this->logger->error('IntegrationManager: Failed to ensure tables', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function addIntegration($name, $type, $config = []) {
        try {
            $data = [
                'name' => $name,
                'type' => $type,
                'config' => json_encode($config),
                'status' => 'inactive'
            ];
            
            $id = $this->tenantDb->insert('integrations', $data);
            $this->logger->info('IntegrationManager: Integration added', ['id' => $id, 'name' => $name]);
            return $id;
        } catch (\Exception $e) {
            $this->logger->error('IntegrationManager: Failed to add integration', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function updateIntegration($id, $data) {
        try {
            if (isset($data['config'])) {
                $data['config'] = json_encode($data['config']);
            }
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            $this->tenantDb->update('integrations', $data, ['id' => $id]);
            $this->logger->info('IntegrationManager: Integration updated', ['id' => $id]);
        } catch (\Exception $e) {
            $this->logger->error('IntegrationManager: Failed to update integration', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function getIntegrations() {
        try {
            $integrations = $this->tenantDb->select('integrations', '*');
            foreach ($integrations as &$integration) {
                $integration['config'] = json_decode($integration['config'], true) ?: [];
            }
            return $integrations;
        } catch (\Exception $e) {
            $this->logger->error('IntegrationManager: Failed to get integrations', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function toggleIntegration($id, $status) {
        try {
            $this->updateIntegration($id, ['status' => $status]);
            $this->logAction($id, 'status_change', 'success', "Status changed to {$status}");
        } catch (\Exception $e) {
            $this->logger->error('IntegrationManager: Failed to toggle integration', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function deleteIntegration($id) {
        try {
            $this->tenantDb->delete('integrations', ['id' => $id]);
            $this->logger->info('IntegrationManager: Integration deleted', ['id' => $id]);
        } catch (\Exception $e) {
            $this->logger->error('IntegrationManager: Failed to delete integration', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function logAction($integrationId, $action, $status, $message, $data = null) {
        try {
            $logData = [
                'integration_id' => $integrationId,
                'action' => $action,
                'status' => $status,
                'message' => $message,
                'data' => $data ? json_encode($data) : null
            ];
            
            $this->tenantDb->insert('integration_logs', $logData);
        } catch (\Exception $e) {
            $this->logger->error('IntegrationManager: Failed to log action', ['error' => $e->getMessage()]);
        }
    }
}