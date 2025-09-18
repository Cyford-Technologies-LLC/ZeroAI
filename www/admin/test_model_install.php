<?php
require_once 'includes/autoload.php';

use ZeroAI\Core\PeerManager;

$peerManager = PeerManager::getInstance();

echo "<h2>Model Installation Test</h2>";

// Test getting installed models
echo "<h3>Current Installed Models:</h3>";
$models = $peerManager->getInstalledModels('ollama');
echo "<pre>" . print_r($models, true) . "</pre>";

// Test model specs
echo "<h3>Recommended Models for Local Ollama:</h3>";
$recommended = $peerManager->getRecommendedModels(16, true, 8); // 16GB RAM, GPU with 8GB
echo "<pre>" . print_r($recommended, true) . "</pre>";

// Test starting installation (uncomment to test)
/*
echo "<h3>Starting Test Installation:</h3>";
$jobId = $peerManager->startModelInstallation('ollama', 'llama3.2:1b');
echo "Job ID: " . $jobId . "<br>";

// Check status
sleep(2);
$status = $peerManager->getInstallationStatus($jobId);
echo "<pre>" . print_r($status, true) . "</pre>";
*/

echo "<p><a href='peers.php'>Back to Peers</a></p>";
?>