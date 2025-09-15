<?php
namespace ZeroAI\Core;

class AsyncDatabase {
    private $queue;
    
    public function __construct() {
        $this->queue = QueueManager::getInstance();
    }
    
    public function insert($table, $data) {
        return $this->queue->push($table, $data, 'INSERT');
    }
    
    public function update($table, $data) {
        return $this->queue->push($table, $data, 'UPDATE');
    }
    
    public function delete($table, $id) {
        return $this->queue->push($table, ['id' => $id], 'DELETE');
    }
    
    // Log user activity asynchronously
    public function logActivity($userId, $action, $details = null) {
        return $this->insert('activity_log', [
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Log API requests asynchronously
    public function logApiRequest($endpoint, $method, $responseTime, $statusCode) {
        return $this->insert('api_logs', [
            'endpoint' => $endpoint,
            'method' => $method,
            'response_time' => $responseTime,
            'status_code' => $statusCode,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Update user stats asynchronously
    public function updateUserStats($userId, $stats) {
        return $this->update('user_stats', array_merge($stats, ['id' => $userId]));
    }
}
