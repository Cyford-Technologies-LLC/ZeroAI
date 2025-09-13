<?php 
$pageTitle = 'Task Management - ZeroAI';
$currentPage = 'tasks';
include __DIR__ . '/includes/header.php';
?>

<h1>Task Management</h1>

<style>
.task-item { padding: 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px; }
.pending { border-left: 4px solid #ffc107; }
.running { border-left: 4px solid #007bff; }
.completed { border-left: 4px solid #28a745; }
.failed { border-left: 4px solid #dc3545; }
.metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
.metric { background: white; padding: 15px; border-radius: 8px; text-align: center; }
</style>
    
    <div class="metrics">
        <div class="metric">
            <h3>0</h3>
            <p>Pending Tasks</p>
        </div>
        <div class="metric">
            <h3>0</h3>
            <p>Running Tasks</p>
        </div>
        <div class="metric">
            <h3>0</h3>
            <p>Completed Today</p>
        </div>
        <div class="metric">
            <h3>0</h3>
            <p>Failed Tasks</p>
        </div>
    </div>
    
    <div class="card">
        <h3>Create New Task</h3>
        <form method="POST">
            <textarea name="description" placeholder="Task Description" rows="3" required></textarea>
            <select name="agent_id">
                <option value="">Select Agent</option>
                <option value="1">Team Manager</option>
                <option value="2">Project Manager</option>
                <option value="3">Prompt Refinement Agent</option>
            </select>
            <select name="crew_id">
                <option value="">Select Crew (Optional)</option>
                <option value="1">Development Crew</option>
                <option value="2">Research Crew</option>
            </select>
            <button type="submit" name="action" value="create_task" class="btn-success">Create Task</button>
        </form>
    </div>
    
    <div class="card">
        <h3>Recent Tasks</h3>
        <div class="task-item pending">
            <strong>Task #001</strong> - Pending<br>
            <small>Analyze project requirements and create development plan</small><br>
            <small>Agent: Project Manager | Created: Just now</small><br>
            <button class="btn-success">Execute</button>
            <button class="btn-danger">Cancel</button>
            <button>View Details</button>
        </div>
        
        <div class="task-item completed">
            <strong>Task #000</strong> - Completed<br>
            <small>Initialize ZeroAI portal system</small><br>
            <small>Agent: Team Manager | Completed: 5 minutes ago | Tokens: 1,250</small><br>
            <button>View Results</button>
            <button>Debug Log</button>
        </div>
    </div>
<?php include __DIR__ . '/includes/footer.php'; ?>