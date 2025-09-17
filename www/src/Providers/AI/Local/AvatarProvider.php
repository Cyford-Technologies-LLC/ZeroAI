<?php

namespace ZeroAI\Providers\AI\Local;

use ZeroAI\Core\Logger;

class AvatarProvider
{
    private $logger;
    private $avatarServiceUrl;

    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->avatarServiceUrl = 'http://avatar:7860';
    }

    public function generateAvatar($prompt, $image = 'examples/source_image/art_0.png')
    {
        $this->logger->info('Avatar generation requested', [
            'prompt' => substr($prompt, 0, 100),
            'image' => $image
        ]);

        if (empty($prompt)) {
            $this->logger->error('Avatar generation failed: Empty prompt');
            throw new \Exception('Prompt is required');
        }

        try {
            $response = $this->callAvatarService($prompt, $image);
            $this->logger->info('Avatar generation successful');
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Avatar generation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function callAvatarService($prompt, $image)
    {
        // Try peer system first, fallback to local
        try {
            return $this->callPeerAvatarService($prompt, $image);
        } catch (\Exception $e) {
            $this->logger->warning('Peer avatar service failed, trying local', ['error' => $e->getMessage()]);
            return $this->callLocalAvatarService($prompt, $image);
        }
    }

    private function callPeerAvatarService($prompt, $image)
    {
        // Use peer system for GPU-accelerated avatar generation
        $peerUrl = 'http://peer:8080/avatar/generate';
        $data = json_encode([
            'prompt' => $prompt,
            'image' => $image,
            'use_gpu' => true
        ]);

        $this->logger->debug('Calling peer avatar service', ['url' => $peerUrl]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $data,
                'timeout' => 300
            ]
        ]);

        $result = @file_get_contents($peerUrl, false, $context);

        if ($result === false) {
            $error = error_get_last();
            throw new \Exception('Peer avatar service failed: ' . ($error['message'] ?? 'Unknown error'));
        }

        return json_decode($result, true);
    }

    private function callLocalAvatarService($prompt, $image)
    {
        $url = $this->avatarServiceUrl . '/generate';
        $data = json_encode([
            'prompt' => $prompt,
            'image' => $image
        ]);

        $this->logger->debug('Calling local avatar service', ['url' => $url]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $data,
                'timeout' => 300
            ]
        ]);

        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            $error = error_get_last();
            $errorMsg = $error['message'] ?? 'Unknown error';
            $this->logger->error('Local avatar service call failed', ['error' => $errorMsg]);
            throw new \Exception('Failed to call local avatar service: ' . $errorMsg);
        }

        return json_decode($result, true);
    }

    public function testConnection()
    {
        try {
            $url = $this->avatarServiceUrl . '/health';
            $result = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 10]
            ]));

            if ($result === false) {
                $this->logger->error('Avatar service health check failed');
                return ['status' => 'error', 'message' => 'Avatar service not reachable'];
            }

            $this->logger->info('Avatar service health check passed');
            return ['status' => 'success', 'message' => 'Avatar service is running'];
        } catch (\Exception $e) {
            $this->logger->error('Avatar service health check error', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}