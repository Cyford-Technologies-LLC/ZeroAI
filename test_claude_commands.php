<?php
// Test Claude commands locally to see what's happening
echo "Testing Claude file commands locally...\n\n";

// Simulate the message processing from claude_chat.php
$testMessage = "@create knowledge/internal_crew/agent_learning/self/claude/test.txt ```Hello World```";

echo "Original message: $testMessage\n\n";

// Test @create regex
if (preg_match('/\@create\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $testMessage, $matches)) {
    echo "✅ @create regex MATCHED\n";
    echo "File path: " . $matches[1] . "\n";
    echo "Content: " . (isset($matches[2]) ? $matches[2] : "NO CONTENT") . "\n";
    
    $filePath = trim($matches[1]);
    $fileContent = isset($matches[2]) ? trim($matches[2]) : "";
    
    // Clean up path
    $cleanPath = ltrim($filePath, '/');
    if (strpos($cleanPath, 'app/') === 0) {
        $cleanPath = substr($cleanPath, 4);
    }
    
    if (empty($cleanPath) || $cleanPath === '/') {
        $cleanPath = 'claude_file.txt';
    }
    
    echo "Clean path: $cleanPath\n";
    echo "Content to write: '$fileContent'\n";
    
    // Try to create the file
    $fullPath = 'c:/Users/allen/PycharmProjects/ZeroAI/' . $cleanPath;
    $dir = dirname($fullPath);
    
    echo "Full path: $fullPath\n";
    echo "Directory: $dir\n";
    
    if (!is_dir($dir)) {
        echo "Creating directory...\n";
        if (mkdir($dir, 0755, true)) {
            echo "✅ Directory created\n";
        } else {
            echo "❌ Directory creation failed\n";
        }
    } else {
        echo "Directory already exists\n";
    }
    
    $result = file_put_contents($fullPath, $fileContent);
    if ($result !== false) {
        echo "✅ File created successfully: $result bytes\n";
    } else {
        echo "❌ File creation failed\n";
        $error = error_get_last();
        echo "Error: " . ($error['message'] ?? 'Unknown') . "\n";
    }
    
} else {
    echo "❌ @create regex DID NOT MATCH\n";
}

echo "\n" . str_repeat("-", 50) . "\n";

// Test different message formats
$testMessages = [
    "@create test1.txt ```content1```",
    "@create test2.txt content2",
    "@create knowledge/test3.txt ```content3```",
    "@file knowledge/internal_crew/agent_learning/self/claude/README.md",
    "@list knowledge/internal_crew/agent_learning/self/claude"
];

foreach ($testMessages as $msg) {
    echo "\nTesting: $msg\n";
    
    if (preg_match('/\@create\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $msg, $matches)) {
        echo "  ✅ @create matched - File: {$matches[1]}, Content: " . (isset($matches[2]) ? $matches[2] : 'none') . "\n";
    } elseif (preg_match('/\@file\s+(.+)/', $msg, $matches)) {
        echo "  ✅ @file matched - Path: {$matches[1]}\n";
    } elseif (preg_match('/\@list\s+(.+)/', $msg, $matches)) {
        echo "  ✅ @list matched - Path: {$matches[1]}\n";
    } else {
        echo "  ❌ No command matched\n";
    }
}

echo "\nTest completed.\n";
?>