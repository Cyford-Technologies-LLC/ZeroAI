<?php
/**
 * Test script for the new OOP structure
 */

require_once __DIR__ . '/src/autoload.php';

use ZeroAI\AI\AIManager;
use ZeroAI\AI\Claude;
use ZeroAI\AI\LocalAgent;

echo "Testing ZeroAI OOP Structure\n";
echo "============================\n\n";

try {
    // Test AIManager
    echo "1. Testing AIManager...\n";
    $aiManager = new AIManager();
    $providers = $aiManager->getAvailableProviders();
    echo "Available providers: " . json_encode($providers) . "\n\n";
    
    // Test Claude if API key exists
    echo "2. Testing Claude integration...\n";
    if (getenv('ANTHROPIC_API_KEY')) {
        $claude = new Claude();
        $models = $claude->getAvailableModels();
        echo "Claude models: " . implode(', ', $models) . "\n";
        
        $testResult = $claude->testConnection();
        echo "Claude connection test: " . ($testResult['success'] ? 'SUCCESS' : 'FAILED') . "\n";
        if (!$testResult['success']) {
            echo "Error: " . $testResult['error'] . "\n";
        }
    } else {
        echo "Claude API key not found - skipping test\n";
    }
    echo "\n";
    
    // Test LocalAgent
    echo "3. Testing LocalAgent...\n";
    $localAgent = new LocalAgent([
        'name' => 'Test Agent',
        'model' => 'llama3.2:latest'
    ]);
    
    $models = $localAgent->getAvailableModels();
    echo "Local models: " . implode(', ', $models) . "\n";
    
    $testResult = $localAgent->testConnection();
    echo "Local agent connection test: " . ($testResult['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    if (!$testResult['success']) {
        echo "Error: " . $testResult['error'] . "\n";
    }
    echo "\n";
    
    // Test smart routing
    echo "4. Testing smart routing...\n";
    $simpleMessage = "Hello";
    $complexMessage = "Analyze and generate a complex algorithm for optimizing database queries";
    
    echo "Simple message complexity: " . $aiManager->assessComplexity($simpleMessage) . "\n";
    echo "Complex message complexity: " . $aiManager->assessComplexity($complexMessage) . "\n";
    echo "\n";
    
    echo "All tests completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error during testing: " . $e->getMessage() . "\n";
}
?>