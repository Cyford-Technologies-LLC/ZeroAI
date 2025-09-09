<?php
echo "=== Web Debug Info ===\n";
echo "Current directory: " . getcwd() . "\n";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] ?? 'Not set' . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] ?? 'Not set' . "\n";
echo "Script name: " . $_SERVER['SCRIPT_NAME'] ?? 'Not set' . "\n";

echo "\n=== File Check ===\n";
$files = [
    '/app/www/index.php',
    '/app/www/admin/claude_chat.php',
    '/etc/nginx/sites-available/zeroai',
    '/etc/nginx/nginx.conf'
];

foreach ($files as $file) {
    echo "$file: " . (file_exists($file) ? 'EXISTS' : 'MISSING') . "\n";
}

echo "\n=== Nginx Config ===\n";
if (file_exists('/etc/nginx/sites-available/zeroai')) {
    echo file_get_contents('/etc/nginx/sites-available/zeroai');
}

echo "\n=== Process Check ===\n";
echo shell_exec('ps aux | grep nginx');
?>