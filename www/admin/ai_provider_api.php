<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_auth();

require_once __DIR__ . '/../src/Providers/AI/AIProviderFactory.php';
use ZeroAI\Providers\AI\AIProviderFactory;

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'test':
            $provider = AIProviderFactory::create($input['provider'], $input['apiKey']);
            $valid = $provider->validateApiKey();
            echo json_encode(['success' => $valid, 'error' => $valid ? null : 'Invalid API key']);
            break;
            
        case 'models':
            $provider = AIProviderFactory::create($input['provider'], $input['apiKey']);
            $models = $provider->getModels();
            echo json_encode(['success' => true, 'models' => $models]);
            break;
            
        case 'save':
            $configFile = '/app/data/ai_providers.json';
            if (!\ZeroAI\Core\InputValidator::validatePath($configFile)) {
                throw new Exception('Invalid file path');
            }
            $configs = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
            $provider = \ZeroAI\Core\InputValidator::sanitize($input['provider']);
            $config = array_map([\ZeroAI\Core\InputValidator::class, 'sanitize'], $input['config']);
            $configs[$provider] = $config;
            file_put_contents($configFile, json_encode($configs, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]);
            break;
            
        case 'load':
            $configFile = '/app/data/ai_providers.json';
            if (!\ZeroAI\Core\InputValidator::validatePath($configFile)) {
                throw new Exception('Invalid file path');
            }
            $configs = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
            echo json_encode(['success' => true, 'providers' => $configs]);
            break;
            
        case 'chat':
            $configFile = '/app/data/ai_providers.json';
            $configs = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
            
            if (!isset($configs[$input['provider']])) {
                throw new Exception('Provider not configured');
            }
            
            $config = $configs[$input['provider']];
            $provider = AIProviderFactory::create($input['provider'], $config['apiKey']);
            
            $response = $provider->chat($input['message'], [
                'model' => $input['model'] ?? $config['model'],
                'system' => $input['system'] ?? '',
                'history' => $input['history'] ?? []
            ]);
            
            echo json_encode(['success' => true, 'response' => $response]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


