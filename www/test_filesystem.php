<?php
header('Content-Type: text/plain');

echo "=== ZeroAI Filesystem Test ===\n\n";

// Test parameters
$testFile = 'knowledge/internal_crew/agent_learning/self/claude/test_file.txt';
$testDir = 'knowledge/internal_crew/agent_learning/self/claude';
$testContent = "Test file content\nLine 2\nLine 3";

echo "Test file: $testFile\n";
echo "Test dir: $testDir\n";
echo "Content length: " . strlen($testContent) . " bytes\n\n";

// Clean up path (same logic as Claude)
function cleanPath($filePath) {
    $cleanPath = ltrim($filePath, '/');
    if (strpos($cleanPath, 'app/') === 0) {
        $cleanPath = substr($cleanPath, 4);
    }
    return $cleanPath;
}

$cleanPath = cleanPath($testFile);
$fullPath = '/app/' . $cleanPath;
$dir = dirname($fullPath);

echo "=== PATH PROCESSING ===\n";
echo "Original: $testFile\n";
echo "Clean path: $cleanPath\n";
echo "Full path: $fullPath\n";
echo "Directory: $dir\n\n";

echo "=== DIRECTORY CHECKS ===\n";
echo "Directory exists: " . (is_dir($dir) ? 'YES' : 'NO') . "\n";
echo "Directory writable: " . (is_writable($dir) ? 'YES' : 'NO') . "\n";
echo "Parent exists: " . (is_dir(dirname($dir)) ? 'YES' : 'NO') . "\n";
echo "Parent writable: " . (is_writable(dirname($dir)) ? 'YES' : 'NO') . "\n\n";

echo "=== DIRECTORY CREATION ===\n";
if (!is_dir($dir)) {
    echo "Creating directory: $dir\n";
    if (mkdir($dir, 0755, true)) {
        echo "✅ Directory created successfully\n";
    } else {
        $error = error_get_last();
        echo "❌ Directory creation failed\n";
        echo "Error: " . ($error['message'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "Directory already exists\n";
}

echo "Directory exists after creation: " . (is_dir($dir) ? 'YES' : 'NO') . "\n";
echo "Directory writable after creation: " . (is_writable($dir) ? 'YES' : 'NO') . "\n\n";

echo "=== FILE CREATION ===\n";
echo "Creating file: $fullPath\n";
$result = file_put_contents($fullPath, $testContent);

if ($result !== false) {
    echo "✅ file_put_contents returned: $result bytes\n";
} else {
    echo "❌ file_put_contents failed\n";
    $error = error_get_last();
    echo "Error: " . ($error['message'] ?? 'Unknown error') . "\n";
}

echo "File exists: " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";
echo "File readable: " . (is_readable($fullPath) ? 'YES' : 'NO') . "\n";
echo "File size: " . (file_exists($fullPath) ? filesize($fullPath) : 'N/A') . " bytes\n\n";

echo "=== SYSTEM INFO ===\n";
echo "PHP user: " . posix_getpwuid(posix_geteuid())['name'] . "\n";
echo "PHP UID: " . posix_geteuid() . "\n";
echo "PHP GID: " . posix_getegid() . "\n";
echo "Working directory: " . getcwd() . "\n";

if (file_exists($fullPath)) {
    $stat = stat($fullPath);
    echo "File owner UID: " . $stat['uid'] . "\n";
    echo "File owner name: " . posix_getpwuid($stat['uid'])['name'] . "\n";
    echo "File permissions: " . substr(sprintf('%o', fileperms($fullPath)), -4) . "\n";
}

echo "\n=== DIRECTORY LISTING ===\n";
if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "- $file\n";
        }
    }
} else {
    echo "Directory does not exist\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>