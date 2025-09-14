<?php
// Claude command logging for debugging
function logClaudeCommand($command, $result, $error = null) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'command' => $command,
        'result' => $result,
        'error' => $error,
        'php_user' => posix_getpwuid(posix_geteuid())['name']
    ];
    
    $logFile = '/app/logs/claude_commands.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

// Test all Claude file commands
echo "Testing Claude file commands...\n\n";

// Test @create
echo "1. Testing @create command:\n";
$testFile = '/app/knowledge/internal_crew/agent_learning/self/claude/debug_test.txt';
$testContent = 'Debug test file created at ' . date('Y-m-d H:i:s');

try {
    $dir = dirname($testFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
        echo "   Created directory: $dir\n";
    }
    
    $result = file_put_contents($testFile, $testContent);
    if ($result !== false) {
        echo "   ✅ @create SUCCESS: $testFile ($result bytes)\n";
        logClaudeCommand('@create', "File created: $testFile ($result bytes)");
    } else {
        $error = error_get_last();
        echo "   ❌ @create FAILED: " . ($error['message'] ?? 'Unknown error') . "\n";
        logClaudeCommand('@create', 'FAILED', $error['message'] ?? 'Unknown error');
    }
} catch (Exception $e) {
    echo "   ❌ @create EXCEPTION: " . $e->getMessage() . "\n";
    logClaudeCommand('@create', 'EXCEPTION', $e->getMessage());
}

// Test @edit
echo "\n2. Testing @edit command:\n";
$newContent = 'Edited content at ' . date('Y-m-d H:i:s');

try {
    if (file_exists($testFile)) {
        $result = file_put_contents($testFile, $newContent);
        if ($result !== false) {
            echo "   ✅ @edit SUCCESS: $testFile ($result bytes)\n";
            logClaudeCommand('@edit', "File edited: $testFile ($result bytes)");
        } else {
            $error = error_get_last();
            echo "   ❌ @edit FAILED: " . ($error['message'] ?? 'Unknown error') . "\n";
            logClaudeCommand('@edit', 'FAILED', $error['message'] ?? 'Unknown error');
        }
    } else {
        echo "   ❌ @edit FAILED: File not found\n";
        logClaudeCommand('@edit', 'FAILED', 'File not found');
    }
} catch (Exception $e) {
    echo "   ❌ @edit EXCEPTION: " . $e->getMessage() . "\n";
    logClaudeCommand('@edit', 'EXCEPTION', $e->getMessage());
}

// Test @append
echo "\n3. Testing @append command:\n";
$appendContent = "\nAppended content at " . date('Y-m-d H:i:s');

try {
    if (file_exists($testFile)) {
        $result = file_put_contents($testFile, $appendContent, FILE_APPEND);
        if ($result !== false) {
            echo "   ✅ @append SUCCESS: $testFile ($result bytes appended)\n";
            logClaudeCommand('@append', "Content appended: $testFile ($result bytes)");
        } else {
            $error = error_get_last();
            echo "   ❌ @append FAILED: " . ($error['message'] ?? 'Unknown error') . "\n";
            logClaudeCommand('@append', 'FAILED', $error['message'] ?? 'Unknown error');
        }
    } else {
        echo "   ❌ @append FAILED: File not found\n";
        logClaudeCommand('@append', 'FAILED', 'File not found');
    }
} catch (Exception $e) {
    echo "   ❌ @append EXCEPTION: " . $e->getMessage() . "\n";
    logClaudeCommand('@append', 'EXCEPTION', $e->getMessage());
}

// Test @file (read)
echo "\n4. Testing @file command:\n";

