<?php 
$pageTitle = 'Crew Management - ZeroAI';
$currentPage = 'crews';
include __DIR__ . '/includes/header.php';
?>

<h1>Crew Management</h1>

<style>
.crew-item { padding: 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px; }
.sequential { border-left: 4px solid #28a745; }
.hierarchical { border-left: 4px solid #007bff; }
.btn-execute { background: #007bff; }
</style>
    
    <div class="card">
        <h3>Create New Crew</h3>
        <form method="POST">
            <input type="text" name="name" placeholder="Crew Name" required>
            <textarea name="description" placeholder="Crew Description" rows="2" required></textarea>
            <select name="process_type">
                <option value="sequential">Sequential Process</option>
                <option value="hierarchical">Hierarchical Process</option>
            </select>
            <button type="submit" name="action" value="create_crew">Create Crew</button>
        </form>
    </div>
    
    <div class="card">
        <h3>Active Crews</h3>
        <div class="crew-item sequential">
            <strong>Development Crew</strong> - Sequential<br>
            <small>Core development team for coding tasks</small><br>
            <button class="btn-execute">Execute Task</button>
            <button>Configure</button>
        </div>
        
        <div class="crew-item hierarchical">
            <strong>Research Crew</strong> - Hierarchical<br>
            <small>Research and analysis team</small><br>
            <button class="btn-execute">Execute Task</button>
            <button>Configure</button>
        </div>
    </div>
<?php include __DIR__ . '/includes/footer.php'; ?>
