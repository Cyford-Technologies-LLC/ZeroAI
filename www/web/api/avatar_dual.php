<?php
    /**
     * Avatar Generation API - Production Ready Implementation
     * Supports both direct generation and streaming with comprehensive error handling
     */

    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Disable display in production
    ini_set('log_errors', 1);
    ini_set('error_log', '/tmp/avatar_api_errors.log');
    ini_set('max_execution_time', 300);
    set_time_limit(300);

// Clean any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();

    require_once '../../src/autoload.php';

    use ZeroAI\Providers\AI\Local\AvatarManager;

// API Configuration
    class AvatarAPIConfig {
        const CACHE_DIR = '/tmp/avatar_cache';
        const STATIC_DIR = '/var/www/static/avatars';
        const CHUNK_SIZE = 1048576; // 1MB chunks for streaming
        const MAX_UPLOAD_SIZE = 10485760; // 10MB max for images
        const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        const STREAM_TIMEOUT = 30;
        const CORS_ORIGIN = '*'; // Configure for production
    }

// Response Helper Class
    class APIResponse {
        public static function json($data, $statusCode = 200) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($data, JSON_PRETTY_PRINT);
            exit;
        }

        public static function error($message, $statusCode = 500, $details = null) {
            error_log("API Error [$statusCode]: $message");
            if ($details) {
                error_log("Error Details: " . json_encode($details));
            }

            $response = ['error' => $message];
            if ($details && (defined('DEBUG_MODE') && DEBUG_MODE)) {
                $response['details'] = $details;
            }

            self::json($response, $statusCode);
        }

        public static function stream($filepath, $contentType = 'video/mp4') {
            if (!file_exists($filepath)) {
                self::error('Stream file not found', 404);
            }

            $filesize = filesize($filepath);
            $filename = basename($filepath);

            // Handle range requests for video scrubbing
            $range = isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : null;

            if ($range) {
                list($unit, $ranges) = explode('=', $range, 2);
                list($start, $end) = explode('-', $ranges);

                $start = intval($start);
                $end = $end ? intval($end) : $filesize - 1;
                $length = $end - $start + 1;

                http_response_code(206);
                header("Content-Type: $contentType");
                header("Content-Range: bytes $start-$end/$filesize");
                header("Content-Length: $length");
                header("Accept-Ranges: bytes");

                $fp = fopen($filepath, 'rb');
                fseek($fp, $start);

                $buffer = 1024 * 8;
                while (!feof($fp) && ($pos = ftell($fp)) <= $end) {
                    if ($pos + $buffer > $end) {
                        $buffer = $end - $pos + 1;
                    }
                    echo fread($fp, $buffer);
                    flush();
                }

                fclose($fp);
            } else {
                header("Content-Type: $contentType");
                header("Content-Length: $filesize");
                header("Accept-Ranges: bytes");
                readfile($filepath);
            }
            exit;
        }
    }

