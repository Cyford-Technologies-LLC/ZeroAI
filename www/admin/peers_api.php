<?php
header('Content-Type: application/json');

try {
    // Try to get cached peer status first
    $cacheFile = '/app/data/peer_status_cache.json';
    
    if (file_exists($cacheFile)) {
        $peersData = json_decode(file_get_contents($cacheFile), true);
        if ($peersData && isset($peersData['peers'])) {
            echo json_encode(['success' => true, 'peers' => $peersData['peers']]);
            exit;
        }
    }
    
    // Fallback to direct check if cache not available
    $peersFile = '/app/config/peers.json';
    
    if (!file_exists($peersFile)) {
        echo json_encode(['success' => false, 'error' => 'Peers config file not found']);
        exit;
    }
    
    $peersData = json_decode(file_get_contents($peersFile), true);
    
    if (!$peersData || !isset($peersData['peers'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid peers config']);
        exit;
    }
    
    // Quick status check with very short timeout
    foreach ($peersData['peers'] as &$peer) {
        $peer['status'] = checkPeerStatus($peer['ip'], $peer['port']);
        $peer['last_check'] = date('Y-m-d H:i:s');
    }
    
    echo json_encode(['success' => true, 'peers' => $peersData['peers']]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function checkPeerStatus($ip, $port) {
    $timeout = 1;
    $connection = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    
    if ($connection) {
        fclose($connection);
        return 'online';
    } else {
        return 'offline';
    }
}
?>