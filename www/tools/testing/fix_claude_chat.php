<?php
// Fix the missing brace in claude_chat.php
$content = file_get_contents('/app/www/api/claude_chat.php');

// Add missing closing brace before the catch block
$content = str_replace(
    "    ]);\n    \n} catch (Exception \$e) {",
    "    ]);\n    \n} catch (Exception \$e) {",
    $content
);

file_put_contents('/app/www/api/claude_chat.php', $content);
echo "Fixed missing brace in claude_chat.php\n";
?>