// Input Validator Class
    class InputValidator {
        public static function validatePrompt($prompt) {
            if (empty($prompt)) {
                APIResponse::error('Prompt is required', 400);
            }

            if (strlen($prompt) > 5000) {
                APIResponse::error('Prompt too long (max 5000 characters)', 400);
            }

            return trim($prompt);
        }

        public static function validateImage($imageData) {
            if (!$imageData) return null;

            // Handle base64 encoded images
            if (strpos($imageData, 'data:image') === 0) {
                list($type, $data) = explode(';', $imageData);
                list(, $data) = explode(',', $data);

                $imageData = base64_decode($data);
                if (!$imageData) {
                    APIResponse::error('Invalid image data', 400);
                }

                if (strlen($imageData) > AvatarAPIConfig::MAX_UPLOAD_SIZE) {
                    APIResponse::error('Image too large (max 10MB)', 400);
                }

                return $imageData;
            }

            return $imageData;
        }

        public static function sanitizeOptions($input) {
            $options = [];

            // Define option types and validation rules
            $optionRules = [
                // TTS Options
                'tts_engine' => ['type' => 'string', 'allowed' => ['espeak', 'edge', 'elevenlabs', 'openai', 'coqui']],
                'tts_voice' => ['type' => 'string', 'max_length' => 100],
                'tts_speed' => ['type' => 'int', 'min' => 25, 'max' => 400],
                'tts_pitch' => ['type' => 'int', 'min' => -100, 'max' => 100],
                'tts_language' => ['type' => 'string', 'max_length' => 10],
                'tts_emotion' => ['type' => 'string', 'allowed' => ['neutral', 'happy', 'sad', 'angry', 'excited']],

                // Audio Options
                'sample_rate' => ['type' => 'int', 'allowed' => [8000, 16000, 22050, 44100, 48000]],
                'audio_format' => ['type' => 'string', 'allowed' => ['mp3', 'wav', 'ogg', 'aac']],

                // Video Options
                'codec' => ['type' => 'string', 'allowed' => ['h264_fast', 'h264_medium', 'h264_slow', 'h265', 'vp8', 'vp9']],
                'quality' => ['type' => 'string', 'allowed' => ['low', 'medium', 'high', 'ultra']],
                'fps' => ['type' => 'int', 'min' => 15, 'max' => 60],
                'bitrate' => ['type' => 'int', 'min' => 500, 'max' => 10000],
                'keyframe_interval' => ['type' => 'int', 'min' => 1, 'max' => 300],

                // Boolean Options
                'still' => ['type' => 'bool'],
                'face_detection' => ['type' => 'bool'],
                'auto_resize' => ['type' => 'bool'],
                'hardware_accel' => ['type' => 'bool'],
                'low_latency' => ['type' => 'bool'],
                'adaptive_quality' => ['type' => 'bool'],
                'split_chunks' => ['type' => 'bool'],
                'use_3d_warping' => ['type' => 'bool'],
                'use_eye_blink' => ['type' => 'bool'],
                'use_head_pose' => ['type' => 'bool'],
                'enable_websocket' => ['type' => 'bool'],
                'verbose_logging' => ['type' => 'bool'],
                'save_intermediates' => ['type' => 'bool'],
                'profile_performance' => ['type' => 'bool'],
                'beta_features' => ['type' => 'bool'],
                'ml_acceleration' => ['type' => 'bool'],

                // Float Options
                'face_confidence' => ['type' => 'float', 'min' => 0.0, 'max' => 1.0],
                'chunk_duration' => ['type' => 'float', 'min' => 0.5, 'max' => 10.0],
                'overlap_duration' => ['type' => 'float', 'min' => 0.0, 'max' => 2.0],
                'expression_scale' => ['type' => 'float', 'min' => 0.0, 'max' => 2.0],

                // String Options
                'preprocess' => ['type' => 'string', 'allowed' => ['none', 'crop', 'resize', 'full']],
                'resolution' => ['type' => 'string', 'pattern' => '/^\d+x\d+$/'],
                'enhancer' => ['type' => 'string', 'allowed' => ['none', 'gfpgan', 'restoreformer']],
                'stream_mode' => ['type' => 'string', 'allowed' => ['none', 'hls', 'dash', 'progressive']],
                'peer' => ['type' => 'string', 'max_length' => 100],

                // Integer Options
                'timeout' => ['type' => 'int', 'min' => 10, 'max' => 600],
                'chunk_length' => ['type' => 'int', 'min' => 1, 'max' => 30],
                'buffer_size' => ['type' => 'int', 'min' => 1024, 'max' => 10485760],
                'max_duration' => ['type' => 'int', 'min' => 1, 'max' => 300],
                'max_concurrent' => ['type' => 'int', 'min' => 1, 'max' => 10],
                'memory_limit' => ['type' => 'int', 'min' => 128, 'max' => 8192],
                'worker_threads' => ['type' => 'int', 'min' => 1, 'max' => 16]
            ];

            foreach ($optionRules as $key => $rule) {
                if (!isset($input[$key])) continue;

                $value = $input[$key];

                switch ($rule['type']) {
                    case 'string':
                        if (isset($rule['allowed']) && !in_array($value, $rule['allowed'])) {
                            error_log("Invalid value for $key: $value");
                            continue 2;
                        }
                        if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                            $value = substr($value, 0, $rule['max_length']);
                        }
                        if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                            error_log("Pattern mismatch for $key: $value");
                            continue 2;
                        }
                        $options[$key] = $value;
                        break;

                    case 'int':
                        $value = intval($value);
                        if (isset($rule['min']) && $value < $rule['min']) {
                            $value = $rule['min'];
                        }
                        if (isset($rule['max']) && $value > $rule['max']) {
                            $value = $rule['max'];
                        }
                        if (isset($rule['allowed']) && !in_array($value, $rule['allowed'])) {
                            error_log("Invalid int value for $key: $value");
                            continue 2;
                        }
                        $options[$key] = $value;
                        break;

                    case 'float':
                        $value = floatval($value);
                        if (isset($rule['min']) && $value < $rule['min']) {
                            $value = $rule['min'];
                        }
                        if (isset($rule['max']) && $value > $rule['max']) {
                            $value = $rule['max'];
                        }
                        $options[$key] = $value;
                        break;

                    case 'bool':
                        $options[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                }
            }

            // Handle special cases from GET parameters
            if (isset($_GET['codec'])) {
                $options['codec'] = $_GET['codec'];
            }
            if (isset($_GET['quality'])) {
                $options['quality'] = $_GET['quality'];
            }
            if (isset($_GET['format'])) {
                $options['audio_format'] = $_GET['format'];
            }

            return $options;
        }
    }

