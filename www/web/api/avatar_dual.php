<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', '/tmp/avatar_api_errors.log');
    ob_clean();
    ob_start();
    ini_set('max_execution_time', 300);
    set_time_limit(300);

    require_once '../../src/autoload.php';

    use ZeroAI\Providers\AI\Local\AvatarManager;

    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    try {
        error_log('=== AVATAR DUAL API REQUEST ===');
        error_log('Method: ' . $_SERVER['REQUEST_METHOD']);
        error_log('Query: ' . $_SERVER['QUERY_STRING']);
        error_log('Headers: ' . json_encode(getallheaders()));

        $avatarManager = new AvatarManager(true); // Debug mode ON

        $action = $_GET['action'] ?? 'generate';
        $mode = $_GET['mode'] ?? 'simple';

        error_log("Action: $action, Mode: $mode");

        switch ($action) {
            case 'generate':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('POST method required for generation');
                }

                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) {
                    throw new Exception('Invalid JSON input');
                }

                $prompt = $input['prompt'] ?? '';
                if (empty($prompt)) {
                    throw new Exception('Prompt is required');
                }

                error_log('Raw input received: ' . json_encode($input, JSON_PRETTY_PRINT));

                // Build comprehensive options array from frontend data
                $options = [];

                // Core TTS options
                if (isset($input['tts_engine'])) {
                    $options['tts_engine'] = $input['tts_engine'];
                }
                if (isset($input['tts_voice'])) {
                    $options['tts_voice'] = $input['tts_voice'];
                }
                if (isset($input['tts_speed'])) {
                    $options['tts_speed'] = intval($input['tts_speed']);
                }
                if (isset($input['tts_pitch'])) {
                    $options['tts_pitch'] = intval($input['tts_pitch']);
                }
                if (isset($input['tts_language'])) {
                    $options['tts_language'] = $input['tts_language'];
                }
                if (isset($input['tts_emotion'])) {
                    $options['tts_emotion'] = $input['tts_emotion'];
                }

                // Audio format options
                if (isset($input['sample_rate'])) {
                    $options['sample_rate'] = intval($input['sample_rate']);
                }
                if (isset($input['format'])) {
                    $options['audio_format'] = $input['format'];
                }

                // Image options
                if (isset($input['image']) && !empty($input['image'])) {
                    $options['image'] = $input['image'];
                    error_log('Image data received: ' . substr($input['image'], 0, 100) . '...');
                }
                if (isset($input['still'])) {
                    $options['still'] = (bool)$input['still'];
                }
                if (isset($input['preprocess'])) {
                    $options['preprocess'] = $input['preprocess'];
                }
                if (isset($input['resolution'])) {
                    $options['resolution'] = $input['resolution'];
                }
                if (isset($input['face_detection'])) {
                    $options['face_detection'] = (bool)$input['face_detection'];
                }
                if (isset($input['face_confidence'])) {
                    $options['face_confidence'] = floatval($input['face_confidence']);
                }
                if (isset($input['auto_resize'])) {
                    $options['auto_resize'] = (bool)$input['auto_resize'];
                }

                // Video codec and quality
                $options['codec'] = $_GET['codec'] ?? $input['codec'] ?? 'h264_fast';
                $options['quality'] = $_GET['quality'] ?? $input['quality'] ?? 'medium';

                if (isset($input['fps'])) {
                    $options['fps'] = intval($input['fps']);
                }
                if (isset($input['bitrate'])) {
                    $options['bitrate'] = intval($input['bitrate']);
                }
                if (isset($input['keyframe_interval'])) {
                    $options['keyframe_interval'] = intval($input['keyframe_interval']);
                }
                if (isset($input['hardware_accel'])) {
                    $options['hardware_accel'] = (bool)$input['hardware_accel'];
                }

                // Streaming options - CRITICAL FOR STREAMING SUPPORT
                if (isset($input['stream_mode'])) {
                    $options['stream_mode'] = $input['stream_mode'];
                    error_log('Stream mode detected: ' . $input['stream_mode']);
                }
                if (isset($input['chunk_duration'])) {
                    $options['chunk_duration'] = floatval($input['chunk_duration']);
                }
                if (isset($input['buffer_size'])) {
                    $options['buffer_size'] = intval($input['buffer_size']);
                }
                if (isset($input['low_latency'])) {
                    $options['low_latency'] = (bool)$input['low_latency'];
                }
                if (isset($input['adaptive_quality'])) {
                    $options['adaptive_quality'] = (bool)$input['adaptive_quality'];
                }

                // SadTalker specific options
                if (isset($input['timeout'])) {
                    $options['timeout'] = intval($input['timeout']);
                }
                if (isset($input['enhancer']) && !empty($input['enhancer'])) {
                    $options['enhancer'] = $input['enhancer'];
                }
                if (isset($input['split_chunks'])) {
                    $options['split_chunks'] = (bool)$input['split_chunks'];
                }
                if (isset($input['chunk_length'])) {
                    $options['chunk_length'] = intval($input['chunk_length']);
                }
                if (isset($input['overlap_duration'])) {
                    $options['overlap_duration'] = floatval($input['overlap_duration']);
                }
                if (isset($input['expression_scale'])) {
                    $options['expression_scale'] = floatval($input['expression_scale']);
                }
                if (isset($input['use_3d_warping'])) {
                    $options['use_3d_warping'] = (bool)$input['use_3d_warping'];
                }
                if (isset($input['use_eye_blink'])) {
                    $options['use_eye_blink'] = (bool)$input['use_eye_blink'];
                }
                if (isset($input['use_head_pose'])) {
                    $options['use_head_pose'] = (bool)$input['use_head_pose'];
                }

                // Advanced options
                if (isset($input['max_duration'])) {
                    $options['max_duration'] = intval($input['max_duration']);
                }
                if (isset($input['max_concurrent'])) {
                    $options['max_concurrent'] = intval($input['max_concurrent']);
                }
                if (isset($input['memory_limit'])) {
                    $options['memory_limit'] = intval($input['memory_limit']);
                }
                if (isset($input['enable_websocket'])) {
                    $options['enable_websocket'] = (bool)$input['enable_websocket'];
            }
                if (isset($input['verbose_logging'])) {
                    $options['verbose_logging'] = (bool)$input['verbose_logging'];
                }
                if (isset($input['save_intermediates'])) {
                    $options['save_intermediates'] = (bool)$input['save_intermediates'];
                }
                if (isset($input['profile_performance'])) {
                    $options['profile_performance'] = (bool)$input['profile_performance'];
                }
                if (isset($input['beta_features'])) {
                    $options['beta_features'] = (bool)$input['beta_features'];
                }
                if (isset($input['ml_acceleration'])) {
                    $options['ml_acceleration'] = (bool)$input['ml_acceleration'];
                }
                if (isset($input['worker_threads'])) {
                    $options['worker_threads'] = intval($input['worker_threads']);
                }

                // Peer selection
                if (isset($input['peer'])) {
                    $options['peer'] = $input['peer'];
                }

                error_log("Generating avatar - Mode: $mode, Stream Mode: " . ($options['stream_mode'] ?? 'complete'));
                error_log("Complete options extracted (" . count($options) . " parameters): " . json_encode($options, JSON_PRETTY_PRINT));

                // Generate avatar using the appropriate method
                if ($mode === 'sadtalker') {
                    $result = $avatarManager->generateSadTalker($prompt, $options);
                } else {
                    $result = $avatarManager->generateSimple($prompt, $options);
                }

                ob_end_clean();

                // Check if this is a streaming response
                if (isset($result['type']) && strpos($result['type'], 'streaming') === 0) {
                    error_log('Streaming response detected: ' . $result['type']);

                    // Handle different streaming types
                    if ($result['type'] === 'streaming') {
                        // JSON chunk information
                        header('Content-Type: application/json');
                        echo json_encode($result['data']);
                        exit;

                    } elseif ($result['type'] === 'streaming_multipart') {
                        // Multipart stream (JPEG frames or video chunks)
                        header('Content-Type: ' . $result['content_type']);
                        echo $result['data'];
                        exit;

                    } elseif ($result['type'] === 'streaming_raw') {
                        // Raw streaming data
                        header('Content-Type: ' . $result['content_type']);
                        echo $result['data'];
                        exit;
                    }
                }

                // Regular video response (existing working code)
                header('Content-Type: ' . $result['content_type']);
                header('Content-Length: ' . $result['size']);
                header('X-Avatar-Mode: ' . $mode);
                header('X-Avatar-Size: ' . $result['size']);
                header('X-Avatar-Engine: ' . ($options['tts_engine'] ?? 'unknown'));
                header('X-Avatar-Voice: ' . ($options['tts_voice'] ?? 'unknown'));
                header('X-Avatar-Options-Count: ' . count($options));
                header('X-Avatar-Parameters: ' . implode(',', array_keys($options)));

                echo $result['data'];
                exit;

            case 'engines':
                // Return available TTS engines and voices
                error_log('Getting TTS engines information');
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
                        'pitch_range' => [0, 99]
                    ],
                    'edge' => [
                        'name' => 'Microsoft Edge TTS',
                        'voices' => [
                            ['value' => 'en-US-AriaNeural', 'label' => 'Aria (Female)'],
                            ['value' => 'en-US-JennyNeural', 'label' => 'Jenny (Female)'],
                            ['value' => 'en-US-GuyNeural', 'label' => 'Guy (Male)'],
                            ['value' => 'en-US-DavisNeural', 'label' => 'Davis (Male)'],
                            ['value' => 'en-US-JaneNeural', 'label' => 'Jane (Female)'],
                            ['value' => 'en-US-JasonNeural', 'label' => 'Jason (Male)'],
                            ['value' => 'en-US-SaraNeural', 'label' => 'Sara (Female)'],
                            ['value' => 'en-US-TonyNeural', 'label' => 'Tony (Male)']
                        ],
                        'speed_range' => [50, 300],
                        'pitch_range' => [-50, 50]
                    ],
                    'elevenlabs' => [
                        'name' => 'ElevenLabs (Premium)',
                        'voices' => [
                            ['value' => '21m00Tcm4TlvDq8ikWAM', 'label' => 'Rachel (Female)'],
                            ['value' => 'AZnzlk1XvdvUeBnXmlld', 'label' => 'Domi (Female)'],
                            ['value' => 'EXAVITQu4vr4xnSDxMaL', 'label' => 'Bella (Female)'],
                            ['value' => 'ErXwobaYiN019PkySvjV', 'label' => 'Antoni (Male)'],
                            ['value' => 'MF3mGyEYCl7XYWbV9V6O', 'label' => 'Elli (Female)'],
                            ['value' => 'TxGEqnHWrfWFTfGW9XjX', 'label' => 'Josh (Male)']
                        ],
                        'speed_range' => [50, 200],
                        'pitch_range' => [-100, 100]
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
                        'pitch_range' => [0, 0]
                    ],
                    'coqui' => [
                        'name' => 'Coqui TTS',
                        'voices' => [
                            ['value' => 'female', 'label' => 'Female (Default)'],
                            ['value' => 'male', 'label' => 'Male (Default)'],
                            ['value' => 'female_emotional', 'label' => 'Female (Emotional)'],
                            ['value' => 'male_emotional', 'label' => 'Male (Emotional)']
                        ],
                        'speed_range' => [50, 200],
                        'pitch_range' => [-50, 50]
                    ]
                ];
                echo json_encode(['engines' => $engines]);
                break;

            case 'status':
                error_log('Getting avatar service status');
                $result = $avatarManager->getStatus();
                echo json_encode($result);
                break;

            case 'logs':
                error_log('Getting avatar service logs');
                $result = $avatarManager->getLogs();
                echo json_encode($result);
                break;

            case 'test':
                error_log('Testing avatar service connection');
                $result = $avatarManager->testConnection();
                echo json_encode($result);
                break;

            case 'php_errors':
                error_log('Getting PHP errors');
                $errors = $avatarManager->getPhpErrors();
                echo json_encode(['errors' => $errors]);
                break;

            case 'clear_errors':
                error_log('Clearing PHP errors');
                $cleared = $avatarManager->clearPhpErrors();
                echo json_encode(['cleared' => $cleared]);
                break;

            case 'server_info':
                error_log('Getting server connection info');
                $currentPeer = $avatarManager->getCurrentPeer();
                $availablePeers = $avatarManager->getAvailablePeers();
                echo json_encode([
                    'current_peer' => $currentPeer,
                    'available_peers' => $availablePeers
                ]);
                break;

            default:
                throw new Exception('Unknown action: ' . $action);
        }

    } catch (Exception $e) {
        error_log('Avatar API Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());

        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request_info' => [
                'method' => $_SERVER['REQUEST_METHOD'],
                'query' => $_SERVER['QUERY_STRING'],
                'action' => $action ?? 'unknown',
                'mode' => $mode ?? 'unknown',
                'input_keys' => isset($input) ? array_keys($input) : [],
                'options_count' => isset($options) ? count($options) : 0
            ]
        ]);
    }
?>