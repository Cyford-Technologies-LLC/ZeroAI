<?php
/**
 * Migration script to transition from standalone API files to object-oriented structure
 */

echo "ZeroAI OOP Migration Script\n";
echo "===========================\n\n";

$migrations = [
    'claude_chat.php' => 'Use src/API/ChatAPI.php with Claude provider',
    'claude_integration.php' => 'Replaced by src/AI/Claude.php',
    'claude_autonomous.php' => 'Integrated into ChatAPI autonomous mode',
    'claude_tools.php' => 'Integrated into Claude class methods',
    'claude_debug.php' => 'Use AIManager testProvider method',
    'get_claude_models.php' => 'Use Claude->getAvailableModels()',
    'test_claude_endpoint.php' => 'Use Claude->testConnection()'
];

echo "Migration Mapping:\n";
foreach ($migrations as $oldFile => $newStructure) {
    echo "- {$oldFile} -> {$newStructure}\n";
}

echo "\nNew Object Structure:\n";
echo "- CloudAI (abstract base class)\n";
echo "  - Claude (Anthropic API)\n";
echo "  - OpenAI (OpenAI API)\n";
echo "- LocalAgent (Ollama integration)\n";
echo "- AIManager (orchestrates all providers)\n";
echo "- ChatAPI (unified API endpoint)\n";
echo "- AIController (web controller)\n";

echo "\nUsage Examples:\n";
echo "1. New API endpoint: /api/chat_v2.php\n";
echo "2. Test providers: POST to ChatAPI with testProvider method\n";
echo "3. Smart routing: POST with {'provider': 'smart', 'message': 'your message'}\n";

echo "\nNext Steps:\n";
echo "1. Update frontend to use new API endpoints\n";
echo "2. Test all providers work correctly\n";
echo "3. Gradually migrate admin pages to use AIController\n";
echo "4. Archive old API files once migration is complete\n";

echo "\nMigration complete! New OOP structure is ready.\n";
?>