<?php
$file = '/app/www/api/claude_chat.php';
$lines = file($file);

echo "Lines 680-690:\n";
for ($i = 679; $i < 690 && $i < count($lines); $i++) {
    echo ($i + 1) . ": " . $lines[$i];
}

// Check for missing braces
$content = file_get_contents($file);
$openBraces = substr_count($content, '{');
$closeBraces = substr_count($content, '}');
echo "\nBrace count: { = $openBraces, } = $closeBraces\n";

if ($openBraces != $closeBraces) {
    echo "❌ Mismatched braces!\n";
} else {
    echo "✅ Braces match\n";
}
?>

