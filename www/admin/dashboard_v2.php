<?php

require_once __DIR__ . '/../src/autoload.php';

use ZeroAI\Admin\DashboardAdmin;

$pageTitle = 'Admin Dashboard - ZeroAI';
$currentPage = 'dashboard';

$dashboard = new DashboardAdmin($pageTitle, $currentPage);
$dashboard->render();
