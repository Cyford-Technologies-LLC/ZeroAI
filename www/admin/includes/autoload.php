<?php
// Include main bootstrap
require_once __DIR__ . '/../../bootstrap.php';

// Initialize core systems
$cache = \ZeroAI\Core\CacheManager::getInstance();
$session = \ZeroAI\Core\SessionManager::getInstance();