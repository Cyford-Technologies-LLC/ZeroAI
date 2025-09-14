#!/usr/bin/env php
<?php
// Queue processor script - runs every minute via cron

require_once __DIR__ . '/../www/bootstrap.php';
require_once __DIR__ . '/../www/src/Core/QueueManager.php';
require_once __DIR__ . '/../www/src/Core/QueueProcessor.php';

$processor = new \ZeroAI\Core\QueueProcessor();
$queue = \ZeroAI\Core\QueueManager::getInstance();

echo "[" . date('Y-m-d H:i:s') . "] Starting queue processing...\n";

$queueSize = $queue->size();
echo "Queue size: $queueSize jobs\n";

if ($queueSize > 0) {
    $processed = $processor->process();
    echo "Processed: $processed jobs\n";
    
    $remaining = $queue->size();
    echo "Remaining: $remaining jobs\n";
} else {
    echo "No jobs to process\n";
}

echo "Queue processing completed\n";