<?php
header('Content-Type: application/json');

$memoryId = $_GET['id'] ?? '';
$memoryFile = "/app/data/memory_$memoryId.json";

if (file_exists($memoryFile)) {
    $data = json_decode(file_get_contents($memoryFile), true);
    unlink($memoryFile); // Clean up after reading
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'error' => 'Memory data not found']);
}
?>