<?php
// Test Claude's file writing capabilities
echo "Testing Claude file operations...\n";

// Test directory creation
$testDir = '/app/knowledge/internal_crew/agent_learning/self/claude/test';
if (!is_dir($testDir)) {
    if (mkdir($testDir, 0777, true)) {
        echo "✅ Directory created: $testDir\n";
    } else {
        echo "❌ Failed to create directory: $testDir\n";
    }
} else {
    echo "⚠️ Directory already exists: $testDir\n";
}

// Test file creation
$testFile = $testDir . '/test_file.txt';
$content = "Claude test file created at " . date('Y-m-d H:i:s');
if (file_put_contents($testFile, $content) !== false) {
    echo "✅ File created: $testFile\n";
} else {
    echo "❌ Failed to create file: $testFile\n";
    $error = error_get_last();
    echo "Error: " . ($error['message'] ?? 'Unknown') . "\n";
}

// Test file reading
if (file_exists($testFile)) {
    $readContent = file_get_contents($testFile);
    echo "✅ File read successfully: " . substr($readContent, 0, 50) . "\n";
} else {
    echo "❌ Cannot read file: $testFile\n";
}

// Check permissions
echo "\nPermission check:\n";
echo "PHP user: " . posix_getpwuid(posix_geteuid())['name'] . "\n";
if (is_dir($testDir)) {
    echo "Directory owner: " . posix_getpwuid(fileowner($testDir))['name'] . "\n";
    echo "Directory perms: " . substr(sprintf('%o', fileperms($testDir)), -4) . "\n";
}
if (file_exists($testFile)) {
    echo "File owner: " . posix_getpwuid(fileowner($testFile))['name'] . "\n";
    echo "File perms: " . substr(sprintf('%o', fileperms($testFile)), -4) . "\n";
}

echo "\nTest completed.\n";
?>