<?php
require_once __DIR__ . '/admin/includes/autoload.php';

use ZeroAI\Core\{AdminAPI, CRMAPI};

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

// Route: /api/admin/* or /api/crm/*
if (count($segments) >= 2 && $segments[0] === 'api') {
    $service = $segments[1];
    $endpoint = $segments[2] ?? '';
    
    switch ($service) {
        case 'admin':
            $api = new AdminAPI();
            $api->handle($endpoint);
            break;
            
        case 'crm':
            $api = new CRMAPI();
            $api->handle($endpoint);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Service not found']);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid API path']);
}
?>

