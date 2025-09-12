<?php
require_once __DIR__ . '/sqlite_manager.php';

// Minimal system prompt to prevent timeouts
$systemPrompt = "You are Claude, integrated into ZeroAI. Help with code review and optimization.\n\n";
$systemPrompt .= "Commands: @file, @list, @create, @edit, @agents, @crews, @docker\n";
$systemPrompt .= "Be concise and helpful.";

// Save to SQLite
$createTable = "CREATE TABLE IF NOT EXISTS system_prompts (id INTEGER PRIMARY KEY, prompt TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)";
SQLiteManager::executeSQL($createTable);

$insertPrompt = "INSERT OR REPLACE INTO system_prompts (id, prompt) VALUES (1, '" . SQLite3::escapeString($systemPrompt) . "')";
SQLiteManager::executeSQL($insertPrompt);

echo json_encode(['success' => true, 'message' => 'Complete system prompt saved to SQLite']);
?>