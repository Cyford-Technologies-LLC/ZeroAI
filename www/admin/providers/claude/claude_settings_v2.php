<?php
try {
    require_once __DIR__ . '/../../../src/bootstrap.php';
} catch (Exception $e) {
    try {
        $logger = \ZeroAI\Core\Logger::getInstance();
        $logger->logClaude('Claude settings v2 bootstrap failed: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    } catch (Exception $logError) {
        error_log('Claude Settings V2 Bootstrap Error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo 'System error';
    exit;
}

require_once __DIR__ . '/../src/autoload.php';

use ZeroAI\Admin\ClaudeSettingsAdmin;

$pageTitle = 'Claude AI Settings - ZeroAI';
$currentPage = 'claude_settings';

$claudeSettings = new ClaudeSettingsAdmin($pageTitle, $currentPage);
$claudeSettings->render();