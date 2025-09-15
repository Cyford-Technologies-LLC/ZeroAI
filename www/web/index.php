<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

use ZeroAI\Core\{Tenant, Company, Project, DatabaseManager};

// Simple routing for CRM
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/web', '', $uri);

switch ($uri) {
    case '/':
    case '/dashboard':
        include 'dashboard.php';
        break;
    case '/project':
        include 'project_view.php';
        break;
    default:
        include 'dashboard.php';
        break;
}


?>