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
        if (!file_exists($this->peersConfigPath)) {
            return [];
        }
        
        $content = file_get_contents($this->peersConfigPath);
        $data = json_decode($content, true);
        
        if (!$data || !isset($data['peers'])) {
            return [];
        }
        
        return array_map([$this, 'formatPeer'], $data['peers']);
    }
    
    public function addPeer($name, $ip, $port = 11434) {
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
            $cmd = "curl -X POST http://{$peerIp}:11434/api/pull -d '{\"name\":\"{$modelName}\"}'";
            exec($cmd . " 2>&1", $output, $returnCode);
            
            $this->logger->info('Model installation', ['peer' => $peerIp, 'model' => $modelName, 'success' => $returnCode === 0]);
            return $returnCode === 0;
        } catch (\Exception $e) {
            $this->logger->error('Model installation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function getInstalledModels($peerIp) {
        try {
            $result = @file_get_contents("http://{$peerIp}:11434/api/tags", false, stream_context_create([
                'http' => ['timeout' => 5]
            ]));
            
            if ($result === false) return [];
            
            $data = json_decode($result, true);
            return array_column($data['models'] ?? [], 'name');
        } catch (\Exception $e) {
            return [];
        }
    }
}


