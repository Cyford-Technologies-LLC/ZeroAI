<?php
session_start();

require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/../src/Services/AuthService.php';

use ZeroAI\Services\AuthService;

$auth = new AuthService();
$auth->logout();

header('Location: /admin/login.php');
exit;
