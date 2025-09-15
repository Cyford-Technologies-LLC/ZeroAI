<?php
session_start();

if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

// Handle form submission
if ($_POST && isset($_POST['name'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $pdo = $db->getConnection();
        
        $stmt = $pdo->prepare("INSERT INTO companies (name, email, phone, address, industry) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['industry']]);
        $success = "Company added successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get companies
$companies = [];
try {
    require_once __DIR__ . '/../config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    $companies = $pdo->query("SELECT * FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Companies - ZeroAI CRM</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 15px 20px; margin-bottom: 20px; }
        .nav { display: flex; gap: 20px; margin-top: 10px; }
        .nav a { color: white; text-decoration: none; padding: 5px 10px; border-radius: 3px; }
        .nav a:hover { background: rgba(255,255,255,0.2); }
        .nav a.active { background: rgba(255,255,255,0.3); }
        .card { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border: none; border-radius: 3px; cursor: pointer; }
        .btn:hover { background: #2980b9; }
        .alert { padding: 10px; margin-bottom: 20px; border-radius: 3px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏢 ZeroAI CRM - Companies</h1>
        <div class="nav">
            <a href="/web/">Dashboard</a>
            <a href="/web/companies.php" class="active">Companies</a>
            <a href="/web/contacts.php">Contacts</a>
            <a href="/web/projects.php">Projects</a>
            <a href="/web/tasks.php">Tasks</a>
            <a href="/admin/">Admin</a>
            <a href="/web/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>Add New Company</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address"></textarea>
                </div>
                <div class="form-group">
                    <label>Industry</label>
                    <select name="industry">
                        <option value="">Select Industry</option>
                        <option value="Technology">Technology</option>
                        <option value="Healthcare">Healthcare</option>
                        <option value="Finance">Finance</option>
                        <option value="Manufacturing">Manufacturing</option>
                        <option value="Retail">Retail</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <button type="submit" class="btn">Add Company</button>
            </form>
        </div>

        <div class="card">
            <h3>Companies List</h3>
            <?php if (empty($companies)): ?>
                <p>No companies found. Add your first company above or <a href="/web/setup_crm.php">setup the database</a>.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Industry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td><?= htmlspecialchars($company['name']) ?></td>
                                <td><?= htmlspecialchars($company['email']) ?></td>
                                <td><?= htmlspecialchars($company['phone']) ?></td>
                                <td><?= htmlspecialchars($company['industry']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>