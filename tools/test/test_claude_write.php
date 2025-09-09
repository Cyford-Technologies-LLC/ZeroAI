<?php
echo "Testing PHP file operations...\n";

$testFile = '/app/claude_test.txt';
$content = "Hello from Claude test";

if (file_put_contents($testFile, $content) !== false) {
    echo "✓ File write successful\n";
    if (file_exists($testFile)) {
        echo "✓ File exists\n";
        unlink($testFile);
    }
} else {
    echo "✗ File write failed\n";
}
?>