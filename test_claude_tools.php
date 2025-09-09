<?php
// Test Claude's file operation regex patterns

$testMessages = [
    '@file src/config.py',
    '@create test.py ```print("hello")```',
    '@edit test.py ```print("world")```',
    '@append test.py ```print("end")```',
    '@delete test.py',
    '@list src/',
    '@search config'
];

echo "Testing Claude regex patterns:\n\n";

foreach ($testMessages as $message) {
    echo "Testing: $message\n";
    
    // Test @file pattern
    if (preg_match('/\\@file\\s+(.+)/', $message, $matches)) {
        echo "  @file matched: " . trim($matches[1]) . "\n";
    }
    
    // Test @create pattern
    if (preg_match('/\\@create\\s+(.+?)\\s+```([\\s\\S]*?)```/', $message, $matches)) {
        echo "  @create matched: file=" . trim($matches[1]) . ", content=" . trim($matches[2]) . "\n";
    }
    
    // Test @edit pattern
    if (preg_match('/\\@edit\\s+(.+?)\\s+```([\\s\\S]*?)```/', $message, $matches)) {
        echo "  @edit matched: file=" . trim($matches[1]) . ", content=" . trim($matches[2]) . "\n";
    }
    
    // Test @append pattern
    if (preg_match('/\\@append\\s+(.+?)\\s+```([\\s\\S]*?)```/', $message, $matches)) {
        echo "  @append matched: file=" . trim($matches[1]) . ", content=" . trim($matches[2]) . "\n";
    }
    
    // Test @delete pattern
    if (preg_match('/\\@delete\\s+(.+)/', $message, $matches)) {
        echo "  @delete matched: " . trim($matches[1]) . "\n";
    }
    
    // Test @list pattern
    if (preg_match('/\\@list\\s+(.+)/', $message, $matches)) {
        echo "  @list matched: " . trim($matches[1]) . "\n";
    }
    
    // Test @search pattern
    if (preg_match('/\\@search\\s+(.+)/', $message, $matches)) {
        echo "  @search matched: " . trim($matches[1]) . "\n";
    }
    
    echo "\n";
}

// Test actual file operations
echo "Testing actual file operations:\n\n";

// Test file creation
$testFile = '/tmp/claude_test.txt';
$testContent = "Hello from Claude test";

if (file_put_contents($testFile, $testContent) !== false) {
    echo "✓ File creation works\n";
    
    // Test file reading
    if (file_exists($testFile)) {
        $content = file_get_contents($testFile);
        echo "✓ File reading works: $content\n";
        
        // Test file deletion
        if (unlink($testFile)) {
            echo "✓ File deletion works\n";
        } else {
            echo "✗ File deletion failed\n";
        }
    } else {
        echo "✗ File doesn't exist after creation\n";
    }
} else {
    echo "✗ File creation failed\n";
}

// Test directory operations
echo "\nTesting directory operations:\n";
if (is_dir('/app')) {
    echo "✓ /app directory exists\n";
    if (is_dir('/app/src')) {
        echo "✓ /app/src directory exists\n";
    } else {
        echo "✗ /app/src directory missing\n";
    }
} else {
    echo "✗ /app directory missing\n";
}
?>