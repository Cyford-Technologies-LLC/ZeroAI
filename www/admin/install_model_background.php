<?php
require_once __DIR__ . '/includes/autoload.php';

use ZeroAI\Core\PeerManager;

if ($argc < 4) {
    echo "Usage: php install_model_background.php <peer_ip> <model_name> <log_file>\n";
    exit(1);
}

$peerIp = $argv[1];
$modelName = $argv[2];
$logFile = $argv[3];

$peerManager = PeerManager::getInstance();

if ($peerIp === 'ollama' || $peerIp === 'localhost') {
    $peerManager->installLocalModelWithLogging($modelName, $logFile);
} else {
    $peerManager->installRemoteModelWithLogging($peerIp, $modelName, $logFile);
}
?>