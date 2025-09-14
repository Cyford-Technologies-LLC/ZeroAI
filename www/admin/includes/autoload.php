<?php
// Include main bootstrap
require_once __DIR__ . '/../../bootstrap.php';

// Load core classes
require_once __DIR__ . '/../../src/Core/CacheManager.php';
require_once __DIR__ . '/../../src/Core/SessionManager.php';
require_once __DIR__ . '/../../src/Core/System.php';
require_once __DIR__ . '/../../src/Core/DatabaseManager.php';
require_once __DIR__ . '/../../src/Core/QueueManager.php';

// Initialize core systems
$cache = \ZeroAI\Core\CacheManager::getInstance();
$session = \ZeroAI\Core\SessionManager::getInstance();