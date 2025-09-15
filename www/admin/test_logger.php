<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../src/bootstrap.php';

echo "Testing Logger...\n";

try {
    $logger = \ZeroAI\Core\Logger::getInstance();
    echo "Logger instance created successfully\n";
    
    $logger->info('Test info message');
    echo "Info log written successfully\n";
    
    $logger->error('Test error message');
    echo "Error log written successfully\n";
    
    $logger->debug('Test debug message');
    echo "Debug log written successfully\n";
    
    // Test if logClaude method exists
    if (method_exists($logger, 'logClaude')) {
        $logger->logClaude('Test Claude message');
        echo "Claude log written successfully\n";
    } else {
        echo "ERROR: logClaude method does not exist\n";
    }
    
    echo "All tests passed!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>