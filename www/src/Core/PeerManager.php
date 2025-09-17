<?php
namespace ZeroAI\Core;

class PeerManager {
    private static $instance = null;
    private $peersConfigPath;
    private $logger;
    
    private function __construct() {
        $this->peersConfigPath = __DIR__ . '/../../../config/peers.json';
        $this->logger = Logger::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getPeers() {
        $peers = [];
        
        // Add local Ollama container as first peer
        $peers[] = $this->getLocalOllamaPeer();
        
        // Add configured peers
        if (file_exists($this->peersConfigPath)) {
            $content = file_get_contents($this->peersConfigPath);
            $data = json_decode($content, true);
            
            if ($data && isset($data['peers'])) {
                $peers = array_merge($peers, array_map([$this, 'formatPeer'], $data['peers']));
            }
        }
        
        return $peers;
    }
    
    private function getLocalOllamaPeer() {
        // Use Python peer discovery to get local capabilities
        $capabilities = $this->getLocalCapabilities();
        
        return [
            'name' => 'Local Ollama',
            'ip' => 'localhost',
            'port' => 11434,
            'ollama_port' => 11434,
            'status' => $capabilities['available'] ? 'online' : 'offline',
            'models' => $capabilities['models'] ?? [],
            'memory_gb' => $capabilities['memory_gb'] ?? 8,
            'gpu_available' => $capabilities['gpu_available'] ?? false,
            'gpu_memory_gb' => $capabilities['gpu_memory_gb'] ?? 0,
            'last_check' => date('Y-m-d H:i:s'),
            'is_local' => true
        ];
    }
    
    private function getLocalCapabilities() {
        // Direct detection - simpler and more reliable
        $models = $this->getInstalledModels('localhost');
        $isAvailable = !empty($models);
        
        // Get system memory
        $memoryGb = 8; // Default
        try {
            $meminfo = file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches)) {
                $memoryGb = round($matches[1] / 1024 / 1024, 1);
            }
        } catch (Exception $e) {}
        
        // Check GPU
        $gpuAvailable = false;
        $gpuMemoryGb = 0;
        exec('nvidia-smi --query-gpu=memory.total --format=csv,noheader,nounits 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && !empty($output[0])) {
            $gpuAvailable = true;
            $gpuMemoryGb = round($output[0] / 1024, 1);
        }
        
