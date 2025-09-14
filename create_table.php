<?php
// Create missing claude_token_usage table
$db = new SQLite3('/app/data/zeroai.db');

$sql = "CREATE TABLE IF NOT EXISTS claude_token_usage (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    model TEXT NOT NULL,
    input_tokens INTEGER DEFAULT 0,
    output_tokens INTEGER DEFAULT 0,
    total_tokens INTEGER DEFAULT 0,
    cost REAL DEFAULT 0.0,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)";

$result = $db->exec($sql);

if ($result) {
    echo "Table created successfully\n";
} else {
    echo "Error: " . $db->lastErrorMsg() . "\n";
}

$db->close();
?>