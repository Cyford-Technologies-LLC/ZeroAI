<?php
require_once 'includes/autoload.php';
use ZeroAI\Core\PeerManager;

$peerManager = PeerManager::getInstance();

if (isset($_POST['test_install'])) {
    echo "<h3>Testing Model Installation</h3>";
    
    // Test direct Ollama connection
    echo "<h4>1. Testing Ollama Connection:</h4>";
    $url = 'http://ollama:11434/api/tags';
    $result = @file_get_contents($url);
    if ($result) {
        echo "✅ Ollama is accessible<br>";
        $data = json_decode($result, true);
        echo "Current models: " . count($data['models'] ?? []) . "<br>";
    } else {
        echo "❌ Cannot connect to Ollama<br>";
    }
    
    // Test model installation
    echo "<h4>2. Starting Model Installation:</h4>";
    $jobId = $peerManager->startModelInstallation('ollama', 'llama3.2:1b');
    echo "Job ID: {$jobId}<br>";
    
    // Check log file
    $logFile = __DIR__ . '/../../logs/model_install_' . $jobId . '.log';
    echo "Log file: {$logFile}<br>";
    
    // Wait a moment and check status
    sleep(2);
    $status = $peerManager->getInstallationStatus($jobId);
    echo "<h4>3. Installation Status:</h4>";
    echo "<pre>" . print_r($status, true) . "</pre>";
    
    if (file_exists($logFile)) {
        echo "<h4>4. Log Contents:</h4>";
        echo "<pre>" . file_get_contents($logFile) . "</pre>";
    }
}
?>

<h2>Model Installation Test</h2>
<form method="POST">
    <input type="hidden" name="test_install" value="1">
    <button type="submit">Test Install llama3.2:1b</button>
</form>

<p><a href="peers.php">Back to Peers</a></p>