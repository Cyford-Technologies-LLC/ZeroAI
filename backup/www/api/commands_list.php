<?php
// API endpoint to get all available Claude commands
header('Content-Type: application/json');

$commands = [
    'file_operations' => [
        'category' => 'File Operations',
        'icon' => 'fas fa-file',
        'commands' => [
            '@file path/to/file.py' => 'Read file contents',
            '@read path/to/file.py' => 'Read file contents (alias)',
            '@list path/to/directory' => 'List directory contents',
            '@search pattern' => 'Find files matching pattern',
            '@create path/to/file.py ```content```' => 'Create file',
            '@edit path/to/file.py ```content```' => 'Replace file content',
            '@append path/to/file.py ```content```' => 'Add to file',
            '@delete path/to/file.py' => 'Delete file'
        ]
    ],
    'docker_operations' => [
        'category' => 'Docker Operations',
        'icon' => 'fab fa-docker',
        'commands' => [
            '@docker [command]' => 'Execute Docker commands',
            '@compose [command]' => 'Execute Docker Compose commands',
            '@ps' => 'Show running containers',
            '@shell [container] [command]' => 'Start interactive shell session',
            '@exec [session_id] [command]' => 'Execute command in shell session',
            '@exit [session_id]' => 'Close shell session',
            '@sessions' => 'List active shell sessions'
        ]
    ],
    'agent_management' => [
        'category' => 'Agent & Crew Management',
        'icon' => 'fas fa-users',
        'commands' => [
            '@agents' => 'List all agents',
            '@update_agent ID role="Role" goal="Goal"' => 'Update agent',
            '@optimize_agents' => 'Analyze agent performance',
            '@crews' => 'Show crew status',
            '@analyze_crew task_id' => 'Analyze crew execution',
            '@logs [days] [role]' => 'Show crew logs'
        ]
    ],
    'database_operations' => [
        'category' => 'Database Operations',
        'icon' => 'fas fa-database',
        'commands' => [
            '@sql ```SELECT * FROM table```' => 'Execute SQL queries'
        ]
    ]
];

echo json_encode($commands, JSON_PRETTY_PRINT);
?>