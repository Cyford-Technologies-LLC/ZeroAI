<?php

require_once __DIR__ . '/../src/autoload.php';

use ZeroAI\Admin\ChatAdmin;

$pageTitle = 'AI Chat - ZeroAI';
$currentPage = 'chat';

$chat = new ChatAdmin($pageTitle, $currentPage);
$chat->render();


