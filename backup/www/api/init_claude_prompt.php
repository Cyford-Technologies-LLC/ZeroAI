<?php
require_once __DIR__ . '/sqlite_manager.php';

// Minimal system prompt to prevent timeouts
$systemPrompt = "You are Claude, integrated into ZeroAI. Help with code review and optimization.\n\n";
$systemPrompt .= "Commands: @file, @list, @create, @edit, @agents, @crews, @docker\n";
$systemPrompt .= "Be concise and helpful.";

// Save to separate default prompts table
$createTable = "CREATE TABLE IF NOT EXISTS default_prompts (id INTEGER PRIMARY KEY, prompt TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)";
SQLiteManager::executeSQL($createTable);

$insertPrompt = "INSERT OR REPLACE INTO default_prompts (id, prompt) VALUES (1, '" . SQLite3::escapeString($systemPrompt) . "')";
SQLiteManager::executeSQL($insertPrompt);

// Don't output JSON when included from other scripts
if (basename($_SERVER['PHP_SELF']) === 'init_claude_prompt.php') {
    echo json_encode(['success' => true, 'message' => 'Complete system prompt saved to SQLite']);
}
?>