try {
    if (file_exists($testFile)) {
        $content = file_get_contents($testFile);
        if ($content !== false) {
            echo "   ✅ @file SUCCESS: Read " . strlen($content) . " bytes\n";
            echo "   Content preview: " . substr($content, 0, 100) . "...\n";
            logClaudeCommand('@file', "File read: $testFile (" . strlen($content) . " bytes)");
        } else {
            $error = error_get_last();
            echo "   ❌ @file FAILED: " . ($error['message'] ?? 'Unknown error') . "\n";
            logClaudeCommand('@file', 'FAILED', $error['message'] ?? 'Unknown error');
        }
    } else {
        echo "   ❌ @file FAILED: File not found\n";
        logClaudeCommand('@file', 'FAILED', 'File not found');
    }
} catch (Exception $e) {
    echo "   ❌ @file EXCEPTION: " . $e->getMessage() . "\n";
    logClaudeCommand('@file', 'EXCEPTION', $e->getMessage());
}

// Test @mkdir
echo "\n5. Testing @mkdir command:\n";
$testDir = '/app/knowledge/internal_crew/agent_learning/self/claude/test_dir';

try {
    if (!is_dir($testDir)) {
        if (mkdir($testDir, 0777, true)) {
            echo "   ✅ @mkdir SUCCESS: $testDir\n";
            logClaudeCommand('@mkdir', "Directory created: $testDir");
        } else {
            $error = error_get_last();
            echo "   ❌ @mkdir FAILED: " . ($error['message'] ?? 'Unknown error') . "\n";
            logClaudeCommand('@mkdir', 'FAILED', $error['message'] ?? 'Unknown error');
        }
    } else {
        echo "   ⚠️ @mkdir: Directory already exists\n";
        logClaudeCommand('@mkdir', 'Directory already exists');
    }
} catch (Exception $e) {
    echo "   ❌ @mkdir EXCEPTION: " . $e->getMessage() . "\n";
    logClaudeCommand('@mkdir', 'EXCEPTION', $e->getMessage());
}

// Test @list
echo "\n6. Testing @list command:\n";
$listDir = '/app/knowledge/internal_crew/agent_learning/self/claude';

try {
    if (is_dir($listDir)) {
        $files = scandir($listDir);
        $filteredFiles = array_filter($files, function($f) { return $f !== '.' && $f !== '..'; });
        echo "   ✅ @list SUCCESS: Found " . count($filteredFiles) . " items\n";
        echo "   Items: " . implode(", ", $filteredFiles) . "\n";
        logClaudeCommand('@list', "Directory listed: $listDir (" . count($filteredFiles) . " items)");
    } else {
        echo "   ❌ @list FAILED: Directory not found\n";
        logClaudeCommand('@list', 'FAILED', 'Directory not found');
    }
} catch (Exception $e) {
    echo "   ❌ @list EXCEPTION: " . $e->getMessage() . "\n";
    logClaudeCommand('@list', 'EXCEPTION', $e->getMessage());
}

// Test @delete
echo "\n7. Testing @delete command:\n";

try {
    if (file_exists($testFile)) {
        if (unlink($testFile)) {
            echo "   ✅ @delete SUCCESS: $testFile\n";
            logClaudeCommand('@delete', "File deleted: $testFile");
        } else {
            $error = error_get_last();
            echo "   ❌ @delete FAILED: " . ($error['message'] ?? 'Unknown error') . "\n";
            logClaudeCommand('@delete', 'FAILED', $error['message'] ?? 'Unknown error');
        }
    } else {
        echo "   ❌ @delete FAILED: File not found\n";
        logClaudeCommand('@delete', 'FAILED', 'File not found');
    }
} catch (Exception $e) {
    echo "   ❌ @delete EXCEPTION: " . $e->getMessage() . "\n";
    logClaudeCommand('@delete', 'EXCEPTION', $e->getMessage());
}

echo "\n\nPermission Info:\n";
echo "PHP User: " . posix_getpwuid(posix_geteuid())['name'] . "\n";
echo "PHP UID: " . posix_geteuid() . "\n";
echo "PHP GID: " . posix_getegid() . "\n";

if (is_dir('/app/knowledge')) {
    echo "Knowledge dir owner: " . posix_getpwuid(fileowner('/app/knowledge'))['name'] . "\n";
    echo "Knowledge dir perms: " . substr(sprintf('%o', fileperms('/app/knowledge')), -4) . "\n";
}

echo "\nTest completed. Check /app/logs/claude_commands.log for detailed logs.\n";
?>