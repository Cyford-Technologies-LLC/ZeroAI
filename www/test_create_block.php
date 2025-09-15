<?php
// Test the exact @create processing code from claude_chat.php
$message = "@create knowledge/internal_crew/agent_learning/self/claude/test.txt\n```\ntest file created\n```";

echo "Testing @create processing block...\n";

// Copy exact code from claude_chat.php
if (preg_match('/\@create\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $message, $matches)) {
    echo "✅ Regex matched\n";
    
    $filePath = trim($matches[1]);
    $fileContent = isset($matches[2]) ? trim($matches[2]) : "";
    
    echo "File path: $filePath\n";
    echo "Content: $fileContent\n";
    
    // Clean up path - remove leading /app/ if present to avoid double path
    $cleanPath = ltrim($filePath, '/');
    if (strpos($cleanPath, 'app/') === 0) {
        $cleanPath = substr($cleanPath, 4);
    }
    
    echo "Clean path: $cleanPath\n";
    
    // Ensure we have a valid filename
    if (empty($cleanPath) || $cleanPath === '/') {
        $cleanPath = 'claude_file.txt';
    }
    
    $fullPath = '/app/' . $cleanPath;
    echo "Full path: $fullPath\n";
    
    $dir = dirname($fullPath);
    echo "Directory: $dir\n";
    
    if (!is_dir($dir)) {
        echo "Creating directory...\n";
        mkdir($dir, 0755, true);
    }
    
    echo "Attempting file_put_contents...\n";
    $result = file_put_contents($fullPath, $fileContent);
    if ($result !== false) {
        echo "✅ File created: $result bytes\n";
        
        // Verify file exists
        if (file_exists($fullPath)) {
            echo "✅ File verified exists: $fullPath\n";
            $readContent = file_get_contents($fullPath);
            echo "✅ File content verified: " . strlen($readContent) . " bytes\n";
            echo "Content: '$readContent'\n";
        } else {
            echo "❌ File does NOT exist after creation!\n";
        }
        
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @create SUCCESS: $cleanPath ($result bytes)\n", FILE_APPEND);
    } else {
        $error = error_get_last();
        echo "❌ File creation failed: " . ($error['message'] ?? 'Unknown error') . "\n";
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @create FAILED: $cleanPath - " . ($error['message'] ?? 'Unknown error') . "\n", FILE_APPEND);
    }
} else {
    echo "❌ Regex did not match\n";
}
?>

