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
        // Test Ollama directly on port 11434
        $isOnline = $this->testOllamaConnection();
        $capabilities = $this->getLocalCapabilities();
        
        return [
            'name' => 'Local Ollama',
            'ip' => 'ollama',
            'port' => 11434,
            'ollama_port' => 11434,
            'status' => $isOnline ? 'online' : 'offline',
            'models' => $capabilities['models'] ?? [],
            'memory_gb' => $capabilities['memory_gb'] ?? 8,
            'gpu_available' => $capabilities['gpu_available'] ?? false,
            'gpu_memory_gb' => $capabilities['gpu_memory_gb'] ?? 0,
            'last_check' => date('Y-m-d H:i:s'),
            'is_local' => true
        ];
    }
    
    private function testOllamaConnection() {
        try {
            $url = 'http://ollama:11434/api/tags';
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'method' => 'GET',
                    'ignore_errors' => true
                ]
            ]);
            
            $result = @file_get_contents($url, false, $context);
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // ... rest of the methods remain the same
}