<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Claude Chat Debug ===\n";

// Check if .env exists
if (file_exists('/app/.env')) {
    echo "✅ .env file exists\n";
    $envContent = file_get_contents('/app/.env');
    if (strpos($envContent, 'ANTHROPIC_API_KEY') !== false) {
        echo "✅ ANTHROPIC_API_KEY found in .env\n";
    } else {
        echo "❌ ANTHROPIC_API_KEY missing from .env\n";
    }
} else {
    echo "❌ .env file missing\n";
}

// Check required files
$requiredFiles = [
    '/app/www/api/claude_integration.php',
    '/app/www/api/crew_context.php',
    '/app/www/api/agent_db.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists\n";
    } else {
        echo "❌ $file missing\n";
    }
}

// Test basic JSON response
echo "\n=== Testing JSON Response ===\n";
header('Content-Type: application/json');

try {
    // Simulate the claude_chat.php logic
    $testData = [
        'success' => true,
        'message' => 'Test response',
        'debug' => 'This is a test'
    ];
    
    echo json_encode($testData);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

