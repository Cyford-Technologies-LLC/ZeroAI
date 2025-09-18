<?php
$pageTitle = 'Menu Manager - ZeroAI Admin';
$currentPage = 'menu_manager';

// Handle form submission
if ($_POST && isset($_POST['action'])) {
    ob_start();
    include __DIR__ . '/../includes/header.php';
    
    try {
        if ($_POST['action'] === 'create_menu') {
            $stmt = $pdo->prepare("INSERT INTO menus (name, type, parent_id, url, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'], 
                $_POST['type'], 
                $_POST['parent_id'] ?: null, 
                $_POST['url'], 
                $_POST['icon'], 
                $_POST['sort_order'], 
                isset($_POST['is_active']) ? 1 : 0
            ]);
            ob_end_clean();
            header('Location: /admin/settings/menu_manager.php?success=created');
            exit;
        }
        
        if ($_POST['action'] === 'update_menu') {
            $stmt = $pdo->prepare("UPDATE menus SET name=?, type=?, parent_id=?, url=?, icon=?, sort_order=?, is_active=? WHERE id=?");
            $stmt->execute([
                $_POST['name'], 
                $_POST['type'], 
                $_POST['parent_id'] ?: null, 
                $_POST['url'], 
                $_POST['icon'], 
                $_POST['sort_order'], 
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['menu_id']
            ]);
            ob_end_clean();
            header('Location: /admin/settings/menu_manager.php?success=updated');
            exit;
        }
        
        if ($_POST['action'] === 'delete_menu') {
            $stmt = $pdo->prepare("DELETE FROM menus WHERE id = ?");
            $stmt->execute([$_POST['menu_id']]);
            ob_end_clean();
            header('Location: /admin/settings/menu_manager.php?success=deleted');
            exit;
        }
    } catch (Exception $e) {
        ob_end_clean();
        $error = "Error: " . $e->getMessage();
    }
} else {
    include __DIR__ . '/../includes/header.php';
}

// Get all menus
try {
    $stmt = $pdo->query("SELECT * FROM menus ORDER BY type, sort_order, name");
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $menus = [];
}

// Handle success messages
$success = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created': $success = 'Menu item created successfully!'; break;
        case 'updated': $success = 'Menu item updated successfully!'; break;
        case 'deleted': $success = 'Menu item deleted successfully!'; break;
    }
}
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h5>
            <button class="btn btn-link p-0 text-decoration-none" type="button" onclick="toggleCollapse('addMenuForm')">
                <i class="fas fa-plus-circle"></i> Add New Menu Item
            </button>
        </h5>
    </div>
    <div class="collapse" id="addMenuForm" style="display: none;">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="create_menu">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Menu Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Menu Type</label>
                        <select class="form-select" name="type" required>
                            <option value="header">Header Menu</option>
                            <option value="sidebar">Sidebar Menu</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Parent Menu</label>
                        <select class="form-select" name="parent_id">
                            <option value="">None (Top Level)</option>
                            <?php foreach ($menus as $menu): ?>
                                <option value="<?= $menu['id'] ?>"><?= htmlspecialchars($menu['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">URL</label>
                        <input type="text" class="form-control" name="url" placeholder="/web/page.php">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Icon</label>
                        <input type="text" class="form-control" name="icon" placeholder="🏢 or fas fa-building">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" class="form-control" name="sort_order" value="0">
                    </div>
                    <div class="col-12 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Menu Item</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Menu Items</h5>
    </div>
    <div class="card-body">
        <?php if (empty($menus)): ?>
            <p>No menu items found. Create your first menu item above.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Parent</th>
                            <th>URL</th>
                            <th>Icon</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menus as $menu): ?>
                            <tr>
                                <td><?= htmlspecialchars($menu['id']) ?></td>
                                <td><?= htmlspecialchars($menu['name']) ?></td>
                                <td><span class="badge bg-<?= $menu['type'] === 'header' ? 'primary' : 'secondary' ?>"><?= htmlspecialchars($menu['type']) ?></span></td>
                                <td>
                                    <?php 
                                    if ($menu['parent_id']) {
                                        $parentMenu = array_filter($menus, fn($m) => $m['id'] == $menu['parent_id']);
                                        echo htmlspecialchars(reset($parentMenu)['name'] ?? 'Unknown');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($menu['url']) ?></td>
                                <td><?= htmlspecialchars($menu['icon']) ?></td>
                                <td><?= htmlspecialchars($menu['sort_order']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $menu['is_active'] ? 'success' : 'danger' ?>">
                                        <?= $menu['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button onclick="editMenu(<?= $menu['id'] ?>)" class="btn btn-warning btn-sm" data-menu='<?= json_encode($menu) ?>' title="Edit">✏️</button>
                                        <button onclick="deleteMenu(<?= $menu['id'] ?>)" class="btn btn-danger btn-sm" title="Delete">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Menu Modal -->
<div class="modal fade" id="editMenuModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Menu Item</h5>
                <button type="button" class="btn-close" onclick="closeModal('editMenuModal')"></button>
            </div>
            <form method="POST" id="editMenuForm">
                <input type="hidden" name="action" value="update_menu">
                <input type="hidden" name="menu_id" id="editMenuId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Menu Name</label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Menu Type</label>
                            <select class="form-select" name="type" id="editType" required>
                                <option value="header">Header Menu</option>
                                <option value="sidebar">Sidebar Menu</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Parent Menu</label>
                            <select class="form-select" name="parent_id" id="editParentId">
                                <option value="">None (Top Level)</option>
                                <?php foreach ($menus as $menu): ?>
                                    <option value="<?= $menu['id'] ?>"><?= htmlspecialchars($menu['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">URL</label>
                            <input type="text" class="form-control" name="url" id="editUrl">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Icon</label>
                            <input type="text" class="form-control" name="icon" id="editIcon">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" id="editSortOrder">
                        </div>
                        <div class="col-12 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive">
                                <label class="form-check-label" for="editIsActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editMenuModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Menu Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleCollapse(id) {
    const element = document.getElementById(id);
    element.style.display = element.style.display === 'none' ? 'block' : 'none';
}

function editMenu(id) {
    const button = document.querySelector(`button[onclick="editMenu(${id})"]`);
    const menuData = JSON.parse(button.getAttribute('data-menu'));
    
    document.getElementById('editMenuId').value = menuData.id;
    document.getElementById('editName').value = menuData.name || '';
    document.getElementById('editType').value = menuData.type || '';
    document.getElementById('editParentId').value = menuData.parent_id || '';
    document.getElementById('editUrl').value = menuData.url || '';
    document.getElementById('editIcon').value = menuData.icon || '';
    document.getElementById('editSortOrder').value = menuData.sort_order || '';
    document.getElementById('editIsActive').checked = menuData.is_active == 1;
    
    document.getElementById('editMenuModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function deleteMenu(id) {
    if (confirm('Are you sure you want to delete this menu item?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_menu"><input type="hidden" name="menu_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('editMenuModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>