<?php
namespace ZeroAI\Core;

class PeerManager {
    private static $instance = null;
    private $peersConfigPath;
    
    private function __construct() {
        $this->peersConfigPath = __DIR__ . '/../../../config/peers.json';
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
        $pythonScript = __DIR__ . '/../../../src/peer_discovery.py';
        if (file_exists($pythonScript)) {
            exec("cd " . dirname($pythonScript) . " && python peer_discovery.py 2>&1", $output, $returnCode);
            return $returnCode === 0;
        }
        return false;
    }
}


