<?php
// Authentication check for admin pages
session_start();

require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/../src/Services/AuthService.php';

use ZeroAI\Services\AuthService;

$auth = new AuthService();
$auth->requireAuth();