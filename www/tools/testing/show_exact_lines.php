<?php
$lines = file('/app/www/api/claude_chat.php');
echo "Exact content around line 685-687:\n";
for ($i = 683; $i < 690; $i++) {
    if (isset($lines[$i])) {
        echo "Line " . ($i + 1) . ": '" . rtrim($lines[$i]) . "'\n";
    }
}
?>

