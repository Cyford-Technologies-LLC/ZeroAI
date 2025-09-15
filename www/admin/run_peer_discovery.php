<?php
require_once __DIR__ . '/includes/autoload.php';

try {
    $peerManager = \ZeroAI\Core\PeerManager::getInstance();
    $result = $peerManager->runPeerDiscovery();
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Peer discovery completed' : 'Peer discovery failed'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>