// Streaming Handler Class
    class StreamingHandler {
        private $sessionId;
        private $avatarManager;

        public function __construct($avatarManager) {
            $this->avatarManager = $avatarManager;
            $this->sessionId = uniqid('stream_', true);
        }

        public function initializeStream($prompt, $options) {
            // Start async generation
            $options['session_id'] = $this->sessionId;
            $options['streaming'] = true;

            // Initialize streaming session
            $result = $this->avatarManager->initializeStreaming($prompt, $options);

            if (!$result['success']) {
                APIResponse::error('Failed to initialize streaming', 500, $result);
            }

            return [
                'session_id' => $this->sessionId,
                'stream_url' => '/api/avatar.php?action=stream&session=' . $this->sessionId,
                'status_url' => '/api/avatar.php?action=stream_status&session=' . $this->sessionId,
                'expected_chunks' => $result['expected_chunks'] ?? 0,
                'estimated_duration' => $result['estimated_duration'] ?? 0
            ];
        }

        public function streamChunk($sessionId, $chunkIndex = null) {
            $chunkPath = $this->getChunkPath($sessionId, $chunkIndex);

            if (!file_exists($chunkPath)) {
                // Check if generation is still in progress
                $status = $this->avatarManager->getStreamStatus($sessionId);

                if ($status['completed']) {
                    APIResponse::error('Stream completed', 404);
                } else {
                    // Return empty response with retry header
                    http_response_code(204);
                    header('Retry-After: 1');
                    exit;
                }
            }

            APIResponse::stream($chunkPath);
        }

        public function getStreamStatus($sessionId) {
            $status = $this->avatarManager->getStreamStatus($sessionId);

            return [
                'session_id' => $sessionId,
                'status' => $status['status'] ?? 'unknown',
                'progress' => $status['progress'] ?? 0,
                'chunks_ready' => $status['chunks_ready'] ?? 0,
                'total_chunks' => $status['total_chunks'] ?? 0,
                'current_chunk_url' => $status['current_chunk'] ?
                    "/api/avatar.php?action=stream&session=$sessionId&chunk=" . $status['current_chunk'] : null,
                'completed' => $status['completed'] ?? false,
                'error' => $status['error'] ?? null
            ];
        }

        private function getChunkPath($sessionId, $chunkIndex = null) {
            $baseDir = AvatarAPIConfig::STATIC_DIR . '/streams/' . $sessionId;

            if ($chunkIndex !== null) {
                return $baseDir . '/chunk_' . sprintf('%04d', $chunkIndex) . '.mp4';
            }

            // Find latest available chunk
            if (!is_dir($baseDir)) {
                return null;
            }

            $chunks = glob($baseDir . '/chunk_*.mp4');
            if (empty($chunks)) {
                return null;
            }

            sort($chunks);
            return end($chunks);
        }
    }

