<?php

require_once __DIR__ . '/../src/API/ChatAPI.php';

use ZeroAI\API\ChatAPI;

$chatAPI = new ChatAPI();
$chatAPI->handleRequest();