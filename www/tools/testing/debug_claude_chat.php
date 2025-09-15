<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing claude_chat.php directly...\n";

// Simulate the POST request
$_POST = [];
$input = json_encode([
    'message' => 'test',
    'model' => 'claude-sonnet-4-20250514',
    'autonomous' => false,
    'history' => []
]);

// Capture output
ob_start();
$_SERVER['REQUEST_METHOD'] = 'POST';
file_put_contents('php://input', $input);

try {
    include '/app/www/api/claude_chat.php';
    $output = ob_get_contents();
} catch (Exception $e) {
    $output = "ERROR: " . $e->getMessage();
} catch (Error $e) {
    $output = "FATAL ERROR: " . $e->getMessage();
}
ob_end_clean();

echo "Output length: " . strlen($output) . "\n";
echo "Output: " . substr($output, 0, 500) . "\n";

if (empty($output)) {
    echo "❌ Empty output - check PHP errors\n";
} else {
    echo "✅ Got output\n";
}
?>

