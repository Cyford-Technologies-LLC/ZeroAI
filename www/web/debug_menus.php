<?php
require_once __DIR__ . '/includes/menu_system.php';
require_once __DIR__ . '/../../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

echo "<h2>Debug Menu System</h2>";

// Check all menus
$stmt = $pdo->query("SELECT * FROM menus ORDER BY type, menu_group, sort_order");
$allMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>All Menus in Database:</h3>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Group</th><th>Parent ID</th><th>URL</th><th>Active</th></tr>";
foreach ($allMenus as $menu) {
    echo "<tr>";
    echo "<td>{$menu['id']}</td>";
    echo "<td>{$menu['name']}</td>";
    echo "<td>{$menu['type']}</td>";
    echo "<td>{$menu['menu_group']}</td>";
    echo "<td>{$menu['parent_id']}</td>";
    echo "<td>{$menu['url']}</td>";
    echo "<td>{$menu['is_active']}</td>";
    echo "</tr>";
}
echo "</table>";

// Test menu system
$menuSystem = new MenuSystem($pdo);

echo "<h3>Company Context Menus:</h3>";
echo "<pre>";
echo $menuSystem->renderSidebarMenu('companies', null);
echo "</pre>";

echo "<h3>Projects Context Menus:</h3>";
echo "<pre>";
echo $menuSystem->renderSidebarMenu('projects', null);
echo "</pre>";
?>