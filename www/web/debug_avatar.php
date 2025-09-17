<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Avatar Debug</h3>";

// Test autoloader
echo "<p>1. Testing autoloader...</p>";
if (file_exists('../../src/autoload.php')) {
    echo "✅ Autoloader file exists<br>";
    require_once '../../src/autoload.php';
    echo "✅ Autoloader loaded<br>";
} else {
    echo "❌ Autoloader file missing<br>";
}

// Test class loading
echo "<p>2. Testing class loading...</p>";
try {
    if (class_exists('ZeroAI\Providers\AI\Local\AvatarProvider')) {
        echo "✅ AvatarProvider class found<br>";
        $provider = new ZeroAI\Providers\AI\Local\AvatarProvider();
        echo "✅ AvatarProvider instantiated<br>";
    } else {
        echo "❌ AvatarProvider class not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test avatar service
echo "<p>3. Testing avatar service...</p>";
try {
    if (isset($provider)) {
        $result = $provider->testConnection();
        echo "Connection result: " . json_encode($result) . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Connection error: " . $e->getMessage() . "<br>";
}
?>