<?php
// Autonomous Claude Worker - Runs continuously
set_time_limit(0);
ignore_user_abort(true);

while (true) {
    // Check for pending tasks
    $tasks = file_get_contents('/app/data/claude_tasks.json');
    $taskList = json_decode($tasks, true) ?: [];
    
    foreach ($taskList as $key => $task) {
        if ($task['status'] === 'pending') {
            // Execute task
            require_once __DIR__ . '/claude_integration.php';
            $claude = new ClaudeIntegration();
            
            try {
                $response = $claude->chatWithClaude($task['command']);
                
                // Update task status
                $taskList[$key]['status'] = 'completed';
                $taskList[$key]['response'] = $response['message'];
                $taskList[$key]['completed_at'] = date('Y-m-d H:i:s');
                
            } catch (Exception $e) {
                $taskList[$key]['status'] = 'failed';
                $taskList[$key]['error'] = $e->getMessage();
            }
        }
    }
    
    // Save updated tasks
    file_put_contents('/app/data/claude_tasks.json', json_encode($taskList));
    
    sleep(5); // Check every 5 seconds
}
?>