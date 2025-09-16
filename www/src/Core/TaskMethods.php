<?php
namespace ZeroAI\Core;

trait TaskMethods {
    
    public function getAllTasks($bypassCache = false) {
        return $this->query('SELECT * FROM tasks ORDER BY id', [], $bypassCache);
    }
    
    public function getTask($id, $bypassCache = false) {
        return $this->query('SELECT * FROM tasks WHERE id = ?', [$id], $bypassCache)[0] ?? null;
    }
    
    public function createTask($data, $bypassQueue = false) {
        return $this->insert('tasks', $data, $bypassQueue);
    }
    
    public function updateTask($id, $data, $bypassQueue = false) {
        return $this->update('tasks', $data, ['id' => $id], $bypassQueue);
    }
    
    public function deleteTask($id, $bypassQueue = false) {
        return $this->delete('tasks', ['id' => $id], $bypassQueue);
    }
    
    public function getActiveTasks($bypassCache = false) {
        return $this->query('SELECT * FROM tasks WHERE status = "active" ORDER BY id', [], $bypassCache);
    }
    
    public function getTasksByAgent($agentId, $bypassCache = false) {
        return $this->query('SELECT * FROM tasks WHERE agent_id = ? ORDER BY id', [$agentId], $bypassCache);
    }
}
?>