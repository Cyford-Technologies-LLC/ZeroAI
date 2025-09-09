<?php
// Test web server write permissions
$test_dirs = [
    '/app/',
    '/app/www/',
    '/app/www/admin/',
    '/app/src/',
    '/app/knowledge/',
    '/app/tools/',
    '/tmp/',
    '/var/www/',
    getcwd(),
    dirname(__FILE__)
];

echo "Testing write permissions...\n\n";

foreach ($test_dirs as $dir) {
    $test_file = rtrim($dir, '/') . '/test_write_' . time() . '.txt';
    
    echo "Testing: $dir\n";
    echo "File: $test_file\n";
    
    // Check if directory exists
    if (!is_dir($dir)) {
        echo "❌ Directory does not exist\n\n";
        continue;
    }
    
    // Check if directory is writable
    if (!is_writable($dir)) {
        echo "❌ Directory not writable\n\n";
        continue;
    }
    
    // Try to create file
    $result = file_put_contents($test_file, "Test content " . date('Y-m-d H:i:s'));
    
    if ($result !== false) {
        echo "✅ Write successful ($result bytes)\n";
        
        // Clean up
        if (file_exists($test_file)) {
            unlink($test_file);
            echo "✅ Cleanup successful\n";
        }
    } else {
        echo "❌ Write failed\n";
    }
    
    echo "\n";
}

// Test current working directory info
echo "Current working directory: " . getcwd() . "\n";
echo "Script directory: " . dirname(__FILE__) . "\n";
echo "Web server user: " . get_current_user() . "\n";
echo "Process owner: " . posix_getpwuid(posix_geteuid())['name'] ?? 'unknown' . "\n";
?>