<?php
header('Content-Type: text/plain');
echo "=== CLAUDE DEBUG LOG ===\n\n";
if (file_exists('/app/logs/claude_debug.log')) {
    $lines = file('/app/logs/claude_debug.log');
    $recent = array_slice($lines, -20);
    foreach ($recent as $line) {
        echo $line;
    }
} else {
    echo "No debug log found\n";
}
?>