        return [
            'available' => $isAvailable,
            'models' => $models,
            'memory_gb' => $memoryGb,
            'gpu_available' => $gpuAvailable,
            'gpu_memory_gb' => $gpuMemoryGb
        ];
    }
    
    public function addPeer($name, $ip, $port = 8080) {
        try {
            $peers = $this->loadPeersConfig();
            
            // Check if peer already exists
            foreach ($peers as $peer) {
                if ($peer['ip'] === $ip) {
                    throw new \Exception("Peer with IP {$ip} already exists");
                }
            }
            
            $newPeer = [
                'name' => $name,
                'ip' => $ip,
                'port' => (int)$port,
                'available' => false,
                'last_updated' => time()
            ];
            
            $peers[] = $newPeer;
            $this->savePeersConfig($peers);
            
            $this->logger->info('Peer added', ['name' => $name, 'ip' => $ip, 'port' => $port]);
            
            // Run discovery to check new peer
            $this->runPeerManager('status');
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to add peer', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function deletePeer($ip) {
        try {
            $peers = $this->loadPeersConfig();
            $originalCount = count($peers);
            
            $peers = array_filter($peers, function($peer) use ($ip) {
                return $peer['ip'] !== $ip;
            });
            
            if (count($peers) === $originalCount) {
                throw new \Exception("Peer with IP {$ip} not found");
            }
            
            $this->savePeersConfig(array_values($peers));
            $this->logger->info('Peer deleted', ['ip' => $ip]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete peer', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function testPeer($ip, $model = 'llama3.2:1b') {
        return $this->runPeerManager('test', ['--ip' => $ip, '--model' => $model]);
    }
    
    public function refreshPeers() {
        return $this->runPeerManager('status');
    }
    
    private function loadPeersConfig() {
        if (!file_exists($this->peersConfigPath)) {
            return [];
        }
        
        $content = file_get_contents($this->peersConfigPath);
        $data = json_decode($content, true);
        
        return $data['peers'] ?? [];
    }
    
    private function savePeersConfig($peers) {
        $configDir = dirname($this->peersConfigPath);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        $data = ['peers' => $peers];
        file_put_contents($this->peersConfigPath, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    private function runPeerManager($command, $args = []) {
        $scriptPath = __DIR__ . '/../../../run/internal/peer_manager.py';
        
        if (!file_exists($scriptPath)) {
            $this->logger->error('Peer manager script not found', ['path' => $scriptPath]);
            return false;
        }
        
        $cmd = "cd " . dirname($scriptPath) . " && python peer_manager.py {$command}";
        
        foreach ($args as $key => $value) {
            $cmd .= " {$key} {$value}";
        }
        
        $this->logger->debug('Running peer manager command', ['command' => $cmd]);
        
        exec($cmd . " 2>&1", $output, $returnCode);
        
        $this->logger->debug('Peer manager result', [
            'return_code' => $returnCode,
            'output' => implode("\n", $output)
        ]);
        
        return $returnCode === 0;
    }
    
    private function formatPeer($peer) {
        return [
            'name' => $peer['name'] ?? 'Unknown',
            'ip' => $peer['ip'] ?? '127.0.0.1',
            'port' => $peer['port'] ?? 8080,
            'ollama_port' => $peer['ollama_port'] ?? 11434,
            'status' => ($peer['available'] ?? false) ? 'online' : 'offline',
            'models' => $peer['models'] ?? [],
            'memory_gb' => $peer['memory_gb'] ?? 0,
            'gpu_available' => $peer['gpu_available'] ?? false,
            'gpu_memory_gb' => $peer['gpu_memory_gb'] ?? 0,
            'last_check' => isset($peer['last_updated']) ? date('Y-m-d H:i:s', $peer['last_updated']) : 'Never'
        ];
    }
    
    public function runPeerDiscovery() {
        return $this->runPeerManager('status');
    }
    
    // Model Management Methods
    private function getModelSpecs() {
        return [
            'essential' => [
                'llama3.2:1b' => ['size_gb' => 1.3, 'min_memory_gb' => 2, 'description' => 'Lightweight model'],
                'llama3.2:3b' => ['size_gb' => 2.0, 'min_memory_gb' => 4, 'description' => 'Balanced model']
            ],
            'medium_memory' => [
                'llama3.1:8b' => ['size_gb' => 4.7, 'min_memory_gb' => 8, 'description' => 'High-quality model'],
                'mistral:7b' => ['size_gb' => 4.1, 'min_memory_gb' => 8, 'description' => 'Fast model'],
                'codellama:7b' => ['size_gb' => 3.8, 'min_memory_gb' => 8, 'description' => 'Code model']
            ],
            'gpu_models' => [
                'llama3.1:8b-instruct-fp16' => ['size_gb' => 16, 'min_memory_gb' => 8, 'min_gpu_memory_gb' => 8, 'description' => 'GPU-optimized']
            ]
        ];
    }
    
    public function getRecommendedModels($memoryGb, $gpuAvailable = false, $gpuMemoryGb = 0) {
        $specs = $this->getModelSpecs();
        $recommended = [];
        
        foreach ($specs['essential'] as $model => $spec) {
            if ($memoryGb >= $spec['min_memory_gb']) {
                $recommended[$model] = $spec;
            }
        }
        
        if ($memoryGb >= 8) {
            foreach ($specs['medium_memory'] as $model => $spec) {
                if ($memoryGb >= $spec['min_memory_gb']) {
                    $recommended[$model] = $spec;
                }
            }
        }
        
        if ($gpuAvailable && $gpuMemoryGb >= 8) {
            foreach ($specs['gpu_models'] as $model => $spec) {
                if ($memoryGb >= $spec['min_memory_gb'] && $gpuMemoryGb >= ($spec['min_gpu_memory_gb'] ?? 0)) {
                    $recommended[$model] = $spec;
                }
            }
        }
        
        return $recommended;
    }
    
    public function installModel($peerIp, $modelName) {
        try {
            // Handle localhost/local container
            if ($peerIp === 'localhost') {
                return $this->installLocalModel($modelName);
            }
            
            $authKey = $this->getAuthKey();
            $scriptPath = __DIR__ . '/../../../run/internal/peer_manager.py';
            
            $cmd = "cd " . dirname($scriptPath) . " && python peer_manager.py install --ip {$peerIp} --model {$modelName} --auth-key {$authKey}";
            exec($cmd . " 2>&1", $output, $returnCode);
            
            $this->logger->info('Model installation', ['peer' => $peerIp, 'model' => $modelName, 'success' => $returnCode === 0]);
            return $returnCode === 0;
        } catch (\Exception $e) {
            $this->logger->error('Model installation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function removeModel($peerIp, $modelName) {
        try {
            // Handle localhost/local container
            if ($peerIp === 'localhost') {
                return $this->removeLocalModel($modelName);
            }
            
            $authKey = $this->getAuthKey();
            $scriptPath = __DIR__ . '/../../../run/internal/peer_manager.py';
            
            $cmd = "cd " . dirname($scriptPath) . " && python peer_manager.py remove --ip {$peerIp} --model {$modelName} --auth-key {$authKey}";
            exec($cmd . " 2>&1", $output, $returnCode);
            
            $this->logger->info('Model removal', ['peer' => $peerIp, 'model' => $modelName, 'success' => $returnCode === 0]);
            return $returnCode === 0;
        } catch (\Exception $e) {
            $this->logger->error('Model removal failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    private function installLocalModel($modelName) {
        try {
            $cmd = "docker exec zeroai_ollama ollama pull {$modelName}";
            exec($cmd . " 2>&1", $output, $returnCode);
            
            $this->logger->info('Local model installation', ['model' => $modelName, 'success' => $returnCode === 0]);
            return $returnCode === 0;
        } catch (\Exception $e) {
            $this->logger->error('Local model installation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    private function removeLocalModel($modelName) {
        try {
            $cmd = "docker exec zeroai_ollama ollama rm {$modelName}";
            exec($cmd . " 2>&1", $output, $returnCode);
            
            $this->logger->info('Local model removal', ['model' => $modelName, 'success' => $returnCode === 0]);
            return $returnCode === 0;
        } catch (\Exception $e) {
            $this->logger->error('Local model removal failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    

    
    private function getAuthKey() {
        $configPath = __DIR__ . '/../../../config/zeroai.json';
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            return $config['peer_manager_key'] ?? 'default_key_change_me';
        }
        return 'default_key_change_me';
    }
    
    public function getInstalledModels($peerIp) {
        try {
            // Handle localhost/local container - use ollama container name
            if ($peerIp === 'localhost') {
                $url = 'http://ollama:11434/api/tags';
            } else {
                $url = "http://{$peerIp}:11434/api/tags";
            }
            
            $result = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 5]
            ]));
            
            if ($result === false) return [];
            
            $data = json_decode($result, true);
            return array_column($data['models'] ?? [], 'name');
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function saveModelRules($rules) {
        try {
            $db = \ZeroAI\Core\DatabaseManager::getInstance();
            
            // Create table if not exists
            $db->query("
                CREATE TABLE IF NOT EXISTS model_rules (
                    category TEXT PRIMARY KEY,
                    models TEXT,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Clear existing rules
            $db->query("DELETE FROM model_rules");
            
            // Insert new rules
            foreach ($rules as $category => $models) {
                if (!empty($models)) {
                    $db->query(
                        "INSERT INTO model_rules (category, models) VALUES (?, ?)",
                        [$category, json_encode($models)]
                    );
                }
            }
            
            $this->logger->info('Model rules saved to database', $rules);
        } catch (\Exception $e) {
            $this->logger->error('Failed to save model rules', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function getModelRules() {
        try {
            $db = \ZeroAI\Core\DatabaseManager::getInstance();
            
            // Create table if not exists
            $db->query("
                CREATE TABLE IF NOT EXISTS model_rules (
                    category TEXT PRIMARY KEY,
                    models TEXT,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            $results = $db->query("SELECT category, models FROM model_rules");
            
            $rules = [
                'all_peers' => [],
                'memory_low' => [],
                'memory_medium' => [],
                'memory_high' => [],
                'gpu_low' => [],
                'gpu_high' => []
            ];
            
            foreach ($results as $row) {
                $models = json_decode($row['models'], true);
                if ($models) {
                    $rules[$row['category']] = $models;
                }
            }
            
            return $rules;
        } catch (\Exception $e) {
            $this->logger->error('Failed to load model rules', ['error' => $e->getMessage()]);
            // Return defaults on error
            return [
                'all_peers' => ['llama3.2:1b'],
                'memory_low' => [],
                'memory_medium' => [],
                'memory_high' => [],
                'gpu_low' => [],
                'gpu_high' => []
            ];
        }
    }
    
    public function exportModels() {
        try {
            $rules = $this->getModelRules();
            $peers = $this->getPeers();
            
            $export = [
                'timestamp' => date('Y-m-d H:i:s'),
                'model_rules' => $rules,
                'peers' => $peers,
                'model_specs' => $this->getModelSpecs()
            ];
            
            $this->logger->info('Models exported');
            return json_encode($export, JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $this->logger->error('Export failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function importModels($jsonData) {
        try {
            $data = json_decode($jsonData, true);
            if (!$data) throw new \Exception('Invalid JSON data');
            
            if (isset($data['model_rules'])) {
                $this->saveModelRules($data['model_rules']);
            }
            
            $this->logger->info('Models imported', ['timestamp' => $data['timestamp'] ?? 'unknown']);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Import failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}

