<?php
$pageTitle = $pageTitle ?? 'ZeroAI CRM';
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; display: flex; flex-direction: column; height: 100vh; }
        .header { background: #007bff; color: white; padding: 1rem 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .ai-workshop { position: absolute; left: 50%; transform: translateX(-50%); }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .nav { display: flex; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; transition: background 0.3s; }
        .nav a:hover { background: rgba(255,255,255,0.1); }
        .nav a.active { background: rgba(255,255,255,0.2); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .header-btn { padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-admin { background: linear-gradient(135deg, #6c757d, #495057); color: white; }
        .btn-admin:hover { background: linear-gradient(135deg, #5a6268, #3d4142); transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .btn-logout { background: linear-gradient(135deg, #dc3545, #e74c3c); color: white; }
        .btn-logout:hover { background: linear-gradient(135deg, #c82333, #dc2626); transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .content-wrapper { display: flex; flex: 1; }
        .sidebar { width: 250px; background: #343a40; color: white; padding: 20px 0; overflow-y: auto; }
        .sidebar-group { margin-bottom: 20px; }
        .sidebar-group h3 { color: #adb5bd; font-size: 12px; text-transform: uppercase; margin: 0 20px 10px; font-weight: bold; }
        .sidebar a { display: block; color: #dee2e6; text-decoration: none; padding: 10px 20px; transition: background 0.3s; }
        .sidebar a:hover { background: #495057; }
        .sidebar a.active { background: #007bff; color: white; }
        .main-content { flex: 1; padding: 20px; overflow-y: auto; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .btn { display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border: none; border-radius: 4px; cursor: pointer; margin: 2px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .alert { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 48%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; display: inline-block; margin-right: 2%; }
        .form-group { display: flex; flex-wrap: wrap; }
        .form-group label { width: 100%; margin-bottom: 5px; }
        .form-group textarea { width: 100%; }
        .form-group select { width: 100%; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: #007bff; color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; }
        @media (max-width: 768px) {
            .header-content { flex-direction: column; gap: 10px; }
            .nav { flex-wrap: wrap; justify-content: center; }
        }
    </style>
</head>
<body>