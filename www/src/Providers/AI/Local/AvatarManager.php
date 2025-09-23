<?php

namespace ZeroAI\Providers\AI\Local;

use ZeroAI\Core\Logger;
use ZeroAI\Core\PeerManager;

class AvatarManager
{
    private $logger;
    private $avatarServiceUrl;
    private $debugMode;
    private $peerManager;
    private $localAvatarUrl;

    public function __construct($debugMode = true)
    {
        $this->logger = Logger::getInstance();
        $this->peerManager = PeerManager::getInstance();
        $this->localAvatarUrl = 'http://zeroai_avatar:7860';
        $this->avatarServiceUrl = $this->selectBestPeer();
        $this->debugMode = $debugMode;
        
        if ($this->debugMode) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
            ini_set('error_log', '/tmp/avatar_php_errors.log');
        }
        
        $this->logger->info('AvatarManager initialized', [
            'service_url' => $this->avatarServiceUrl,
            'debug_mode' => $this->debugMode,
            'local_url' => $this->localAvatarUrl
        ]);
    }

    /**
     * Select the best peer for avatar generation
     */
    private function selectBestPeer()
    {
        try {
            $peers = $this->peerManager->getPeers();
            $bestPeer = null;
            $bestScore = -1;
            
            foreach ($peers as $peer) {
                if ($peer['status'] !== 'online') {
                    continue;
                }
                
                // Calculate peer score based on GPU and memory
                $score = 0;
                if ($peer['gpu_available']) {
                    $score += $peer['gpu_memory_gb'] * 10; // GPU memory is most important
                }
                $score += $peer['memory_gb']; // Add system memory
                
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestPeer = $peer;
                }
            }
            
            if ($bestPeer && !isset($bestPeer['is_local'])) {
                $avatarUrl = "http://{$bestPeer['ip']}:444"; // Avatar service on port 444
                $this->logger->info('Selected best peer for avatar generation', [
                    'peer' => $bestPeer['name'],
                    'ip' => $bestPeer['ip'],
                    'gpu_memory' => $bestPeer['gpu_memory_gb'],
                    'memory' => $bestPeer['memory_gb'],
                    'score' => $bestScore,
                    'url' => $avatarUrl
                ]);
                return $avatarUrl;
            }
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to select best peer, using local', [
                'error' => $e->getMessage()
            ]);
        }
        
        $this->logger->info('Using local avatar service as fallback');
        return $this->localAvatarUrl;
    }

    /**
     * Set specific peer for avatar generation
     */



    public function generateWithTTS($prompt, $mode = 'simple', $ttsEngine = 'espeak', $ttsOptions = [], $options = [])
{
    $options['tts_engine'] = $ttsEngine;
    $options['tts_options'] = $ttsOptions;

    if ($mode === 'sadtalker') {
        return $this->generateSadTalker($prompt, $options);
    } else {
        return $this->generateSimple($prompt, $options);
    }
}


    public function setPeer($peerIp = null)
    {
        if ($peerIp === null) {
            $this->avatarServiceUrl = $this->selectBestPeer();
        } else if ($peerIp === 'local') {
            $this->avatarServiceUrl = $this->localAvatarUrl;
        } else {
            $this->avatarServiceUrl = "http://{$peerIp}:444";
        }
        
        $this->logger->info('Avatar service URL updated', [
            'new_url' => $this->avatarServiceUrl,
            'peer_ip' => $peerIp
        ]);
        
        return $this->avatarServiceUrl;
    }

    /**
     * Get current peer information
     */
    public function getCurrentPeer()
    {
        if ($this->avatarServiceUrl === $this->localAvatarUrl) {
            return [
                'type' => 'local',
                'url' => $this->avatarServiceUrl,
                'name' => 'Local Avatar Service'
            ];
        }
        
        // Extract IP from URL
        if (preg_match('/http:\/\/([^:]+):444/', $this->avatarServiceUrl, $matches)) {
            $peerIp = $matches[1];
            $peers = $this->peerManager->getPeers();
            
            foreach ($peers as $peer) {
                if ($peer['ip'] === $peerIp) {
                    return [
                        'type' => 'peer',
                        'url' => $this->avatarServiceUrl,
                        'name' => $peer['name'],
                        'ip' => $peer['ip'],
                        'gpu_memory' => $peer['gpu_memory_gb'],
                        'memory' => $peer['memory_gb']
                    ];
                }
            }
        }
        
        return [
            'type' => 'unknown',
            'url' => $this->avatarServiceUrl
        ];
    }

    /**
     * Get available peers for avatar generation
     */
    public function getAvailablePeers()
    {
        try {
            $peers = $this->peerManager->getPeers();
            $availablePeers = [];
            
            // Add local option
            $availablePeers[] = [
                'id' => 'local',
                'name' => 'Local Avatar Service',
                'type' => 'local',
                'status' => 'online',
                'gpu_available' => false,
                'gpu_memory_gb' => 0,
                'memory_gb' => 0,
                'score' => 0
            ];
            
            // Add peer options
            foreach ($peers as $peer) {
                if ($peer['status'] === 'online' && !isset($peer['is_local'])) {
                    $score = 0;
                    if ($peer['gpu_available']) {
                        $score += $peer['gpu_memory_gb'] * 10;
                    }
                    $score += $peer['memory_gb'];
                    
                    $availablePeers[] = [
                        'id' => $peer['ip'],
                        'name' => $peer['name'],
                        'type' => 'peer',
                        'ip' => $peer['ip'],
                        'status' => $peer['status'],
                        'gpu_available' => $peer['gpu_available'],
                        'gpu_memory_gb' => $peer['gpu_memory_gb'],
                        'memory_gb' => $peer['memory_gb'],
                        'score' => $score
                    ];
                }
            }
            
            // Sort by score (highest first)
            usort($availablePeers, function($a, $b) {
                return $b['score'] - $a['score'];
            });
            
            return $availablePeers;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get available peers', [
                'error' => $e->getMessage()
            ]);
            
            // Return local only as fallback
            return [[
                'id' => 'local',
                'name' => 'Local Avatar Service',
                'type' => 'local',
                'status' => 'online',
                'gpu_available' => false,
                'gpu_memory_gb' => 0,
                'memory_gb' => 0,
                'score' => 0
            ]];
        }
    }

    /**
     * Generate simple OpenCV avatar
     */
    public function generateSimple($prompt, $options = [])
    {
        // Set peer if specified in options
        if (isset($options['peer'])) {
            $this->setPeer($options['peer']);
        }
        
        $currentPeer = $this->getCurrentPeer();
        $this->logger->info('=== SIMPLE AVATAR REQUEST ===', [
            'prompt' => substr($prompt, 0, 100),
            'options' => $options,
            'peer' => $currentPeer
        ]);

        try {
            $response = $this->callAvatarService($prompt, 'simple', $options);
            
            $this->logger->info('Simple avatar generation successful', [
                'response_size' => strlen($response['data']),
                'content_type' => $response['content_type'],
                'peer_used' => $currentPeer['name'] ?? 'Unknown'
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('Simple avatar generation failed on peer', [
                'error' => $e->getMessage(),
                'peer' => $currentPeer,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Try fallback to local if we were using a peer
            if ($currentPeer['type'] === 'peer') {
                $this->logger->info('Attempting fallback to local avatar service');
                $this->setPeer('local');
                
                try {
                    $response = $this->callAvatarService($prompt, 'simple', $options);
                    $this->logger->info('Simple avatar generation successful on local fallback');
                    return $response;
                } catch (\Exception $fallbackError) {
                    $this->logger->error('Local fallback also failed', [
                        'error' => $fallbackError->getMessage()
                    ]);
                }
            }
            
            throw $e;
        }
    }

    /**
     * Generate SadTalker realistic avatar
     */
    public function generateSadTalker($prompt, $options = [])
    {
        // Set peer if specified in options
        if (isset($options['peer'])) {
            $this->setPeer($options['peer']);
        }
        
        $currentPeer = $this->getCurrentPeer();
        $this->logger->info('=== SADTALKER AVATAR REQUEST ===', [
            'prompt' => substr($prompt, 0, 100),
            'options' => $options,
            'peer' => $currentPeer
        ]);

        try {
            $response = $this->callAvatarService($prompt, 'sadtalker', $options);
            
            $this->logger->info('SadTalker avatar generation successful', [
                'response_size' => strlen($response['data']),
                'content_type' => $response['content_type'],
                'peer_used' => $currentPeer['name'] ?? 'Unknown'
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('SadTalker avatar generation failed on peer', [
                'error' => $e->getMessage(),
                'peer' => $currentPeer,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Try fallback to local if we were using a peer
            if ($currentPeer['type'] === 'peer') {
                $this->logger->info('Attempting fallback to local avatar service');
                $this->setPeer('local');
                
                try {
                    $response = $this->callAvatarService($prompt, 'sadtalker', $options);
                    $this->logger->info('SadTalker avatar generation successful on local fallback');
                    return $response;
                } catch (\Exception $fallbackError) {
                    $this->logger->error('Local fallback also failed', [
                        'error' => $fallbackError->getMessage()
                    ]);
                }
            }
            
            throw $e;
        }
    }

    /**
     * Get avatar service status
     */
    public function getStatus()
    {
        $this->logger->info('Getting avatar service status');
        
        try {
            $ch = curl_init($this->avatarServiceUrl . '/debug/status');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception('Curl error: ' . $error);
            }
            
            if ($httpCode !== 200) {
                throw new \Exception('HTTP error: ' . $httpCode);
            }
            
            $status = json_decode($result, true);
            
            $this->logger->info('Avatar service status retrieved', ['status' => $status]);
            
            return [
                'status' => 'success',
                'data' => $status
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get avatar service status', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get avatar service logs
     */
    public function getLogs()
    {
        $this->logger->info('Getting avatar service logs');
        
        try {
            $ch = curl_init($this->avatarServiceUrl . '/debug/logs');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception('Curl error: ' . $error);
            }
            
            if ($httpCode !== 200) {
                throw new \Exception('HTTP error: ' . $httpCode);
            }
            
            $logs = json_decode($result, true);
            
            $this->logger->info('Avatar service logs retrieved', [
                'log_count' => count($logs['logs'] ?? [])
            ]);
            
            return [
                'status' => 'success',
                'data' => $logs
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get avatar service logs', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Test connection to avatar service
     */
    public function testConnection()
    {
        $this->logger->info('Testing avatar service connection');
        
        try {
            $ch = curl_init($this->avatarServiceUrl . '/health');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception('Connection failed: ' . $error);
            }
            
            if ($httpCode !== 200) {
                throw new \Exception('Service unavailable: HTTP ' . $httpCode);
            }
            
            $health = json_decode($result, true);
            
            $this->logger->info('Avatar service connection successful', ['health' => $health]);
            
            return [
                'status' => 'success',
                'message' => 'Avatar service is running',
                'data' => $health
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Avatar service connection failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Call avatar service with specified mode
     */
    private function callAvatarService($prompt, $mode, $options = [])
{
    $codec = $options['codec'] ?? 'h264_fast';
    $quality = $options['quality'] ?? 'high';
    $ttsEngine = $options['tts_engine'] ?? 'espeak';
    $ttsOptions = $options['tts_options'] ?? [];

    $url = $this->avatarServiceUrl . '/generate?mode=' . $mode . '&codec=' . $codec . '&quality=' . $quality;

    $payload = [
        'prompt' => $prompt,
        'tts_engine' => $ttsEngine,
        'tts_options' => $ttsOptions
    ];

    $data = json_encode($payload);
        
        $this->logger->debug('Calling avatar service', [
            'url' => $url,
            'data_length' => strlen($data),
            'mode' => $mode
        ]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $this->logger->debug('Avatar service response', [
            'http_code' => $httpCode,
            'content_type' => $contentType,
            'response_size' => strlen($result),
            'curl_error' => $error
        ]);
        
        if ($error) {
            throw new \Exception('Curl error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($result, true);
            $errorMessage = $errorData['error'] ?? 'HTTP error: ' . $httpCode;
            throw new \Exception($errorMessage);
        }
        
        if (!$result) {
            throw new \Exception('Empty response from avatar service');
        }
        
        return [
            'data' => $result,
            'content_type' => $contentType,
            'size' => strlen($result)
        ];
    }

    /**
     * Get PHP error logs
     */
    public function getPhpErrors()
    {
        try {
            $errorLog = '/tmp/avatar_php_errors.log';
            if (file_exists($errorLog)) {
                $errors = file($errorLog, FILE_IGNORE_NEW_LINES);
                return array_slice($errors, -50); // Last 50 errors
            }
            return [];
        } catch (\Exception $e) {
            return ['Error reading PHP error log: ' . $e->getMessage()];
        }
    }

    /**
     * Clear PHP error logs
     */
    public function clearPhpErrors()
    {
        try {
            $errorLog = '/tmp/avatar_php_errors.log';
            if (file_exists($errorLog)) {
                file_put_contents($errorLog, '');
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}