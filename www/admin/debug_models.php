<?php
require_once 'includes/autoload.php';
use ZeroAI\Core\PeerManager;

$peerManager = PeerManager::getInstance();
$peers = $peerManager->getPeers();

echo "<h2>Debug Model Installation</h2>";

foreach ($peers as $peer) {
    echo "<h3>Peer: {$peer['name']} ({$peer['ip']})</h3>";
    echo "Status: {$peer['status']}<br>";
    echo "Memory: {$peer['memory_gb']}GB<br>";
    echo "GPU: " . ($peer['gpu_available'] ? "Yes ({$peer['gpu_memory_gb']}GB)" : "No") . "<br>";
    
    echo "<h4>Installed Models:</h4>";
    $installed = $peerManager->getInstalledModels($peer['ip']);
    echo "<pre>" . print_r($installed, true) . "</pre>";
    
    echo "<h4>Recommended Models:</h4>";
    $recommended = $peerManager->getRecommendedModels($peer['memory_gb'], $peer['gpu_available'], $peer['gpu_memory_gb']);
    echo "<pre>" . print_r($recommended, true) . "</pre>";
    
    echo "<h4>Test Direct Ollama Connection:</h4>";
    $url = ($peer['ip'] === 'ollama') ? 'http://ollama:11434/api/tags' : "http://{$peer['ip']}:11434/api/tags";
    echo "Testing: $url<br>";
    
    $context = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
    $result = @file_get_contents($url, false, $context);
    
    if ($result) {
        $data = json_decode($result, true);
        echo "✅ Connection successful<br>";
        echo "Models found: " . count($data['models'] ?? []) . "<br>";
    } else {
        echo "❌ Connection failed<br>";
    }
    
    echo "<hr>";
}

// Test simple model installation
echo "<h3>Test Model Installation</h3>";
echo "<form method='POST'>";
echo "<input type='hidden' name='test_install' value='1'>";
echo "<button type='submit'>Test Install llama3.2:1b on Local Ollama</button>";
echo "</form>";

if (isset($_POST['test_install'])) {
    echo "<h4>Starting Installation...</h4>";
    $jobId = $peerManager->startModelInstallation('ollama', 'llama3.2:1b');
    echo "Job ID: $jobId<br>";
    
    // Check status immediately
    $status = $peerManager->getInstallationStatus($jobId);
    echo "<pre>" . print_r($status, true) . "</pre>";
}
?>