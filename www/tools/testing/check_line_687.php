<?php
$lines = file('www/api/claude_chat.php');
echo "Lines 680-690:\n";
for ($i = 679; $i < 690 && $i < count($lines); $i++) {
    echo ($i + 1) . ": " . $lines[$i];
}
?>

