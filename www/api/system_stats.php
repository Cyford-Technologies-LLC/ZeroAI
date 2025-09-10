<?php
header('Content-Type: application/json');

// Get CPU usage
$cpu = sys_getloadavg()[0] * 100;

// Get memory usage
$memInfo = file_get_contents('/proc/meminfo');
preg_match('/MemTotal:\s+(\d+)/', $memInfo, $memTotal);
preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $memAvailable);
$memUsed = (($memTotal[1] - $memAvailable[1]) / $memTotal[1]) * 100;

// Get disk I/O
$diskStats = file_get_contents('/proc/diskstats');
$lines = explode("\n", $diskStats);
$totalReads = 0;
$totalWrites = 0;
foreach ($lines as $line) {
    $parts = preg_split('/\s+/', trim($line));
    if (count($parts) >= 14 && strpos($parts[2], 'loop') === false) {
        $totalReads += $parts[5];
        $totalWrites += $parts[9];
    }
}

echo json_encode([
    'cpu' => round($cpu, 1),
    'memory' => round($memUsed, 1),
    'disk_reads' => $totalReads,
    'disk_writes' => $totalWrites,
    'timestamp' => time()
]);
?>