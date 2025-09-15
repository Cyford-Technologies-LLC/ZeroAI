<?php
// Real-time crew streaming endpoint
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$message = \ZeroAI\Core\InputValidator::sanitize($_GET['message'] ?? '');
$project = \ZeroAI\Core\InputValidator::sanitize($_GET['project'] ?? 'zeroai');

if (!$message) {
    echo "data: {\"error\": \"No message provided\"}\n\n";
    return;
}

// Start crew execution with streaming
$escapedMessage = escapeshellarg($message);
$escapedProject = escapeshellarg($project);

$pythonCmd = 'export HOME=/tmp && cd /app && /app/venv/bin/python -u run/internal/run_dev_ops.py ' . $escapedMessage . ' --project=' . $escapedProject . ' 2>&1';

// Open process with streaming
$process = popen($pythonCmd, 'r');

if ($process) {
    echo "data: {\"status\": \"started\", \"message\": \"Crew is working on your task...\"}\n\n";
    flush();
    
    while (!feof($process)) {
        $line = fgets($process);
        if ($line !== false) {
            $line = trim($line);
            if (!empty($line)) {
                // Send each line as streaming data
                $data = json_encode([
                    'type' => 'output',
                    'content' => $line,
                    'timestamp' => date('H:i:s')
                ]);
                echo "data: $data\n\n";
                flush();
            }
        }
        
        // Small delay to prevent overwhelming
        usleep(10000); // 0.01 second
    }
    
    pclose($process);
    echo "data: {\"status\": \"completed\", \"message\": \"Task completed!\"}\n\n";
} else {
    echo "data: {\"error\": \"Failed to start crew process\"}\n\n";
}

flush();
?>


