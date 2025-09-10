<?php
// Debug Claude's exact message format
$claudeMessage = "@create knowledge/internal_crew/agent_learning/self/claude/test.txt
```
test file created
```";

echo "Testing Claude's exact message format:\n";
echo "Message: " . json_encode($claudeMessage) . "\n\n";

// Test the regex from claude_chat.php
if (preg_match('/\@create\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $claudeMessage, $matches)) {
    echo "✅ REGEX MATCHED\n";
    echo "File: " . $matches[1] . "\n";
    echo "Content: " . (isset($matches[2]) ? trim($matches[2]) : 'NO CONTENT') . "\n";
} else {
    echo "❌ REGEX FAILED\n";
}

// Test different formats
$formats = [
    "@create test.txt ```content```",
    "@create test.txt\n```\ncontent\n```",
    "@create knowledge/test.txt ```content```"
];

foreach ($formats as $format) {
    echo "\nTesting: " . json_encode($format) . "\n";
    if (preg_match('/\@create\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $format, $matches)) {
        echo "  ✅ MATCHED - File: {$matches[1]}, Content: " . (isset($matches[2]) ? trim($matches[2]) : 'none') . "\n";
    } else {
        echo "  ❌ NO MATCH\n";
    }
}
?>