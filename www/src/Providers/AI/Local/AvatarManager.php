<?php

namespace ZeroAI\Providers\AI\Local;

use ZeroAI\Core\Logger;

class AvatarManager
{
    private $logger;
    private $avatarServiceUrl;
    private $debugMode;

    public function __construct($debugMode = true)
    {
        $this->logger = Logger::getInstance();
        $this->avatarServiceUrl = 'http://zeroai_avatar:7860';
        $this->debugMode = $debugMode;
        
        if ($this->debugMode) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
            ini_set('error_log', '/tmp/avatar_php_errors.log');
        }
        
        $this->logger->info('AvatarManager initialized', [
            'service_url' => $this->avatarServiceUrl,
            'debug_mode' => $this->debugMode
        ]);
    }

    /**
     * Generate simple OpenCV avatar
     */
    public function generateSimple($prompt, $options = [])
    {
        $this->logger->info('=== SIMPLE AVATAR REQUEST ===', [
            'prompt' => substr($prompt, 0, 100),
            'options' => $options
        ]);

        try {
            $response = $this->callAvatarService($prompt, 'simple', $options);
            
            $this->logger->info('Simple avatar generation successful', [
                'response_size' => strlen($response['data']),
                'content_type' => $response['content_type']
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('Simple avatar generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Generate SadTalker realistic avatar
     */
    public function generateSadTalker($prompt, $options = [])
    {
        $this->logger->info('=== SADTALKER AVATAR REQUEST ===', [
            'prompt' => substr($prompt, 0, 100),
            'options' => $options
        ]);

        try {
            $response = $this->callAvatarService($prompt, 'sadtalker', $options);
            
            $this->logger->info('SadTalker avatar generation successful', [
                'response_size' => strlen($response['data']),
                'content_type' => $response['content_type']
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('SadTalker avatar generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
        $url = $this->avatarServiceUrl . '/generate?mode=' . $mode;
        $data = json_encode([
            'prompt' => $prompt,
            'options' => $options
        ]);
        
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes for avatar generation
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        
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