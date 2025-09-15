<?php

require_once __DIR__ . '/../src/autoload.php';

use ZeroAI\Admin\ClaudeSettingsAdmin;

$pageTitle = 'Claude AI Settings - ZeroAI';
$currentPage = 'claude_settings';

$claudeSettings = new ClaudeSettingsAdmin($pageTitle, $currentPage);
$claudeSettings->render();