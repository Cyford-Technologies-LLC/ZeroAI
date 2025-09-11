<?php
// Test @exec command processing
$message = "@exec zeroai_api-test bash -c \"echo 'test' > /app/tmp/test.txt\"";

echo "Testing message: $message\n";

// Test regex
if (preg_match('/\@exec\s+([^\s]+)\s+(.+)/', $message, $matches)) {
    echo "Regex matched!\n";
    echo "Container: " . $matches[1] . "\n";
    echo "Command: " . $matches[2] . "\n";
    
    $containerName = trim($matches[1]);
    $command = trim($matches[2]);
    
    echo "Executing: docker exec $containerName $command\n";
    $output = shell_exec("docker exec $containerName $command 2>&1");
    echo "Output: " . ($output ?: "No output") . "\n";
} else {
    echo "Regex did not match!\n";
}
?>