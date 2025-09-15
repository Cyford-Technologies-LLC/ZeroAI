<?php
$message = 'try again
exec zeroai_api-test bash -c "git branch"
try only 1 command ,   and reply  working or not';

echo "<h1>Exec Regex Test</h1>";
echo "<h2>Original Message:</h2>";
echo "<pre>" . htmlspecialchars($message) . "</pre>";

echo "<h2>Regex Test:</h2>";
if (preg_match('/\@exec\s+([^\s]+)\s+(.+)/s', $message, $matches)) {
    echo "MATCH FOUND:<br>";
    echo "Container: " . htmlspecialchars($matches[1]) . "<br>";
    echo "Command: " . htmlspecialchars($matches[2]) . "<br>";
} else {
    echo "NO MATCH FOUND<br>";
}

echo "<h2>All @ commands:</h2>";
if (preg_match_all('/\@(\w+)/', $message, $matches)) {
    foreach ($matches[1] as $cmd) {
        echo "Found: @$cmd<br>";
    }
} else {
    echo "No @ commands found";
}
?>

