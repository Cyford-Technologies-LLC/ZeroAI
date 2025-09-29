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

    private $streamBuffer = '';
    private $streamChunks = [];
    private $currentChunkIndex = 0;

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
        
        $this->logger->info('AvatarManager initialized with streaming support', [
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
                $avatarUrl = "http://{$bestPeer['ip']}:444"; // Avatar service on port 7860
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
     * Main generation method - automatically detects streaming vs regular generation
     */
    public function generateAvatar($prompt, $options = [])
    {
        // Detect if this should be a streaming request
        $isStreaming = $this->isStreamingRequest($options);

        if ($isStreaming) {
            return $this->handleStreamingGeneration($prompt, $options);
        } else {
            // Traditional complete generation
            $mode = $options['mode'] ?? 'simple';
            return $mode === 'sadtalker'
                ? $this->generateSadTalker($prompt, $options)
                : $this->generateSimple($prompt, $options);
        }
    }

    /**
     * Detect if request should use streaming
     */
    private function isStreamingRequest($options)
    {
        // Check for streaming indicators
        $streamMode = $options['stream_mode'] ?? 'complete';

        // If explicitly set to streaming modes
        if (in_array($streamMode, ['chunked', 'realtime', 'websocket'])) {
            return true;
        }

        // Check for streaming-specific parameters
        $streamingParams = [
            'chunk_duration', 'buffer_size', 'low_latency',
            'enable_websocket', 'adaptive_quality'
        ];

        foreach ($streamingParams as $param) {
            if (isset($options[$param]) && $options[$param] !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle streaming generation requests
     */
    private function handleStreamingGeneration($prompt, $options)
    {
        $this->logger->info('=== STREAMING AVATAR REQUEST ===', [
            'prompt' => substr($prompt, 0, 100),
            'stream_mode' => $options['stream_mode'] ?? 'auto-detected',
            'options_count' => count($options)
        ]);

        try {
            // Check if streaming is available
            $streamingStatus = $this->checkStreamingAvailability();
            if (!$streamingStatus['available']) {
                $this->logger->warning('Streaming not available, falling back to complete generation');
                return $this->generateComplete($prompt, $options);
            }

            // Determine streaming mode
            $streamMode = $options['stream_mode'] ?? $this->determineOptimalStreamingMode($options);

            switch ($streamMode) {
                case 'realtime':
                    return $this->generateRealtimeStream($prompt, $options);

                case 'chunked':
                    return $this->generateChunkedStream($prompt, $options);

                case 'websocket':
                    return $this->initiateWebSocketStream($prompt, $options);

                default:
                    $this->logger->info('Unknown streaming mode, using chunked as default');
                    return $this->generateChunkedStream($prompt, $options);
            }

        } catch (\Exception $e) {
            $this->logger->error('Streaming generation failed, falling back to complete', [
                'error' => $e->getMessage()
            ]);
            return $this->generateComplete($prompt, $options);
        }
    }

    /**
     * Check if streaming is available on the target service
     */
    private function checkStreamingAvailability()
    {
        try {
            $ch = curl_init($this->avatarServiceUrl . '/debug/streaming');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $status = json_decode($result, true);
                return [
                    'available' => $status['streaming_available'] ?? false,
                    'modes' => $status['supported_modes'] ?? [],
                    'websocket' => $status['websocket_enabled'] ?? false
                ];
            }
        } catch (\Exception $e) {
            $this->logger->warning('Could not check streaming availability', [
                'error' => $e->getMessage()
            ]);
        }

        return ['available' => false, 'modes' => [], 'websocket' => false];
    }

    /**
     * Determine optimal streaming mode based on options
     */
    private function determineOptimalStreamingMode($options)
    {
        // If low latency is required, use realtime
        if (isset($options['low_latency']) && $options['low_latency']) {
            return 'realtime';
        }

        // If WebSocket is enabled, prefer WebSocket
        if (isset($options['enable_websocket']) && $options['enable_websocket']) {
            return 'websocket';
        }

        // For longer content or when chunking is explicitly enabled
        if (isset($options['split_chunks']) && $options['split_chunks']) {
            return 'chunked';
        }

        // Default to chunked streaming
        return 'chunked';
    }

    /**
     * Generate realtime stream
     */
    private function generateRealtimeStream($prompt, $options)
    {
        $this->logger->info('Generating realtime stream');

        $endpoint = '/stream';
        $payload = $this->buildStreamingPayload($prompt, $options, 'realtime');

        return $this->callStreamingService($endpoint, $payload, 'realtime');
    }

    /**
     * Generate chunked stream
     */
    private function generateChunkedStream($prompt, $options)
    {
        $this->logger->info('Generating chunked stream');

        $endpoint = '/stream';
        $payload = $this->buildStreamingPayload($prompt, $options, 'chunked');

        return $this->callStreamingService($endpoint, $payload, 'chunked');
    }

    /**
     * Initiate WebSocket stream
     */
    private function initiateWebSocketStream($prompt, $options)
    {
        $this->logger->info('Initiating WebSocket stream');

        // For WebSocket, we return connection info instead of actual stream
        return [
            'type' => 'websocket_info',
            'websocket_url' => str_replace('http://', 'ws://', $this->avatarServiceUrl) . '/stream/ws',
            'payload' => $this->buildStreamingPayload($prompt, $options, 'websocket'),
            'instructions' => 'Connect to WebSocket URL and send payload to start streaming'
        ];
    }

    /**
     * Generate complete video (fallback)
     */
    private function generateComplete($prompt, $options)
    {
        $this->logger->info('Falling back to complete generation');
        $mode = $options['mode'] ?? 'simple';

        return $mode === 'sadtalker'
            ? $this->generateSadTalker($prompt, $options)
            : $this->generateSimple($prompt, $options);
    }

    /**
     * Build streaming payload with all necessary parameters
     */
    private function buildStreamingPayload($prompt, $options, $streamingMode)
    {
        $payload = [
            'prompt' => $prompt,
            'streaming_mode' => $streamingMode
        ];

        // Add delivery mode if specified
        if (isset($options['delivery_mode'])) {
            $payload['delivery_mode'] = $options['delivery_mode'];
        }



        // Core parameters
        $coreParams = [
            'tts_engine', 'tts_voice', 'tts_speed', 'tts_pitch', 'tts_language', 'tts_emotion',
            'delivery_mode',  // ADD THIS LINE
            'image', 'codec', 'quality', 'fps'
        ];

        foreach ($coreParams as $param) {
            if (isset($options[$param])) {
                $payload[$param] = $options[$param];
            }
        }

        // Streaming-specific parameters
        $streamingParams = [
            'chunk_duration' => 3.0,
            'frame_rate' => 20,
            'buffer_size' => 5,
            'low_latency' => false,
            'adaptive_quality' => true
        ];

        foreach ($streamingParams as $param => $default) {
            $payload[$param] = $options[$param] ?? $default;
        }

        // Mode-specific optimizations
        switch ($streamingMode) {
            case 'realtime':
                $payload['frame_rate'] = min($payload['frame_rate'], 15); // Lower FPS for realtime
                $payload['buffer_size'] = min($payload['buffer_size'], 3); // Smaller buffer
                $payload['low_latency'] = true;
                break;

            case 'chunked':
                $payload['chunk_duration'] = $payload['chunk_duration'] ?? 5.0; // Longer chunks
                break;

            case 'websocket':
                $payload['frame_rate'] = min($payload['frame_rate'], 20);
                $payload['enable_websocket'] = true;
                break;
        }

        return $payload;
    }

    /**
     * Call streaming service endpoint
     */
    private function callStreamingService($endpoint, $payload, $mode)
    {
        $url = $this->avatarServiceUrl . $endpoint;
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $this->logger->debug('Calling streaming service', [
            'url' => $url,
            'mode' => $mode,
            'payload_size' => strlen($data),
            'parameters' => count($payload)
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0); // No timeout for streaming
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

        // Handle streaming response differently
        if ($mode === 'realtime' || $mode === 'chunked') {
            // For streaming, we might want to handle chunks
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, [$this, 'handleStreamChunk']);
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('Streaming service error: ' . $error);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($result, true);
            $errorMessage = $errorData['error'] ?? 'HTTP error: ' . $httpCode;
            throw new \Exception($errorMessage);
        }

        return [
            'type' => 'stream',
            'mode' => $mode,
            'data' => $result,
            'content_type' => $contentType,
            'size' => strlen($result)
        ];
    }

    /**
     * Handle streaming chunks (placeholder for future enhancement)
     */
    private function handleStreamChunk($ch, $chunk)
    {
        // For now, just accumulate chunks
        // In the future, this could handle real-time processing
        return strlen($chunk);
    }

    /**
     * Original generate methods (unchanged)
     */
    public function generateSimple($prompt, $options = [])
    {
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

    public function generateSadTalker($prompt, $options = [])
    {
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
     * Enhanced callAvatarService with complete parameter support
     */
//    private function callAvatarService($prompt, $mode, $options = [])
//    {
//        // Detect streaming mode
//        $streamMode = $options['stream_mode'] ?? 'complete';
//        $isStreaming = ($streamMode !== 'complete');
//
//        if ($isStreaming) {
//            // Use streaming endpoint
//            $url = $this->avatarServiceUrl . '/stream?mode=' . $mode . '&codec=' . $codec . '&quality=' . $quality;
//            $this->logger->info('Using streaming endpoint', [
//                'stream_mode' => $streamMode,
//                'url' => $url
//            ]);
//        } else {
//            // Use regular generation endpoint
//            $codec = $options['codec'] ?? 'h264_fast';
//            $quality = $options['quality'] ?? 'high';
//            $url = $this->avatarServiceUrl . '/generate?mode=' . $mode . '&codec=' . $codec . '&quality=' . $quality;
//            $this->logger->info('Using generation endpoint', [
//                'mode' => $mode,
//                'url' => $url
//            ]);
//        }
//
//        // Build comprehensive payload with ALL parameters
//        $payload = [
//            'prompt' => $prompt,
//            'tts_engine' => $options['tts_engine'] ?? 'espeak'
//        ];
//
//        if ($isStreaming) {
//            $payload['streaming_mode'] = $streamMode; // Map stream_mode to streaming_mode
//        }
//
//        // Add all the parameter mappings
//        $allParams = [
//            'tts_voice', 'tts_speed', 'tts_pitch', 'tts_language', 'tts_emotion',
//            'delivery_mode',  // ADD THIS LINE
//            'sample_rate', 'audio_format', 'image', 'still', 'preprocess', 'resolution',
//            'face_detection', 'face_confidence', 'auto_resize', 'fps', 'bitrate',
//            'keyframe_interval', 'hardware_accel', 'stream_mode', 'chunk_duration',
//            'buffer_size', 'low_latency', 'adaptive_quality', 'timeout', 'enhancer',
//            'split_chunks', 'chunk_length', 'overlap_duration', 'expression_scale',
//            'use_3d_warping', 'use_eye_blink', 'use_head_pose', 'max_duration',
//            'max_concurrent', 'memory_limit', 'enable_websocket', 'verbose_logging',
//            'save_intermediates', 'profile_performance', 'beta_features',
//            'ml_acceleration', 'worker_threads'
//        ];
//
//        foreach ($allParams as $param) {
//            if (isset($options[$param])) {
//                $payload[$param] = $options[$param];
//            }
//        }
//
//        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);
//
//        $this->logger->debug('Calling avatar service with complete parameters', [
//            'url' => $url,
//            'parameter_count' => count($payload),
//            'mode' => $mode,
//            'streaming' => $isStreaming,
//            'stream_mode' => $streamMode,
//            'data_length' => strlen($data)
//        ]);
//
//        $ch = curl_init($url);
//        curl_setopt($ch, CURLOPT_POST, 1);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
//        curl_setopt($ch, CURLOPT_HTTPHEADER, [
//            'Content-Type: application/json',
//            'Content-Length: ' . strlen($data)
//        ]);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
//        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
//
//        $result = curl_exec($ch);
//        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
//        $error = curl_error($ch);
//        curl_close($ch);
//
//        if ($error) {
//            throw new \Exception('Curl error: ' . $error);
//        }
//
//        if ($httpCode !== 200) {
//            $errorData = json_decode($result, true);
//            $errorMessage = $errorData['error'] ?? 'HTTP error: ' . $httpCode;
//            throw new \Exception($errorMessage);
//        }
//
//        if (!$result) {
//            throw new \Exception('Empty response from avatar service');
//        }
//
//        // Handle streaming responses differently based on content type
//        if ($isStreaming) {
//            $this->logger->info('Processing streaming response', [
//                'content_type' => $contentType,
//                'size' => strlen($result),
//                'stream_mode' => $streamMode
//            ]);
//
//            // Check if it's JSON response (chunk info)
//            if (strpos($contentType, 'application/json') !== false) {
//                $streamData = json_decode($result, true);
//
//                if (json_last_error() === JSON_ERROR_NONE) {
//                    return [
//                        'type' => 'streaming',
//                        'mode' => $streamMode,
//                        'data' => $streamData,
//                        'chunks' => $streamData['chunks'] ?? [],
//                        'urls' => $streamData['urls'] ?? [],
//                        'content_type' => 'application/json',
//                        'size' => strlen($result)
//                    ];
//                }
//            }
//
//            // Check if it's multipart (JPEG frames or chunked video)
//            if (strpos($contentType, 'multipart') !== false) {
//                return [
//                    'type' => 'streaming_multipart',
//                    'mode' => $streamMode,
//                    'data' => $result,
//                    'content_type' => $contentType,
//                    'size' => strlen($result)
//                ];
//            }
//
//            // Otherwise return as streaming raw
//            return [
//                'type' => 'streaming_raw',
//                'mode' => $streamMode,
//                'data' => $result,
//                'content_type' => $contentType,
//                'size' => strlen($result)
//            ];
//        }
//
//        // Regular non-streaming response (existing working code)
//        return [
//            'data' => $result,
//            'content_type' => $contentType,
//            'size' => strlen($result)
//        ];
//    }


    private function callAvatarService($prompt, $mode, $options = [])
    {
        // Detect streaming mode
        $streamMode = $options['stream_mode'] ?? 'complete';
        $isStreaming = ($streamMode !== 'complete');

        // Initialize streaming state
        $this->streamChunks = [];
        $this->currentChunkIndex = 0;

        if ($isStreaming) {
            // Use streaming endpoint
            $codec = $options['codec'] ?? 'h264_fast';
            $quality = $options['quality'] ?? 'high';
            $url = $this->avatarServiceUrl . '/stream?mode=' . $mode . '&codec=' . $codec . '&quality=' . $quality;
            $this->logger->info('Using streaming endpoint', [
                'stream_mode' => $streamMode,
                'url' => $url
            ]);
        } else {
            // Use regular generation endpoint
            $codec = $options['codec'] ?? 'h264_fast';
            $quality = $options['quality'] ?? 'high';
            $url = $this->avatarServiceUrl . '/generate?mode=' . $mode . '&codec=' . $codec . '&quality=' . $quality;
            $this->logger->info('Using generation endpoint', [
                'mode' => $mode,
                'url' => $url
            ]);
        }

        // Build comprehensive payload with ALL parameters
        $payload = [
            'prompt' => $prompt,
            'tts_engine' => $options['tts_engine'] ?? 'espeak'
        ];

        if ($isStreaming) {
            $payload['streaming_mode'] = $streamMode; // Map stream_mode to streaming_mode
        }

        // Add all the parameter mappings
        $allParams = [
            'tts_voice', 'tts_speed', 'tts_pitch', 'tts_language', 'tts_emotion',
            'delivery_mode',
            'sample_rate', 'audio_format', 'image', 'still', 'preprocess', 'resolution',
            'face_detection', 'face_confidence', 'auto_resize', 'fps', 'bitrate',
            'keyframe_interval', 'hardware_accel', 'stream_mode', 'chunk_duration',
            'buffer_size', 'low_latency', 'adaptive_quality', 'timeout', 'enhancer',
            'split_chunks', 'chunk_length', 'overlap_duration', 'expression_scale',
            'use_3d_warping', 'use_eye_blink', 'use_head_pose', 'max_duration',
            'max_concurrent', 'memory_limit', 'enable_websocket', 'verbose_logging',
            'save_intermediates', 'profile_performance', 'beta_features',
            'ml_acceleration', 'worker_threads'
        ];

        foreach ($allParams as $param) {
            if (isset($options[$param])) {
                $payload[$param] = $options[$param];
            }
        }

        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $this->logger->debug('Calling avatar service with complete parameters', [
            'url' => $url,
            'parameter_count' => count($payload),
            'mode' => $mode,
            'streaming' => $isStreaming,
            'stream_mode' => $streamMode,
            'data_length' => strlen($data)
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

        // NEW: Add streaming-specific CURL options
        if ($isStreaming) {
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, [$this, 'handleStreamData']);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'handleStreamProgress']);
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 512); // Process in smaller chunks
        }

//        $result = curl_exec($ch);

        if ($isStreaming) {
            $this->streamBuffer = ''; // Clear buffer before streaming
            curl_exec($ch); // Execute but data goes to callback
            $result = $this->streamBuffer; // Use collected buffer data
        } else {
            $result = curl_exec($ch); // Normal execution for non-streaming
        }


        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

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

        // Handle streaming responses differently based on content type
        if ($isStreaming) {
            $this->logger->info('Processing streaming response', [
                'content_type' => $contentType,
                'size' => strlen($result),
                'stream_mode' => $streamMode,
                'chunks_collected' => count($this->streamChunks)
            ]);

            // Check if it's multipart (JPEG frames or chunked video)
            if (strpos($contentType, 'multipart') !== false) {
                // Parse multipart streaming response
                $parsedChunks = $this->parseMultipartStream($result);

                return [
                    'type' => 'streaming_multipart',
                    'mode' => $streamMode,
                    'data' => $result,
                    'chunks' => $parsedChunks,
                    'incremental_chunks' => $this->streamChunks,
                    'content_type' => $contentType,
                    'size' => strlen($result),
                    'final_video' => $this->combineVideoChunks($parsedChunks)
                ];
            }

            // Check if it's JSON response (chunk info)
            if (strpos($contentType, 'application/json') !== false) {
                $streamData = json_decode($result, true);



                if (json_last_error() === JSON_ERROR_NONE) {
                    return [
                        'type' => 'streaming',
                        'mode' => $streamMode,
                        'data' => $streamData,
                        'chunks' => $streamData['chunks'] ?? [],
                        'urls' => $streamData['urls'] ?? [],
                        'incremental_chunks' => $this->streamChunks,
                        'content_type' => 'application/json',
                        'size' => strlen($result)
                    ];
                }
            }

            // Otherwise return as streaming raw
            return [
                'type' => 'streaming_raw',
                'mode' => $streamMode,
                'data' => $result,
                'incremental_chunks' => $this->streamChunks,
                'content_type' => $contentType,
                'size' => strlen($result)
            ];
        }

        // Regular non-streaming response (PRESERVED EXISTING CODE)
        return [
            'data' => $result,
            'content_type' => $contentType,
            'size' => strlen($result)
        ];
    }

    private function handleStreamData($curl, $data) {
        // Log the streaming data as it arrives
        $this->logger->info('Stream data received', [
            'data_length' => strlen($data),
            'contains_video_data' => strpos($data, 'video_data') !== false,
            'contains_base64' => strpos($data, 'base64') !== false,
            'first_100_chars' => substr($data, 0, 100)
        ]);

        // Process the streaming chunk
        $this->processStreamChunk($data);

        return strlen($data);
    }



    private function handleStreamProgress($resource, $download_size, $downloaded, $upload_size, $uploaded) {
        if ($download_size > 0) {
            $progress = ($downloaded / $download_size) * 100;
            $this->logger->debug("Streaming progress: {$progress}%", [
                'downloaded' => $downloaded,
                'total' => $download_size
            ]);
        }
        return 0;
    }

    // ... (keep all the existing utility methods: setPeer, getCurrentPeer, getAvailablePeers,
    //      getStatus, getLogs, testConnection, getPhpErrors, clearPhpErrors)

public function setPeer($peerIp = null)
{
    if ($peerIp === null) {
        $this->avatarServiceUrl = $this->selectBestPeer();
    } else if ($peerIp === 'local') {
        $this->avatarServiceUrl = $this->localAvatarUrl; // Keeps 7860
    } else {
        $this->avatarServiceUrl = "http://{$peerIp}:444"; // Back to your original 444
    }

    $this->logger->info('Avatar service URL updated', [
        'new_url' => $this->avatarServiceUrl,
        'peer_ip' => $peerIp
    ]);

    return $this->avatarServiceUrl;
}

    public function getCurrentPeer()
    {
    if ($this->avatarServiceUrl === $this->localAvatarUrl) {
        return [
            'type' => 'local',
            'url' => $this->avatarServiceUrl,
            'name' => 'Local Avatar Service'
        ];
    }

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

    public function getAvailablePeers()
    {
        try {
            $peers = $this->peerManager->getPeers();
            $availablePeers = [];

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

            usort($availablePeers, function($a, $b) {
                return $b['score'] - $a['score'];
            });

            return $availablePeers;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get available peers', [
                'error' => $e->getMessage()
            ]);

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



// NEW: Parse multipart streaming response
    private function parseMultipartStream($result) {
        $chunks = [];
        $parts = explode('--frame', $result);

        foreach ($parts as $index => $part) {
            if (empty(trim($part))) continue;

            // Extract JSON data from each part
            if (preg_match('/Content-Type: application\/json.*?\r?\n\r?\n(.*?)\r?\n/s', $part, $matches)) {
                $chunkData = json_decode($matches[1], true);
                if ($chunkData && isset($chunkData['video_data'])) {
                    $chunks[] = [
                        'id' => $index,
                        'data' => $chunkData['video_data'],
                        'duration' => $chunkData['duration'] ?? 0,
                        'ready' => $chunkData['ready'] ?? false
                    ];
                }
            }
        }

        return $chunks;
    }

// UPDATED: Your existing processStreamChunk method
    private function processStreamChunk($chunkData) {
        // Parse chunk data (handle both raw and JSON data)
        $chunk = null;

        // Try to extract JSON from multipart data
        if (strpos($chunkData, 'Content-Type: application/json') !== false) {
            if (preg_match('/\r?\n\r?\n(\{.*?\})\r?\n/s', $chunkData, $matches)) {
                $chunk = json_decode($matches[1], true);
            }
        } else {
            // Direct JSON data
            $chunk = json_decode($chunkData, true);
        }

        if ($chunk && isset($chunk['video_data'])) {
            // Store chunk for progressive and final processing
            $this->streamChunks[] = [
                'id' => $this->currentChunkIndex++,
                'data' => $chunk['video_data'],
                'duration' => $chunk['duration'] ?? 0,
                'ready' => $chunk['ready'] ?? false
            ];

            // Optional: Trigger frontend update
            $this->broadcastChunkToFrontend($chunk);
        }
    }

// UPDATED: Your existing broadcastChunkToFrontend method
    private function broadcastChunkToFrontend($chunk) {
        // WebSocket or Server-Sent Events implementation
        // For now, just log the chunk arrival
        $this->logger->info('Chunk ready for frontend', [
            'chunk_id' => $chunk['chunk_id'] ?? 'unknown',
            'duration' => $chunk['duration'] ?? 0,
            'data_size' => strlen($chunk['video_data'] ?? '')
        ]);

        // TODO: Implement actual broadcasting (WebSocket/SSE)
    }

// UPDATED: Your existing combineVideoChunks method
    private function combineVideoChunks($parsedChunks = null) {
        // Use provided chunks or collected stream chunks
        $chunks = $parsedChunks ?? $this->streamChunks;

        if (empty($chunks)) {
            return null;
        }

        // For base64 chunks, we need to decode and save them first
        $tempFiles = [];
        foreach ($chunks as $index => $chunk) {
            if (isset($chunk['data']) && strpos($chunk['data'], 'data:video/mp4;base64,') === 0) {
                // Decode base64 video data
                $base64Data = substr($chunk['data'], strlen('data:video/mp4;base64,'));
                $videoData = base64_decode($base64Data);

                // Save to temporary file
                $tempFile = tempnam(sys_get_temp_dir(), "chunk_{$index}_") . '.mp4';
                file_put_contents($tempFile, $videoData);
                $tempFiles[] = $tempFile;
            }
        }

        if (empty($tempFiles)) {
            return null;
        }

        // Create ffmpeg concat file
        $tempChunkFile = tempnam(sys_get_temp_dir(), 'avatar_chunks_');
        file_put_contents($tempChunkFile, implode("\n", array_map(function($file) {
            return "file '{$file}'";
        }, $tempFiles)));

        $finalVideoPath = tempnam(sys_get_temp_dir(), 'combined_avatar_') . '.mp4';

        // FFmpeg command to concatenate
        $cmd = "ffmpeg -f concat -safe 0 -i {$tempChunkFile} -c copy {$finalVideoPath} 2>&1";
        $output = [];
        $returnVar = 0;
        exec($cmd, $output, $returnVar);

        // Cleanup temporary files
        unlink($tempChunkFile);
        foreach ($tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        if ($returnVar === 0 && file_exists($finalVideoPath)) {
            return $finalVideoPath;
        }

        $this->logger->error('Video combination failed', [
            'command' => $cmd,
            'output' => implode("\n", $output),
            'return_code' => $returnVar
        ]);

        return null;
    }

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

    public function getPhpErrors()
    {
        try {
            $errorLog = '/tmp/avatar_php_errors.log';
            if (file_exists($errorLog)) {
                $errors = file($errorLog, FILE_IGNORE_NEW_LINES);
                return array_slice($errors, -50);
            }
            return [];
        } catch (\Exception $e) {
            return ['Error reading PHP error log: ' . $e->getMessage()];
        }
    }

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

    /**
     * Backward compatibility method - routes to new generateAvatar method
     */
    public function generateWithTTS($prompt, $mode = 'simple', $ttsEngine = 'espeak', $ttsOptions = [], $options = [])
    {
        if (!is_array($ttsOptions)) {
            $ttsOptions = [];
        }

        // Merge TTS options into main options for new method
        $options['mode'] = $mode;
        $options['tts_engine'] = $ttsEngine ?: 'espeak';

        // Map old TTS options format
        if (isset($ttsOptions['voice'])) {
            $options['tts_voice'] = $ttsOptions['voice'];
        }
        if (isset($ttsOptions['speed'])) {
            $options['tts_speed'] = $ttsOptions['speed'];
        }
        if (isset($ttsOptions['pitch'])) {
            $options['tts_pitch'] = $ttsOptions['pitch'];
        }
        if (isset($ttsOptions['language'])) {
            $options['tts_language'] = $ttsOptions['language'];
        }

        return $this->generateAvatar($prompt, $options);
    }

    /**
     * Get streaming capabilities and status
     */
    public function getStreamingInfo()
    {
        try {
            $streamingStatus = $this->checkStreamingAvailability();

            return [
                'streaming_available' => $streamingStatus['available'],
                'supported_modes' => $streamingStatus['modes'],
                'websocket_enabled' => $streamingStatus['websocket'],
                'endpoints' => [
                    'realtime' => $this->avatarServiceUrl . '/stream (realtime mode)',
                    'chunked' => $this->avatarServiceUrl . '/stream (chunked mode)',
                    'websocket' => str_replace('http://', 'ws://', $this->avatarServiceUrl) . '/stream/ws'
                ],
                'detection_params' => [
                    'stream_mode' => 'chunked|realtime|websocket',
                    'chunk_duration' => 'float (seconds)',
                    'buffer_size' => 'int (frames)',
                    'low_latency' => 'boolean',
                    'enable_websocket' => 'boolean',
                    'adaptive_quality' => 'boolean'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'streaming_available' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}