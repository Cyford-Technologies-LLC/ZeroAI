<?php
// Simple test to verify Claude's file operations work
echo "Testing file write operations...\n";

$testFile = '/tmp/claude_test.txt';
$content = "Hello from Claude test";

if (file_put_contents($testFile, $content) !== false) {
    echo "✓ File write successful\n";
    if (file_exists($testFile)) {
        echo "✓ File exists after write\n";
        echo "Content: " . file_get_contents($testFile) . "\n";
        unlink($testFile);
        echo "✓ File deleted\n";
    }
} else {
    echo "✗ File write failed\n";
}
?>