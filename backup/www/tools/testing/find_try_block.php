<?php
$content = file_get_contents('/app/www/api/claude_chat.php');
$lines = explode("\n", $content);

echo "Looking for 'try {' blocks:\n";
foreach ($lines as $i => $line) {
    if (strpos($line, 'try {') !== false) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}

echo "\nLooking for lines before catch:\n";
for ($i = 680; $i < 690 && $i < count($lines); $i++) {
    echo ($i + 1) . ": " . $lines[$i] . "\n";
}
?>