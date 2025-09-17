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
}


