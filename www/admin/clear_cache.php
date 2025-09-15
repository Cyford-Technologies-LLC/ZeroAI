<?php
require_once 'includes/autoload.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? '';

$cache = \ZeroAI\Core\CacheManager::getInstance();
$success = false;

switch ($type) {
    case 'apcu':
        if (extension_loaded('apcu')) {
            $success = apcu_clear_cache();
        }
        break;
    case 'opcache':
        if (extension_loaded('opcache')) {
            $success = opcache_reset();
        }
        break;
    case 'redis':
        $success = $cache->flush();
        break;
    case 'all':
        $success = true;
        if (extension_loaded('apcu')) {
            $success &= apcu_clear_cache();
        }
        if (extension_loaded('opcache')) {
            $success &= opcache_reset();
        }
        $success &= $cache->flush();
        break;
}

echo json_encode(['success' => $success]);


