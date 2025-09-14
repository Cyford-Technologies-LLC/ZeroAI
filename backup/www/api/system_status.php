<?php
header('Content-Type: application/json');

$type = $_GET['type'] ?? 'all';

switch ($type) {
    case 'ai':
        echo json_encode(getAIStatus());
        break;
    case 'crews':
        echo json_encode(getActiveCrews());
        break;
    case 'resources':
        echo json_encode(getSystemResources());
        break;
    default:
        echo json_encode([
            'success' => true,
            'ai' => getAIStatus(),
            'crews' => getActiveCrews(),
            'resources' => getSystemResources()
        ]);
}

function getAIStatus() {
    $status = ['success' => true];
    
    // Check Ollama status
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://ollama:11434/api/tags');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            $status['ollama'] = [
                'status' => 'running',
                'models' => count($data['models'] ?? [])
            ];
        } else {
            $status['ollama'] = ['status' => 'offline'];
        }
    } catch (Exception $e) {
        $status['ollama'] = ['status' => 'error'];
    }
    
    // Check Claude status (if API key is configured)
    $env_file = '../.env';
    if (file_exists($env_file)) {
        $env_content = file_get_contents($env_file);
        if (strpos($env_content, 'ANTHROPIC_API_KEY=') !== false) {
            $status['claude'] = ['status' => 'available'];
        } else {
            $status['claude'] = ['status' => 'not_configured'];
        }
    } else {
        $status['claude'] = ['status' => 'not_configured'];
    }
    
    return $status;
}

function getActiveCrews() {
    // Check for running crew processes or database entries
    $crews = [];
    
    try {
        // Check for crew execution logs or database entries
        $log_dir = '../logs';
        if (is_dir($log_dir)) {
            $recent_logs = glob($log_dir . '/crew_*.log');
            foreach ($recent_logs as $log) {
                $mtime = filemtime($log);
                // Consider crews active if log was modified in last 5 minutes
                if (time() - $mtime < 300) {
                    $crews[] = [
                        'id' => basename($log, '.log'),
                        'status' => 'running',
                        'started' => date('Y-m-d H:i:s', $mtime)
                    ];
                }
            }
        }
        
        return ['success' => true, 'crews' => $crews];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getSystemResources() {
    $resources = ['success' => true];
    
    try {
        // Get CPU usage (Linux/Unix)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $resources['cpu'] = round($load[0] * 100 / 4, 1); // Assuming 4 cores
        }
        
        // Get memory usage
        if (function_exists('memory_get_usage')) {
            $memory_used = memory_get_usage(true);
            $memory_limit = ini_get('memory_limit');
            if ($memory_limit !== '-1') {
                $memory_limit_bytes = convertToBytes($memory_limit);
                $resources['memory'] = round(($memory_used / $memory_limit_bytes) * 100, 1);
            }
        }
        
        // Get disk usage
        $disk_free = disk_free_space('.');
        $disk_total = disk_total_space('.');
        if ($disk_free && $disk_total) {
            $resources['disk'] = round((($disk_total - $disk_free) / $disk_total) * 100, 1);
        }
        
    } catch (Exception $e) {
        $resources['error'] = $e->getMessage();
    }
    
    return $resources;
}

function convertToBytes($value) {
    $unit = strtolower(substr($value, -1));
    $value = (int) $value;
    
    switch ($unit) {
        case 'g': $value *= 1024;
        case 'm': $value *= 1024;
        case 'k': $value *= 1024;
    }
    
    return $value;
}
?>