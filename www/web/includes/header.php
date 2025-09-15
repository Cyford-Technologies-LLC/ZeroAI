<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?? 'ZeroAI Portal' ?></title>
    <link rel="stylesheet" href="/www/assets/css/frontend.css">
    <link rel="icon" type="image/x-icon" href="/www/assets/img/favicon.ico">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .header { background: #28a745; color: white; padding: 1rem 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .nav { display: flex; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; transition: background 0.3s; }
        .nav a:hover { background: rgba(255,255,255,0.1); }
        .nav a.active { background: rgba(255,255,255,0.2); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .main-content { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        button { padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn-primary { background: #007bff; }
        .btn-danger { background: #dc3545; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">ZeroAI Portal</div>
            <nav class="nav">
                <a href="/web/frontend" <?= ($currentPage ?? '') === 'frontend' ? 'class="active"' : '' ?>>Dashboard</a>
                <a href="/web/projects" <?= ($currentPage ?? '') === 'projects' ? 'class="active"' : '' ?>>Projects</a>
                <a href="/web/tasks" <?= ($currentPage ?? '') === 'tasks' ? 'class="active"' : '' ?>>Tasks</a>
            </nav>
            <div class="user-info">
                <span>Welcome, <?= $_SESSION['web_user'] ?? 'User' ?>!</span>
                <a href="/web/logout" style="background: #dc3545; padding: 6px 12px; border-radius: 4px;">Logout</a>
            </div>
        </div>
    </div>
    <div class="main-content">