// Main API Handler
    class AvatarAPI {
        private $avatarManager;
        private $streamingHandler;

        public function __construct() {
            // Set CORS headers
            header('Access-Control-Allow-Origin: ' . AvatarAPIConfig::CORS_ORIGIN);
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                exit(0);
            }

            try {
                $this->avatarManager = new AvatarManager(true);
                $this->streamingHandler = new StreamingHandler($this->avatarManager);
            } catch (Exception $e) {
                APIResponse::error('Failed to initialize Avatar Manager', 500, ['message' => $e->getMessage()]);
            }
        }

        public function handle() {
            $action = $_GET['action'] ?? 'generate';

            error_log("=== AVATAR API REQUEST ===");
            error_log("Action: $action");
            error_log("Method: " . $_SERVER['REQUEST_METHOD']);
            error_log("Query: " . $_SERVER['QUERY_STRING']);

            try {
                switch ($action) {
                    case 'generate':
                        $this->handleGenerate();
                        break;

                    case 'stream_init':
                        $this->handleStreamInit();
                        break;

                    case 'stream':
                        $this->handleStream();
                        break;

                    case 'stream_status':
                        $this->handleStreamStatus();
                        break;

                    case 'engines':
                        $this->handleEngines();
                        break;

                    case 'status':
                        $this->handleStatus();
                        break;

                    case 'logs':
                        $this->handleLogs();
                        break;

                    case 'test':
                        $this->handleTest();
                        break;

                    case 'server_info':
                        $this->handleServerInfo();
                        break;

                    case 'php_errors':
                        $this->handlePhpErrors();
                        break;

                    case 'clear_errors':
                        $this->handleClearErrors();
                        break;

                    case 'static':
                        $this->handleStatic();
                        break;

                    default:
                        APIResponse::error('Unknown action: ' . $action, 404);
                }
            } catch (Exception $e) {
                APIResponse::error($e->getMessage(), 500, [
                    'trace' => $e->getTraceAsString(),
                    'action' => $action
                ]);
            }
        }

        private function handleGenerate() {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                APIResponse::error('POST method required', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                APIResponse::error('Invalid JSON input', 400);
            }

            $prompt = InputValidator::validatePrompt($input['prompt'] ?? '');
            $mode = $_GET['mode'] ?? $input['mode'] ?? 'simple';

            // Process and validate options
            $options = InputValidator::sanitizeOptions($input);

            // Handle image if provided
            if (isset($input['image'])) {
                $imageData = InputValidator::validateImage($input['image']);
                if ($imageData) {
                    $options['image'] = $imageData;
                }
            }

            error_log("Generating avatar - Mode: $mode");
            error_log("Options: " . json_encode(array_keys($options)));

            // Check if streaming is requested
            if (isset($input['streaming']) && $input['streaming']) {
                $streamResult = $this->streamingHandler->initializeStream($prompt, $options);
                APIResponse::json($streamResult);
                return;
            }

            // Generate avatar
            try {
                if ($mode === 'sadtalker') {
                    $result = $this->avatarManager->generateSadTalker($prompt, $options);
                } else {
                    $result = $this->avatarManager->generateSimple($prompt, $options);
                }

                ob_end_clean();

                // Return video data
                header('Content-Type: ' . ($result['content_type'] ?? 'video/mp4'));
                header('Content-Length: ' . $result['size']);
                header('X-Avatar-Mode: ' . $mode);
                header('X-Avatar-Engine: ' . ($options['tts_engine'] ?? 'default'));

                echo $result['data'];
                exit;

            } catch (Exception $e) {
                APIResponse::error('Generation failed: ' . $e->getMessage(), 500);
            }
        }

        private function handleStreamInit() {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                APIResponse::error('POST method required', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                APIResponse::error('Invalid JSON input', 400);
            }

            $prompt = InputValidator::validatePrompt($input['prompt'] ?? '');
            $options = InputValidator::sanitizeOptions($input);

            $result = $this->streamingHandler->initializeStream($prompt, $options);
            APIResponse::json($result);
        }

        private function handleStream() {
            $sessionId = $_GET['session'] ?? null;
            if (!$sessionId) {
                APIResponse::error('Session ID required', 400);
            }

            $chunkIndex = isset($_GET['chunk']) ? intval($_GET['chunk']) : null;
            $this->streamingHandler->streamChunk($sessionId, $chunkIndex);
        }

        private function handleStreamStatus() {
            $sessionId = $_GET['session'] ?? null;
            if (!$sessionId) {
                APIResponse::error('Session ID required', 400);
            }

            $status = $this->streamingHandler->getStreamStatus($sessionId);
            APIResponse::json($status);
        }

        private function handleStatic() {
            $path = $_GET['path'] ?? '';
            if (empty($path)) {
                APIResponse::error('Path required', 400);
            }

            // Sanitize path to prevent directory traversal
            $path = str_replace('..', '', $path);
            $path = preg_replace('/[^a-zA-Z0-9\/_\-\.]/', '', $path);

            $fullPath = AvatarAPIConfig::STATIC_DIR . '/' . $path;

            if (!file_exists($fullPath)) {
                APIResponse::error('File not found', 404);
            }

            // Determine content type
            $ext = pathinfo($fullPath, PATHINFO_EXTENSION);
            $contentTypes = [
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif'
            ];

            $contentType = $contentTypes[$ext] ?? 'application/octet-stream';
            APIResponse::stream($fullPath, $contentType);
        }

        private function handleEngines() {
            $engines = [
                'espeak' => [
                    'name' => 'eSpeak (Free)',
                    'voices' => [
                        ['value' => 'en', 'label' => 'English (Default)'],
                        ['value' => 'en+f3', 'label' => 'English Female 3'],
                        ['value' => 'en+f4', 'label' => 'English Female 4'],
                        ['value' => 'en+m3', 'label' => 'English Male 3'],
                        ['value' => 'en+m4', 'label' => 'English Male 4'],
                        ['value' => 'en+f5', 'label' => 'English Female 5'],
                        ['value' => 'en+m5', 'label' => 'English Male 5']
                    ],
                    'speed_range' => [80, 400],
                    'pitch_range' => [0, 99],
                    'supports_emotion' => false,
                    'supports_streaming' => true
                ],
                'edge' => [
                    'name' => 'Microsoft Edge TTS',
                    'voices' => [
                        ['value' => 'en-US-AriaNeural', 'label' => 'Aria (Female)'],
                        ['value' => 'en-US-JennyNeural', 'label' => 'Jenny (Female)'],
                        ['value' => 'en-US-GuyNeural', 'label' => 'Guy (Male)'],
                        ['value' => 'en-US-DavisNeural', 'label' => 'Davis (Male)']
                    ],
                    'speed_range' => [50, 300],
                    'pitch_range' => [-50, 50],
                    'supports_emotion' => true,
                    'supports_streaming' => true
                ],
                'elevenlabs' => [
                    'name' => 'ElevenLabs (Premium)',
                    'voices' => [
                        ['value' => '21m00Tcm4TlvDq8ikWAM', 'label' => 'Rachel (Female)'],
                        ['value' => 'AZnzlk1XvdvUeBnXmlld', 'label' => 'Domi (Female)'],
                        ['value' => 'ErXwobaYiN019PkySvjV', 'label' => 'Antoni (Male)'],
                        ['value' => 'TxGEqnHWrfWFTfGW9XjX', 'label' => 'Josh (Male)']
                    ],
                    'speed_range' => [50, 200],
                    'pitch_range' => [-100, 100],
                    'supports_emotion' => true,
                    'supports_streaming' => true
                ],
                'openai' => [
                    'name' => 'OpenAI TTS',
                    'voices' => [
                        ['value' => 'alloy', 'label' => 'Alloy (Neutral)'],
                        ['value' => 'echo', 'label' => 'Echo (Male)'],
                        ['value' => 'fable', 'label' => 'Fable (British Male)'],
                        ['value' => 'onyx', 'label' => 'Onyx (Male)'],
                        ['value' => 'nova', 'label' => 'Nova (Female)'],
                        ['value' => 'shimmer', 'label' => 'Shimmer (Female)']
                    ],
                    'speed_range' => [25, 400],
                    'pitch_range' => [0, 0],
                    'supports_emotion' => false,
                    'supports_streaming' => true
                ],
                'coqui' => [
                    'name' => 'Coqui TTS',
                    'voices' => [
                        ['value' => 'female', 'label' => 'Female (Default)'],
                        ['value' => 'male', 'label' => 'Male (Default)']
                    ],
                    'speed_range' => [50, 200],
                    'pitch_range' => [-50, 50],
                    'supports_emotion' => true,
                    'supports_streaming' => false
                ]
            ];

            APIResponse::json(['engines' => $engines]);
        }

        private function handleStatus() {
            $result = $this->avatarManager->getStatus();
            APIResponse::json($result);
        }

        private function handleLogs() {
            $result = $this->avatarManager->getLogs();
            APIResponse::json($result);
        }

        private function handleTest() {
            $result = $this->avatarManager->testConnection();
            APIResponse::json($result);
        }

        private function handleServerInfo() {
            $currentPeer = $this->avatarManager->getCurrentPeer();
            $availablePeers = $this->avatarManager->getAvailablePeers();

            APIResponse::json([
                'current_peer' => $currentPeer,
                'available_peers' => $availablePeers,
                'streaming_enabled' => true,
                'cache_dir' => AvatarAPIConfig::CACHE_DIR,
                'static_dir' => AvatarAPIConfig::STATIC_DIR,
                'max_upload_size' => AvatarAPIConfig::MAX_UPLOAD_SIZE,
                'supported_image_types' => AvatarAPIConfig::ALLOWED_IMAGE_TYPES
            ]);
        }

        private function handlePhpErrors() {
            $errors = $this->avatarManager->getPhpErrors();
            APIResponse::json(['errors' => $errors]);
        }

        private function handleClearErrors() {
            $cleared = $this->avatarManager->clearPhpErrors();
            APIResponse::json(['cleared' => $cleared]);
        }
    }

// Initialize and run the API
    try {
        $api = new AvatarAPI();
        $api->handle();
    } catch (Exception $e) {
        APIResponse::error('Fatal error: ' . $e->getMessage(), 500);
    } finally {
        if (ob_get_level()) {
            ob_end_flush();
        }
    }
?>