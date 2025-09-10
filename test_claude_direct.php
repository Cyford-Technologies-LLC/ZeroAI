<?php
// Test Claude directly to see if she uses @create commands
$testMessage = "Please create a test file called test.txt with the content 'Hello World'";

// Simulate what happens in claude_chat.php
echo "User message: $testMessage\n\n";

// Check if Claude's response contains @create
$mockClaudeResponse = "I'll create that file for you.\n\n@create test.txt ```Hello World```\n\nThe file has been created successfully.";

echo "Mock Claude response:\n$mockClaudeResponse\n\n";

// Test if our regex catches it
if (preg_match('/\@create\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $mockClaudeResponse, $matches)) {
    echo "✅ @create command detected!\n";
    echo "File: " . $matches[1] . "\n";
    echo "Content: " . (isset($matches[2]) ? $matches[2] : 'none') . "\n";
    
    // Try to create the file
    $filePath = $matches[1];
    $content = isset($matches[2]) ? $matches[2] : '';
    
    $result = file_put_contents($filePath, $content);
    if ($result !== false) {
        echo "✅ File created: $result bytes\n";
    } else {
        echo "❌ File creation failed\n";
    }
} else {
    echo "❌ No @create command found in response\n";
}

echo "\nThe issue is: Claude needs to include @create commands in her actual responses.\n";
?>