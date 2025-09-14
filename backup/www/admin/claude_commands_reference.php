<?php
// Claude Commands Reference Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claude Commands Reference - ZeroAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 bg-dark text-white p-3">
                <h5><i class="fas fa-robot"></i> ZeroAI Admin</h5>
                <nav class="nav flex-column">
                    <a class="nav-link text-white" href="index.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a class="nav-link text-white" href="claude_settings.php"><i class="fas fa-cog"></i> Claude Settings</a>
                    <a class="nav-link text-white active" href="claude_commands_reference.php"><i class="fas fa-terminal"></i> Commands</a>
                    <a class="nav-link text-white" href="claude_chat.php"><i class="fas fa-comments"></i> Chat</a>
                </nav>
            </div>
            
            <div class="col-md-10 p-4">
                <h2><i class="fas fa-terminal"></i> Claude Commands Reference</h2>
                <p class="text-muted">Complete list of available commands for Claude AI assistant</p>

                <!-- File Operations -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-file"></i> File Operations</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Reading Files</h6>
                                <ul class="list-unstyled">
                                    <li><code>@file path/to/file.py</code> - Read file contents</li>
                                    <li><code>@read path/to/file.py</code> - Read file contents (alias)</li>
                                    <li><code>@list path/to/directory</code> - List directory contents</li>
                                    <li><code>@search pattern</code> - Find files matching pattern</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Writing Files</h6>
                                <ul class="list-unstyled">
                                    <li><code>@create path/to/file.py ```content```</code> - Create file</li>
                                    <li><code>@edit path/to/file.py ```content```</code> - Replace file content</li>
                                    <li><code>@append path/to/file.py ```content```</code> - Add to file</li>
                                    <li><code>@delete path/to/file.py</code> - Delete file</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Docker Operations -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fab fa-docker"></i> Docker Operations</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Container Management</h6>
                                <ul class="list-unstyled">
                                    <li><code>@docker [command]</code> - Execute Docker commands</li>
                                    <li><code>@compose [command]</code> - Execute Docker Compose commands</li>
                                    <li><code>@ps</code> - Show running containers</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Interactive Shell Access</h6>
                                <ul class="list-unstyled">
                                    <li><code>@shell [container] [command]</code> - Start interactive shell session</li>
                                    <li><code>@exec [session_id] [command]</code> - Execute command in shell session</li>
                                    <li><code>@exit [session_id]</code> - Close shell session</li>
                                    <li><code>@sessions</code> - List active shell sessions</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Agent Management -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-users"></i> Agent & Crew Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Agent Operations</h6>
                                <ul class="list-unstyled">
                                    <li><code>@agents</code> - List all agents</li>
                                    <li><code>@update_agent ID role="Role" goal="Goal"</code> - Update agent</li>
                                    <li><code>@optimize_agents</code> - Analyze agent performance</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Crew Operations</h6>
                                <ul class="list-unstyled">
                                    <li><code>@crews</code> - Show crew status</li>
                                    <li><code>@analyze_crew task_id</code> - Analyze crew execution</li>
                                    <li><code>@logs [days] [role]</code> - Show crew logs</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Database Operations -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5><i class="fas fa-database"></i> Database Operations</h5>
                    </div>
                    <div class="card-body">
                        <h6>SQL Commands</h6>
                        <ul class="list-unstyled">
                            <li><code>@sql ```SELECT * FROM table```</code> - Execute SQL queries</li>
                        </ul>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Note:</strong> SQL commands are executed directly on the database. Use with caution.
                        </div>
                    </div>
                </div>

                <!-- Safety Features -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5><i class="fas fa-shield-alt"></i> Safety Features</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Command Protection</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-clock text-warning"></i> Timeout protection (10-30s)</li>
                                    <li><i class="fas fa-ban text-danger"></i> Dangerous command blocking</li>
                                    <li><i class="fas fa-file-alt text-info"></i> All commands logged</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Blocked Commands</h6>
                                <ul class="list-unstyled text-danger">
                                    <li><code>docker rm/kill/stop/restart</code></li>
                                    <li><code>compose down/kill/stop</code></li>
                                    <li>System destructive operations</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usage Examples -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5><i class="fas fa-code"></i> Usage Examples</h5>
                    </div>
                    <div class="card-body">
                        <h6>Common Workflows</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>File Analysis:</strong>
                                <pre class="bg-light p-2 rounded"><code>@read src/main.py
@search "*.py"
@list src/</code></pre>
                            </div>
                            <div class="col-md-6">
                                <strong>Container Debugging:</strong>
                                <pre class="bg-light p-2 rounded"><code>@ps
@shell zeroai_api bash
@exec shell_123 ls -la
@exit shell_123</code></pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Command Logs -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5><i class="fas fa-history"></i> Command Logging</h5>
                    </div>
                    <div class="card-body">
                        <p>All Claude commands are logged to: <code>/app/logs/claude_commands.log</code></p>
                        <p>Log format: <code>[timestamp] @command: parameters</code></p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            Use <code>@read logs/claude_commands.log</code> to view recent command history.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>